<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/**
 * PoS orders: create (e.g. "mark as paid in cash/card") and list.
 */
class PosOrderController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * List orders for a store (optional filter by pos_terminal_id).
     */
    public function index(Request $request, Store $store)
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        $q = $store->posOrders()->with('posTerminal')->orderByDesc('created_at');
        if ($request->filled('pos_terminal_id')) {
            $q->where('pos_terminal_id', $request->input('pos_terminal_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        $orders = $q->limit(100)->get()->map(fn (PosOrder $o) => $this->formatOrder($o));
        return response()->json(['data' => $orders]);
    }

    /**
     * Create a PoS order (e.g. "Mark as paid in cash" / "Mark as paid by card").
     * Cash/card only allowed if plan has offline_payment_methods.
     */
    public function store(Request $request, Store $store)
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'pos_terminal_id' => ['required', 'uuid', 'exists:pos_terminals,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'paid_method' => ['required', 'string', 'in:lightning,onchain,cash,card'],
            'btcpay_invoice_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $terminal = PosTerminal::where('id', $request->input('pos_terminal_id'))->where('store_id', $store->id)->first();
        if (!$terminal) {
            abort(404, 'PoS terminal not found');
        }

        $paidMethod = $request->input('paid_method');
        if (in_array($paidMethod, ['cash', 'card'], true)) {
            if (!$this->subscriptionService->canUseOfflinePaymentMethods($request->user())) {
                return response()->json([
                    'message' => '"Mark as Paid in Cash" and "Mark as Paid by Card" are available on Pro. Please upgrade.',
                ], 403);
            }
            if (!in_array($paidMethod, $terminal->getEnabledPaymentMethods(), true)) {
                return response()->json([
                    'message' => "Payment method \"{$paidMethod}\" is not enabled for this terminal.",
                ], 422);
            }
        }

        $order = PosOrder::create([
            'pos_terminal_id' => $terminal->id,
            'store_id' => $store->id,
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency', $store->default_currency ?? 'EUR'),
            'status' => PosOrder::STATUS_PAID,
            'paid_method' => $paidMethod,
            'btcpay_invoice_id' => $request->input('btcpay_invoice_id'),
            'metadata_json' => $request->input('metadata'),
            'paid_at' => now(),
        ]);

        return response()->json(['data' => $this->formatOrder($order->load('posTerminal'))], 201);
    }

    private function formatOrder(PosOrder $o): array
    {
        return [
            'id' => $o->id,
            'pos_terminal_id' => $o->pos_terminal_id,
            'store_id' => $o->store_id,
            'amount' => (float) $o->amount,
            'currency' => $o->currency,
            'status' => $o->status,
            'paid_method' => $o->paid_method,
            'btcpay_invoice_id' => $o->btcpay_invoice_id,
            'paid_at' => $o->paid_at?->toIso8601String(),
            'created_at' => $o->created_at->toIso8601String(),
            'pos_terminal' => $o->relationLoaded('posTerminal') && $o->posTerminal
                ? ['id' => $o->posTerminal->id, 'name' => $o->posTerminal->name]
                : null,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Boltz\SettlementLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettlementController extends Controller
{
    public function __construct(protected SettlementLedgerService $ledger) {}

    /**
     * Paged settlement ledger for the store; optional per-invoice filter.
     */
    public function index(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $store->settlements()->orderByDesc('paid_at')->orderByDesc('created_at');
        if (! empty($validated['invoice_id'])) {
            $query->where('btcpay_invoice_id', $validated['invoice_id']);
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 25)));
    }

    /**
     * On-demand sync: one invoice (invoice_id) or a recent-invoices backfill.
     */
    public function sync(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['sometimes', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        if (! empty($validated['invoice_id'])) {
            $rows = $this->ledger->syncInvoice($store, $validated['invoice_id'], forgetCache: true);

            return response()->json(['data' => ['invoices' => 1, 'rows' => $rows]]);
        }

        $result = $this->ledger->syncRecent($store, (int) ($validated['limit'] ?? 50));

        return response()->json(['data' => $result]);
    }
}

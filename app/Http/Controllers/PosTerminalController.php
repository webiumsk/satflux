<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class PosTerminalController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * List PoS terminals for a store.
     */
    public function index(Request $request, Store $store)
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        $terminals = $store->posTerminals()->orderBy('name')->get()->map(fn (PosTerminal $t) => $this->formatTerminal($t));
        return response()->json(['data' => $terminals]);
    }

    /**
     * Create a PoS terminal.
     */
    public function store(Request $request, Store $store)
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'enabled_payment_methods' => ['nullable', 'array'],
            'enabled_payment_methods.*' => ['string', 'in:lightning,onchain,cash,card'],
        ]);

        $methods = $request->input('enabled_payment_methods', PosTerminal::DEFAULT_PAYMENT_METHODS);
        if (!$this->subscriptionService->canUseOfflinePaymentMethods($request->user())) {
            $methods = array_values(array_intersect($methods, ['lightning', 'onchain']));
        }

        $terminal = PosTerminal::create([
            'store_id' => $store->id,
            'name' => $request->input('name'),
            'settings_json' => ['enabled_payment_methods' => $methods],
        ]);

        return response()->json(['data' => $this->formatTerminal($terminal)], 201);
    }

    /**
     * Update PoS terminal (including enabled_payment_methods). Cash/card only if plan allows.
     */
    public function update(Request $request, Store $store, PosTerminal $posTerminal)
    {
        if ($store->user_id !== $request->user()->id || $posTerminal->store_id !== $store->id) {
            abort(403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'enabled_payment_methods' => ['nullable', 'array'],
            'enabled_payment_methods.*' => ['string', 'in:lightning,onchain,cash,card'],
        ]);

        $settings = $posTerminal->settings_json ?? [];
        if ($request->has('enabled_payment_methods')) {
            $methods = $request->input('enabled_payment_methods');
            if (!$this->subscriptionService->canUseOfflinePaymentMethods($request->user())) {
                $methods = array_values(array_intersect($methods, ['lightning', 'onchain']));
            }
            $settings['enabled_payment_methods'] = $methods;
        }
        if ($request->has('name')) {
            $posTerminal->name = $request->input('name');
        }
        $posTerminal->settings_json = $settings;
        $posTerminal->save();

        return response()->json(['data' => $this->formatTerminal($posTerminal->fresh())]);
    }

    /**
     * Delete PoS terminal.
     */
    public function destroy(Request $request, Store $store, PosTerminal $posTerminal)
    {
        if ($store->user_id !== $request->user()->id || $posTerminal->store_id !== $store->id) {
            abort(403);
        }

        $posTerminal->delete();
        return response()->json(['message' => 'PoS terminal deleted'], 204);
    }

    private function formatTerminal(PosTerminal $t): array
    {
        return [
            'id' => $t->id,
            'store_id' => $t->store_id,
            'name' => $t->name,
            'enabled_payment_methods' => $t->getEnabledPaymentMethods(),
        ];
    }
}

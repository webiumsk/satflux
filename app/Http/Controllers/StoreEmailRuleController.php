<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailRuleRequest;
use App\Models\Store;
use App\Models\StoreEmailRule;
use Illuminate\Http\JsonResponse;

class StoreEmailRuleController extends Controller
{
    protected const TRIGGER_LABELS = [
        'InvoiceCreated' => 'Invoice - Created',
        'InvoiceReceivedPayment' => 'Invoice - Received Payment',
        'InvoiceProcessing' => 'Invoice - Is Processing',
        'InvoiceExpired' => 'Invoice - Expired',
        'InvoiceSettled' => 'Invoice - Is Settled',
        'InvoiceInvalid' => 'Invoice - Became Invalid',
        'InvoicePaymentSettled' => 'Invoice - Payment Settled',
        'InvoiceExpiredPaidPartial' => 'Invoice - Expired Paid Partial',
        'InvoicePaidAfterExpiration' => 'Invoice - Expired Paid Late',
    ];

    /**
     * Trigger options for dropdowns (invoice events only).
     */
    public function triggers(Store $store): JsonResponse
    {
        $data = [];
        foreach (config('invoice_email_triggers', []) as $value) {
            $data[] = [
                'value' => $value,
                'label' => self::TRIGGER_LABELS[$value] ?? $value,
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function index(Store $store): JsonResponse
    {
        $rules = $store->emailRules()->get();

        return response()->json([
            'data' => $rules->map(fn (StoreEmailRule $r) => $this->serializeRule($r))->all(),
        ]);
    }

    public function store(StoreEmailRuleRequest $request, Store $store): JsonResponse
    {
        $rule = $store->emailRules()->create($request->payloadForModel());

        return response()->json([
            'data' => $this->serializeRule($rule),
            'message' => 'Email rule created.',
        ], 201);
    }

    public function update(StoreEmailRuleRequest $request, Store $store, StoreEmailRule $store_email_rule): JsonResponse
    {
        $this->assertRuleStore($store, $store_email_rule);
        $store_email_rule->update($request->payloadForModel());

        return response()->json([
            'data' => $this->serializeRule($store_email_rule->fresh()),
            'message' => 'Email rule updated.',
        ]);
    }

    public function destroy(Store $store, StoreEmailRule $store_email_rule): JsonResponse
    {
        $this->assertRuleStore($store, $store_email_rule);
        $store_email_rule->delete();

        return response()->json(['message' => 'Email rule deleted.']);
    }

    protected function assertRuleStore(Store $store, StoreEmailRule $rule): void
    {
        abort_if($rule->store_id !== $store->id, 404);
    }

    protected function serializeRule(StoreEmailRule $r): array
    {
        return [
            'id' => $r->id,
            'trigger' => $r->trigger,
            'trigger_label' => self::TRIGGER_LABELS[$r->trigger] ?? $r->trigger,
            'condition' => $r->condition,
            'to_addresses' => $r->to_addresses,
            'cc_addresses' => $r->cc_addresses,
            'bcc_addresses' => $r->bcc_addresses,
            'send_to_buyer' => $r->send_to_buyer,
            'subject' => $r->subject,
            'body' => $r->body,
            'sort_order' => $r->sort_order,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }
}

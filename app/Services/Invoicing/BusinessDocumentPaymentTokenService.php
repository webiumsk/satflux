<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use Illuminate\Support\Str;

class BusinessDocumentPaymentTokenService
{
    public function assignIfNeeded(BusinessDocument $document): void
    {
        if (! $document->payment_btc_enabled || ! $document->store_id) {
            $document->payment_token = null;

            return;
        }

        if (! $document->payment_token) {
            $document->payment_token = $this->generateUniqueToken();
        }
    }

    public function ensureForDocument(BusinessDocument $document): void
    {
        if (! $document->payment_btc_enabled) {
            return;
        }

        $this->assignIfNeeded($document);
        if ($document->isDirty('payment_token')) {
            $document->save();
        }
    }

    public function payUrl(BusinessDocument $document): ?string
    {
        if (! $document->payment_token) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/pay/i/'.$document->payment_token;
    }

    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (BusinessDocument::query()->where('payment_token', $token)->exists());

        return $token;
    }
}

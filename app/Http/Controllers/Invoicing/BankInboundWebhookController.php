<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankNotificationEmail;
use App\Services\Invoicing\BankInboundWebhookPayloadNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankInboundWebhookController extends Controller
{
    public function handle(Request $request, BankInboundWebhookPayloadNormalizer $normalizer): JsonResponse
    {
        if ($normalizer->isMailgunPayload($request)) {
            if ($authFailure = $this->mailgunAuthFailure($request, $normalizer)) {
                return $authFailure;
            }
        } elseif ($authFailure = $this->nativeAuthFailure($request)) {
            return $authFailure;
        }

        $validated = Validator::make($normalizer->normalize($request), [
            'to' => ['required', 'string', 'max:255'],
            'from' => ['required', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:65535'],
            'headers' => ['sometimes', 'string', 'max:65535'],
        ])->validate();

        ProcessBankNotificationEmail::dispatch([
            'to' => $validated['to'],
            'from' => $validated['from'],
            'subject' => $validated['subject'] ?? '',
            'body' => $validated['body'],
            'headers' => $validated['headers'] ?? '',
        ]);

        return response()->json(['accepted' => true]);
    }

    protected function mailgunAuthFailure(
        Request $request,
        BankInboundWebhookPayloadNormalizer $normalizer,
    ): ?JsonResponse {
        $signingKey = config('bank_inbound.mailgun_webhook_signing_key');
        if (! is_string($signingKey) || $signingKey === '') {
            return response()->json(['error' => 'Mailgun webhook signing key is not configured.'], 503);
        }

        if (! $normalizer->verifyMailgunSignature($request, $signingKey)) {
            return response()->json(['error' => 'Invalid Mailgun webhook signature.'], 403);
        }

        return null;
    }

    protected function nativeAuthFailure(Request $request): ?JsonResponse
    {
        $secret = config('bank_inbound.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['error' => 'Bank inbound webhook secret is not configured.'], 503);
        }

        if (! hash_equals($secret, (string) $request->header('X-Bank-Inbound-Secret', ''))) {
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        return null;
    }
}

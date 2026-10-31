<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankNotificationEmail;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\Invoicing\BankInboundWebhookPayloadNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class BankInboundWebhookController extends Controller
{
    public function handleMime(
        Request $request,
        BankInboundWebhookPayloadNormalizer $normalizer,
    ): JsonResponse {
        // A .mime destination makes Mailgun supply the original message.
        // Do not silently accept a parsed delivery on this dedicated route.
        if ($authFailure = $this->mailgunAuthFailure($request, $normalizer)) {
            return $authFailure;
        }

        return $this->dispatchMime($request);
    }

    public function handle(
        Request $request,
        BankInboundWebhookPayloadNormalizer $normalizer,
        BankEmailOriginVerifier $originVerifier,
    ): JsonResponse {
        $isMailgun = $normalizer->isMailgunPayload($request);
        if ($isMailgun) {
            if ($authFailure = $this->mailgunAuthFailure($request, $normalizer)) {
                return $authFailure;
            }
        } elseif ($authFailure = $this->nativeAuthFailure($request)) {
            return $authFailure;
        }

        if ($isMailgun && $request->has('body-mime')) {
            return $this->dispatchMime($request);
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
            // Only the protected native receiving-MTA adapter may attest bank
            // identity. Mailgun delivery auth and MIME headers cannot do so.
            '_verified_bank_domain' => $isMailgun ? null : $originVerifier->nativeVerifiedDomain(
                $request->input('bank_authentication'),
                $validated['from'],
            ),
        ]);

        return response()->json(['accepted' => true]);
    }

    private function dispatchMime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'body-mime' => ['required', 'string', 'max:262144'],
        ]);
        if (strlen($validated['body-mime']) > 262144) {
            return response()->json(['message' => 'Bank MIME message is too large.'], 422);
        }

        ProcessBankNotificationEmail::dispatch([
            'to' => strtolower(trim($validated['recipient'])),
            'from' => '',
            'subject' => '',
            'body' => '',
            // Raw bank mail must not appear in serialized/failed job payloads.
            '_encrypted_bank_mime' => Crypt::encryptString($validated['body-mime']),
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

        $providedSecret = $request->header('X-Bank-Inbound-Secret');
        if (! is_string($providedSecret) || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        return null;
    }
}

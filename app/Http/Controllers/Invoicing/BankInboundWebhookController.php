<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankNotificationEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankInboundWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('bank_inbound.webhook_secret');
        if ($secret && $request->header('X-Bank-Inbound-Secret') !== $secret) {
            abort(403, 'Invalid webhook secret');
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:255'],
            'from' => ['required', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:65535'],
            'headers' => ['sometimes', 'string', 'max:65535'],
        ]);

        ProcessBankNotificationEmail::dispatch([
            'to' => $validated['to'],
            'from' => $validated['from'],
            'subject' => $validated['subject'] ?? '',
            'body' => $validated['body'],
            'headers' => $validated['headers'] ?? '',
        ]);

        return response()->json(['accepted' => true]);
    }
}

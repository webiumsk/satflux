<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use App\Support\Legal\LegalConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactInquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'type' => ['required', 'string', 'in:enterprise,support,general'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'locale' => ['nullable', 'string', 'max:8'],
        ], LegalConsent::privacyOnlyRules()));

        $inquiry = ContactInquiry::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'privacy_consent_at' => now(),
            'locale' => $validated['locale'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        $this->notifyTeam($inquiry);

        return response()->json([
            'message' => __('messages.contact_inquiry_received'),
        ], 201);
    }

    private function notifyTeam(ContactInquiry $inquiry): void
    {
        $to = config('mail.contact_inbox', 'hello@satflux.io');
        $body = implode("\n", [
            'Type: '.$inquiry->type,
            'Name: '.$inquiry->name,
            'Email: '.$inquiry->email,
            'Subject: '.($inquiry->subject ?: '-'),
            '',
            $inquiry->message,
        ]);

        try {
            Mail::raw($body, function ($message) use ($to, $inquiry) {
                $message->to($to)
                    ->replyTo($inquiry->email, $inquiry->name)
                    ->subject('[satflux.io contact] '.$inquiry->type.($inquiry->subject ? ': '.$inquiry->subject : ''));
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send contact inquiry email', [
                'inquiry_id' => $inquiry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

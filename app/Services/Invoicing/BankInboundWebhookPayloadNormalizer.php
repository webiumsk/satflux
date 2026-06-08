<?php

namespace App\Services\Invoicing;

use Illuminate\Http\Request;

class BankInboundWebhookPayloadNormalizer
{
    public function isMailgunPayload(Request $request): bool
    {
        return $request->filled('recipient')
            || $request->filled('stripped-text')
            || $request->filled('body-plain');
    }

    /**
     * @return array{to: string, from: string, subject: string, body: string, headers: string}
     */
    public function normalize(Request $request): array
    {
        if ($this->isMailgunPayload($request)) {
            return $this->normalizeMailgun($request);
        }

        return [
            'to' => strtolower(trim((string) $request->input('to'))),
            'from' => trim((string) $request->input('from')),
            'subject' => trim((string) $request->input('subject', '')),
            'body' => (string) $request->input('body'),
            'headers' => (string) $request->input('headers', ''),
        ];
    }

    public function verifyMailgunSignature(Request $request, string $signingKey): bool
    {
        $timestamp = (string) $request->input('timestamp', '');
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');

        if ($timestamp === '' || $token === '' || $signature === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $timestamp.$token, $signingKey),
            $signature,
        );
    }

    /**
     * @return array{to: string, from: string, subject: string, body: string, headers: string}
     */
    protected function normalizeMailgun(Request $request): array
    {
        $body = trim((string) (
            $request->input('stripped-text')
            ?: $request->input('body-plain')
            ?: $request->input('stripped-html')
            ?: $request->input('body-html')
            ?: ''
        ));

        $from = trim((string) (
            $request->input('sender')
            ?: $request->input('from')
            ?: $request->input('From')
            ?: ''
        ));

        return [
            'to' => strtolower(trim((string) $request->input('recipient'))),
            'from' => $from,
            'subject' => trim((string) $request->input('subject', '')),
            'body' => $body,
            'headers' => $this->normalizeMailgunHeaders($request->input('message-headers')),
        ];
    }

    protected function normalizeMailgunHeaders(mixed $raw): string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $raw;
        }

        $lines = [];
        foreach ($decoded as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1])) {
                $lines[] = $pair[0].': '.$pair[1];
            }
        }

        return implode("\r\n", $lines);
    }
}

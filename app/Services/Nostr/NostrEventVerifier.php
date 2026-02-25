<?php

namespace App\Services\Nostr;

use Illuminate\Support\Facades\Log;
use Mdanter\Ecc\Crypto\Signature\SchnorrSigner;

/**
 * Verify Nostr events (NIP-01): event id computation and Schnorr signature (BIP-340).
 * Uses ext-secp256k1-nostr when available, otherwise paragonie/ecc SchnorrSigner.
 */
class NostrEventVerifier
{
    /**
     * Verify event structure, id and signature.
     * Returns true only if event id matches computed id and signature is valid (when verification available).
     */
    public function verify(array $event): bool
    {
        $pubkey = $event['pubkey'] ?? null;
        $id = $event['id'] ?? null;
        $sig = $event['sig'] ?? null;
        $created_at = $event['created_at'] ?? null;
        $kind = $event['kind'] ?? null;
        $tags = $event['tags'] ?? [];
        $content = $event['content'] ?? '';

        if (! is_string($pubkey) || ! is_string($id) || ! is_string($sig)) {
            return false;
        }
        if (strlen($pubkey) !== 64 || strlen($id) !== 64 || strlen($sig) !== 128) {
            return false;
        }
        if (! ctype_xdigit($pubkey) || ! ctype_xdigit($id) || ! ctype_xdigit($sig)) {
            return false;
        }

        $expectedId = $this->computeEventId($pubkey, $created_at, $kind, $tags, $content);
        if (! hash_equals($expectedId, $id)) {
            return false;
        }

        return $this->verifySignature($id, $sig, $pubkey);
    }

    /**
     * NIP-01: event id = sha256(serialize([0, pubkey, created_at, kind, tags, content])).
     */
    public function computeEventId(string $pubkey, $created_at, $kind, array $tags, string $content): string
    {
        $payload = [0, $pubkey, (int) $created_at, (int) $kind, $tags, $content];
        $serialized = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $serialized);
    }

    /**
     * Verify BIP-340 Schnorr signature.
     * Uses ext-secp256k1-nostr when available; otherwise Mdanter\Ecc\Crypto\Signature\SchnorrSigner (paragonie/ecc).
     */
    protected function verifySignature(string $eventIdHash, string $signatureHex, string $pubkeyHex): bool
    {
        if (function_exists('secp256k1_nostr_verify')) {
            $hashBin = hex2bin($eventIdHash);
            $sigBin = hex2bin($signatureHex);
            $pubkeyBin = hex2bin($pubkeyHex);
            if ($hashBin === false || $sigBin === false || $pubkeyBin === false) {
                return false;
            }

            return secp256k1_nostr_verify($hashBin, $sigBin, $pubkeyBin);
        }

        try {
            $schnorr = new SchnorrSigner;
            return $schnorr->verify($pubkeyHex, $signatureHex, $eventIdHash);
        } catch (\Throwable $e) {
            Log::warning('Nostr signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

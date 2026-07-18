<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserPasskeyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated fetch of a passkey recovery envelope during "sign in with
 * a passkey": the client learns the credential id from the WebAuthn
 * assertion, pulls the ciphertext here, decrypts it locally with the
 * PRF-derived key and then signs the existing Ed25519 recovery challenge
 * with the decrypted phrase - so this endpoint grants NO access by itself.
 *
 * Response discipline: ciphertext only, never any account information, and
 * a generic 404 for unknown ids (credential ids are high-entropy, so
 * enumeration is impractical; the route is rate limited on top).
 */
class PasskeyEnvelopeLoginController extends Controller
{
    public function fetch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,512}$/'],
        ]);

        $envelope = UserPasskeyEnvelope::query()
            ->where('credential_id', $validated['credential_id'])
            ->first();

        if (! $envelope) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $envelope->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'data' => [
                'payload' => $envelope->payload,
                'envelope_version' => $envelope->envelope_version,
            ],
        ]);
    }
}

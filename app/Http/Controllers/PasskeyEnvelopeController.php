<?php

namespace App\Http\Controllers;

use App\Models\UserPasskeyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Account-scoped CRUD for passkey recovery envelopes (see the model doc:
 * ciphertext-only, zero-knowledge). The unauthenticated login-path fetch
 * lives in Auth\PasskeyEnvelopeLoginController.
 */
class PasskeyEnvelopeController extends Controller
{
    /** Hard cap per account - a user has no legitimate need for more. */
    public const MAX_ENVELOPES_PER_USER = 10;

    /** Ciphertext of a 24-word phrase is well under this; reject bloat. */
    public const MAX_PAYLOAD_BYTES = 4096;

    public function index(Request $request): JsonResponse
    {
        $envelopes = UserPasskeyEnvelope::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['credential_id', 'label', 'created_at', 'last_used_at']);

        return response()->json(['data' => $envelopes]);
    }

    public function upsert(Request $request, string $credentialId): JsonResponse
    {
        $this->assertCredentialIdFormat($credentialId);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'string', 'max:'.self::MAX_PAYLOAD_BYTES],
            'envelope_version' => ['nullable', 'integer', 'min:1', 'max:100'],
            'transports' => ['nullable', 'array', 'max:8'],
            'transports.*' => ['string', 'max:32'],
        ]);

        $user = $request->user();

        $existing = UserPasskeyEnvelope::query()
            ->where('credential_id', $credentialId)
            ->first();

        // A credential id belongs to exactly one account; never let one
        // account overwrite another's envelope (generic message, no oracle).
        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'credential_id' => ['This passkey cannot be saved.'],
            ]);
        }

        if (! $existing) {
            $count = UserPasskeyEnvelope::query()->where('user_id', $user->id)->count();
            if ($count >= self::MAX_ENVELOPES_PER_USER) {
                throw ValidationException::withMessages([
                    'credential_id' => ['Passkey limit reached for this account.'],
                ]);
            }
        }

        $envelope = UserPasskeyEnvelope::query()->updateOrCreate(
            ['credential_id' => $credentialId],
            [
                'user_id' => $user->id,
                'label' => $validated['label'] ?? null,
                'payload' => $validated['payload'],
                'envelope_version' => $validated['envelope_version'] ?? 1,
                'transports' => $validated['transports'] ?? null,
            ],
        );

        return response()->json([
            'data' => $envelope->only(['credential_id', 'label', 'created_at', 'last_used_at']),
        ]);
    }

    public function destroy(Request $request, string $credentialId): JsonResponse
    {
        $this->assertCredentialIdFormat($credentialId);

        UserPasskeyEnvelope::query()
            ->where('user_id', $request->user()->id)
            ->where('credential_id', $credentialId)
            ->delete();

        return response()->json(['message' => 'Passkey envelope deleted.']);
    }

    protected function assertCredentialIdFormat(string $credentialId): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]{16,512}$/', $credentialId)) {
            throw ValidationException::withMessages([
                'credential_id' => ['Invalid credential id.'],
            ]);
        }
    }
}

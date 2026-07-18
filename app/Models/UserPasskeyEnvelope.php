<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zero-knowledge passkey recovery envelope: the payload is the user's
 * recovery phrase encrypted CLIENT-SIDE with a key derived from the
 * passkey's WebAuthn PRF output. The server only stores ciphertext -
 * decryption requires the physical authenticator with user verification.
 */
class UserPasskeyEnvelope extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'label',
        'payload',
        'envelope_version',
        'transports',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'last_used_at' => 'datetime',
            'envelope_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserApiKey extends Model
{
    use HasFactory;

    protected $table = 'user_api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Create a new API key and return the plaintext token (only shown once).
     */
    public static function createKey(User $user, string $name): array
    {
        $plain = 'd21_' . Str::random(48);
        $hash = hash('sha256', $plain);

        $key = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'key_hash' => $hash,
        ]);

        return [
            'id' => $key->id,
            'name' => $key->name,
            'plain_token' => $plain,
            'created_at' => $key->created_at,
        ];
    }

    /**
     * Find key by plain token (for authentication). Returns null if revoked or not found.
     */
    public static function findValidByToken(string $token): ?self
    {
        $hash = hash('sha256', $token);
        $key = self::where('key_hash', $hash)->whereNull('revoked_at')->first();
        if ($key) {
            $key->update(['last_used_at' => now()]);
        }
        return $key;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class WalletConnection extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'type',
        'encrypted_secret',
        'status',
        'reconfig',
        'submitted_by_user_id',
        'revealed_last_at',
        'revealed_last_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revealed_last_at' => 'datetime',
            'reconfig' => 'boolean',
        ];
    }

    /**
     * Get the store that owns the wallet connection.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who submitted the wallet connection.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * Get the user who last revealed the secret.
     */
    public function revealedLastBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_last_by');
    }

    /**
     * Get the masked secret (first 6 + last 6 characters).
     * This is an accessor that can be accessed via $model->masked_secret.
     */
    public function getMaskedSecretAttribute(): string
    {
        $encrypted = $this->attributes['encrypted_secret'] ?? '';
        if (empty($encrypted)) {
            return '';
        }

        try {
            $decrypted = Crypt::decryptString($encrypted);
            if (strlen($decrypted) <= 12) {
                // If secret is too short, just show stars
                return str_repeat('*', strlen($decrypted));
            }
            return substr($decrypted, 0, 6) . '...' . substr($decrypted, -6);
        } catch (\Exception $e) {
            // If decryption fails, return masked placeholder
            return '******...******';
        }
    }

    /**
     * Reveal the plaintext secret (only for support/admin use).
     *
     * @return string Plaintext secret
     */
    public function reveal(): string
    {
        return Crypt::decryptString($this->encrypted_secret);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'lightning_public_key',
        'btcpay_user_id',
        'btcpay_api_key',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'btcpay_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'btcpay_api_key' => 'encrypted', // Encrypt API key in database
        ];
    }

    /**
     * Check if user is a support user.
     */
    public function isSupport(): bool
    {
        return $this->role === 'support' || $this->role === 'admin';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a merchant.
     */
    public function isMerchant(): bool
    {
        return $this->role === 'merchant' || empty($this->role);
    }

    /**
     * Get the stores for the user.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Mark the email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Get BTCPay API key or throw exception.
     * 
     * @return string The decrypted BTCPay API key
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function getBtcPayApiKeyOrFail(): string
    {
        if (!$this->btcpay_api_key) {
            abort(500, 'BTCPay API key not configured. Please contact support.');
        }
        
        return $this->btcpay_api_key;
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'btcpay_store_id',
        'name',
        'default_currency',
        'timezone',
        'preferred_exchange',
        'wallet_type',
        'metadata',
        'auto_report_enabled',
        'auto_report_email',
        'auto_report_format',
        'webhook_secret',
        'btcpay_webhook_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'auto_report_enabled' => 'boolean',
            'webhook_secret' => 'encrypted',
        ];
    }

    /**
     * Get the user that owns the store.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the checklist items for the store.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(StoreChecklist::class);
    }

    /**
     * Get the exports for the store.
     */
    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    /**
     * Get the apps for the store.
     */
    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }

    /**
     * Get the wallet connection for the store.
     */
    public function walletConnection(): HasOne
    {
        return $this->hasOne(WalletConnection::class);
    }

    /**
     * Get the API keys for the store.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(StoreApiKey::class);
    }

    /**
     * Get the PoS terminals for the store.
     */
    public function posTerminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class, 'store_id');
    }

    /**
     * Get PoS orders for the store.
     */
    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'store_id');
    }

    /**
     * Scope a query to only include stores for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}








<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pos_terminals';

    protected $fillable = [
        'store_id',
        'name',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    public const DEFAULT_PAYMENT_METHODS = ['lightning', 'onchain'];

    public const OFFLINE_PAYMENT_METHODS = ['cash', 'card'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'pos_terminal_id');
    }

    /**
     * Get enabled payment methods from settings (default: lightning, onchain).
     */
    public function getEnabledPaymentMethods(): array
    {
        $methods = $this->settings_json['enabled_payment_methods'] ?? self::DEFAULT_PAYMENT_METHODS;
        return is_array($methods) ? $methods : self::DEFAULT_PAYMENT_METHODS;
    }

    /**
     * Check if cash is enabled.
     */
    public function hasCashEnabled(): bool
    {
        return in_array('cash', $this->getEnabledPaymentMethods(), true);
    }

    /**
     * Check if card is enabled.
     */
    public function hasCardEnabled(): bool
    {
        return in_array('card', $this->getEnabledPaymentMethods(), true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'display_name',
        'price_eur',
        'billing_period',
        'max_stores',
        'max_api_keys',
        'max_ln_addresses',
        'max_events',
        'features',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_eur' => 'decimal:2',
            'max_stores' => 'integer',
            'max_api_keys' => 'integer',
            'max_ln_addresses' => 'integer',
            'max_events' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Check if this plan has unlimited stores.
     */
    public function hasUnlimitedStores(): bool
    {
        return $this->max_stores === null;
    }

    /**
     * Check if this plan has unlimited API keys.
     */
    public function hasUnlimitedApiKeys(): bool
    {
        return $this->max_api_keys === null;
    }

    /**
     * Check if this plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features ?? [];
        return in_array($feature, $features);
    }

    /**
     * Check if this plan has unlimited LN addresses.
     */
    public function hasUnlimitedLnAddresses(): bool
    {
        return $this->max_ln_addresses === null;
    }

    /**
     * Check if this plan has unlimited ticket events per store.
     */
    public function hasUnlimitedEvents(): bool
    {
        return $this->max_events === null;
    }

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


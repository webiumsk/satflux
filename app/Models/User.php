<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'name',
        'email',
        'password',
        'lightning_public_key',
        'btcpay_user_id',
        'btcpay_api_key',
        'role',
        'btcpay_subscription_id',
        'subscription_expires_at',
        'subscription_grace_period_ends_at',
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
            'subscription_expires_at' => 'datetime',
            'subscription_grace_period_ends_at' => 'datetime',
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
     * Check if user has unlimited access (admin, support, or enterprise).
     */
    public function hasUnlimitedAccess(): bool
    {
        return $this->isAdmin() || $this->isSupport() || $this->isEnterprise();
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
     * Check if user has a Pro plan.
     */
    public function isPro(): bool
    {
        return $this->role === 'pro';
    }

    /**
     * Check if user has an Enterprise plan.
     */
    public function isEnterprise(): bool
    {
        return $this->role === 'enterprise';
    }

    /**
     * Check if user has a paid plan (Pro or Enterprise).
     */
    public function isPaidPlan(): bool
    {
        return $this->isPro() || $this->isEnterprise();
    }

    /**
     * Get maximum number of Lightning Addresses allowed for this user.
     * Based on subscription plan (max_ln_addresses).
     *
     * @return int|null Maximum number of addresses (null = unlimited)
     */
    public function getMaxLightningAddresses(): ?int
    {
        if ($this->hasUnlimitedAccess()) {
            return null;
        }

        $plan = $this->currentSubscriptionPlan();
        if (! $plan) {
            return 1; // No plan = Free tier
        }

        // Free plan: always max 1 (never unlimited), even if max_ln_addresses is null in DB
        $code = strtolower($plan->code ?? $plan->name ?? '');
        if ($code === 'free') {
            return (int) ($plan->max_ln_addresses ?? 1);
        }

        if ($plan->hasUnlimitedLnAddresses()) {
            return null;
        }

        return (int) $plan->max_ln_addresses;
    }

    /**
     * Get the stores for the user.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get the subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's panel API keys (not BTCPay keys).
     */
    public function userApiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class, 'user_id');
    }

    /**
     * Get the current active subscription for the user.
     * Returns the most recent active subscription, or null if none exists.
     * 
     * @return \App\Models\Subscription|null
     */
    public function currentSubscription(): ?\App\Models\Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'grace'])
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    /**
     * Get the current subscription plan for the user.
     * Returns the plan for the current subscription, or FREE plan if no subscription.
     *
     * @return \App\Models\SubscriptionPlan|null
     */
    public function currentSubscriptionPlan(): ?\App\Models\SubscriptionPlan
    {
        $subscription = $this->currentSubscription();
        if ($subscription) {
            return $subscription->plan;
        }

        return SubscriptionPlan::where('code', 'free')->orWhere('name', 'free')->first();
    }

    /**
     * Check if the user's plan has a feature (e.g. advanced_stats, automatic_csv_exports, offline_payment_methods).
     */
    public function planFeature(string $feature): bool
    {
        $plan = $this->currentSubscriptionPlan();
        return $plan ? $plan->hasFeature($feature) : false;
    }

    /**
     * Check if user has an active subscription (not expired).
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->currentSubscription();
        return $subscription && $subscription->isActive();
    }

    /**
     * Check if user's subscription is in grace period.
     */
    public function isSubscriptionInGracePeriod(): bool
    {
        $subscription = $this->currentSubscription();
        return $subscription && $subscription->isInGracePeriod();
    }

    /**
     * Check if user's subscription is expired.
     */
    public function isSubscriptionExpired(): bool
    {
        $subscription = $this->currentSubscription();
        return !$subscription || $subscription->isExpired();
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
        return !is_null($this->email_verified_at);
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


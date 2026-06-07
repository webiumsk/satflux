<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const BILLING_TRIAL = 'trial';

    public const BILLING_PAID = 'paid';

    public const BILLING_GRACE = 'grace';

    public const BILLING_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'billing_phase',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'grace_ends_at',
        'btcpay_subscription_id',
        'auto_renew',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function isTrial(): bool
    {
        return $this->billing_phase === self::BILLING_TRIAL;
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    /**
     * Check if the subscription is in grace period.
     * Uses grace_ends_at if set, otherwise config pricing.grace_days after expiration.
     */
    public function isInGracePeriod(): bool
    {
        if ($this->isTrial()) {
            return false;
        }

        if ($this->status === 'grace') {
            return true;
        }

        if ($this->status === 'expired') {
            return false;
        }

        $graceEnds = $this->grace_ends_at ?? $this->expires_at->copy()->addDays((int) config('pricing.grace_days', 30));

        return $this->expires_at->isPast() && now()->isBefore($graceEnds);
    }

    /**
     * Check if the subscription is expired (beyond grace period).
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired' || $this->billing_phase === self::BILLING_EXPIRED) {
            return true;
        }

        if ($this->isTrial() && $this->expires_at->isPast()) {
            return true;
        }

        if ($this->expires_at->isPast()) {
            $gracePeriodEnds = $this->grace_ends_at
                ?? $this->expires_at->copy()->addDays((int) config('pricing.grace_days', 30));

            return now()->isAfter($gracePeriodEnds);
        }

        return false;
    }

    /**
     * Update subscription status based on expiration date.
     * This should be called periodically (e.g., via a scheduled task).
     */
    public function updateStatus(): void
    {
        if ($this->isTrial()) {
            if ($this->expires_at->isFuture()) {
                $this->status = 'active';
                $this->billing_phase = self::BILLING_TRIAL;
            } else {
                $this->status = 'expired';
                $this->billing_phase = self::BILLING_EXPIRED;
            }

            $this->save();

            return;
        }

        if ($this->expires_at->isFuture()) {
            $this->status = 'active';
            $this->billing_phase = self::BILLING_PAID;
        } elseif ($this->isInGracePeriod()) {
            $this->status = 'grace';
            $this->billing_phase = self::BILLING_GRACE;
        } else {
            $this->status = 'expired';
            $this->billing_phase = self::BILLING_EXPIRED;
        }

        $this->save();
    }

    /**
     * Extend subscription by 1 year from current expiration date.
     */
    public function extendOneYear(): void
    {
        $newExpiresAt = $this->expires_at->isFuture()
            ? $this->expires_at->copy()->addYear()
            : now()->addYear();
        $this->expires_at = $newExpiresAt;
        $this->grace_ends_at = $newExpiresAt->copy()->addDays((int) config('pricing.grace_days', 30));
        $this->trial_ends_at = null;
        $this->status = 'active';
        $this->billing_phase = self::BILLING_PAID;
        $this->save();
    }

    public function convertToPaidYear(): void
    {
        $newExpiresAt = now()->addYear();
        $this->expires_at = $newExpiresAt;
        $this->grace_ends_at = $newExpiresAt->copy()->addDays((int) config('pricing.grace_days', 30));
        $this->trial_ends_at = null;
        $this->status = 'active';
        $this->billing_phase = self::BILLING_PAID;
        $this->save();
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include subscriptions in grace period.
     */
    public function scopeInGrace($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'grace')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'active')
                        ->where('expires_at', '<=', now())
                        ->where('expires_at', '>', now()->subDays(14));
                });
        });
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere(function ($q2) {
                    $q2->where('expires_at', '<=', now()->subDays(14));
                });
        });
    }
}

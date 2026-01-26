<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'expires_at',
        'btcpay_subscription_id',
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
        ];
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
     * Grace period is 14 days after expiration.
     */
    public function isInGracePeriod(): bool
    {
        if ($this->status === 'grace') {
            return true;
        }

        if ($this->status === 'expired') {
            return false;
        }

        // Check if expired but within 14-day grace period
        if ($this->expires_at->isPast()) {
            $gracePeriodEnds = $this->expires_at->copy()->addDays(14);
            return now()->isBefore($gracePeriodEnds);
        }

        return false;
    }

    /**
     * Check if the subscription is expired (beyond grace period).
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expires_at->isPast()) {
            $gracePeriodEnds = $this->expires_at->copy()->addDays(14);
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
        if ($this->expires_at->isFuture()) {
            $this->status = 'active';
        } elseif ($this->isInGracePeriod()) {
            $this->status = 'grace';
        } else {
            $this->status = 'expired';
        }

        $this->save();
    }

    /**
     * Extend subscription by 1 year from current expiration date.
     */
    public function extendOneYear(): void
    {
        $newExpiresAt = $this->expires_at->copy()->addYear();
        $this->expires_at = $newExpiresAt;
        $this->status = 'active';
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


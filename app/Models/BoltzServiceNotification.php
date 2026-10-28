<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable campaign claim. An uncertain transport result must never be retried automatically.
 */
class BoltzServiceNotification extends Model
{
    public const CAMPAIGN = 'boltz-receiving-unavailable-2026-10';

    protected $fillable = [
        'user_id',
        'campaign',
        'locale',
        'status',
        'claimed_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}

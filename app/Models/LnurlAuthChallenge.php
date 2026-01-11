<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LnurlAuthChallenge extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'k1';
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'k1',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Check if the challenge is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the challenge has been consumed.
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NostrAuthChallenge extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'expires_at',
        'consumed_at',
        'nostr_public_key',
        'link_user_id',
        'purpose',
        'pending_user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function pendingUser()
    {
        return $this->belongsTo(User::class, 'pending_user_id');
    }

    public function linkUser()
    {
        return $this->belongsTo(User::class, 'link_user_id');
    }
}

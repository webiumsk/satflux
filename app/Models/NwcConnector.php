<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NwcConnector extends Model
{
    use HasUuids;

    protected $table = 'nwc_connectors';

    protected $fillable = [
        'store_id',
        'btcpay_store_id',
        'nostr_pubkey',
        'relay_url',
        'backend_type',
        'allowed_methods',
        'rate_limit_per_min',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'allowed_methods' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'nostr_secret_encrypted',
        'backend_config_encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

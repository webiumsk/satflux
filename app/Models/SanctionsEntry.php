<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SanctionsEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'source',
        'external_id',
        'primary_name',
        'primary_name_normalized',
        'aliases_normalized',
        'countries',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'aliases_normalized' => 'array',
            'countries' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}

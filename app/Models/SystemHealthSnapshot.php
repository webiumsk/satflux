<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'healthy',
        'checks',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'healthy' => 'boolean',
            'checks' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

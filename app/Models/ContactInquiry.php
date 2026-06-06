<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type',
        'name',
        'email',
        'subject',
        'message',
        'privacy_consent_at',
        'locale',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'privacy_consent_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletConfigurationAttempt extends Model
{
    protected $fillable = ['store_id', 'connection_type', 'encrypted_secret', 'status'];

    protected $hidden = ['encrypted_secret'];

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['applying', 'uncertain']);
    }
}

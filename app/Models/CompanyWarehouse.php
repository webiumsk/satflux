<?php

namespace App\Models;

use App\Enums\CompanyWarehouseType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyWarehouse extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'deduct_on_issue',
        'is_default',
        'is_active',
        'company_contact_id',
        'street',
        'city',
        'postal_code',
        'country',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyWarehouseType::class,
            'deduct_on_issue' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'company_contact_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(CompanyStockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CompanyStockItemMovement::class);
    }
}

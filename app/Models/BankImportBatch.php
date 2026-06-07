<?php

namespace App\Models;

use App\Enums\BankImportSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'user_id',
        'source',
        'filename',
        'storage_path',
        'row_count',
        'imported_count',
        'skipped_duplicates',
        'auto_matched_count',
    ];

    protected function casts(): array
    {
        return [
            'source' => BankImportSource::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}

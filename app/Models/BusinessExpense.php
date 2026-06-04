<?php

namespace App\Models;

use App\Enums\BusinessExpenseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'status',
        'internal_number',
        'external_number',
        'title',
        'variable_symbol',
        'constant_symbol',
        'specific_symbol',
        'issue_date',
        'delivery_date',
        'due_date',
        'paid_at',
        'total',
        'currency',
        'internal_note',
        'attachment_disk',
        'attachment_path',
        'original_filename',
        'attachment_mime',
    ];

    protected function casts(): array
    {
        return [
            'status' => BusinessExpenseStatus::class,
            'issue_date' => 'date',
            'delivery_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isOverdue(): bool
    {
        if ($this->status === BusinessExpenseStatus::Paid || $this->status === BusinessExpenseStatus::Cancelled) {
            return false;
        }

        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null && $this->attachment_path !== '';
    }
}

<?php

namespace App\Models;

use App\Enums\RecurringInterval;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessRecurringProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'company_contact_id',
        'store_id',
        'document_type',
        'is_active',
        'recurrence_interval',
        'first_issue_date',
        'next_issue_date',
        'repeat_indefinitely',
        'ends_at',
        'issue_last_day_of_month',
        'title',
        'variable_symbol',
        'constant_symbol',
        'specific_symbol',
        'payment_terms_days',
        'delivery_date_mode',
        'currency',
        'discount_percent',
        'subtotal',
        'tax_total',
        'total',
        'note_above_lines',
        'note_footer',
        'internal_note',
        'pdf_locale',
        'pdf_show_signature',
        'pdf_show_payment_info',
        'payment_btc_enabled',
        'payment_bank_enabled',
        'send_email_after_issue',
        'email_bcc',
        'tags',
        'last_generated_document_id',
        'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'recurrence_interval' => RecurringInterval::class,
            'first_issue_date' => 'date',
            'next_issue_date' => 'date',
            'ends_at' => 'date',
            'repeat_indefinitely' => 'boolean',
            'issue_last_day_of_month' => 'boolean',
            'tags' => 'array',
            'payment_btc_enabled' => 'boolean',
            'payment_bank_enabled' => 'boolean',
            'pdf_show_signature' => 'boolean',
            'pdf_show_payment_info' => 'boolean',
            'send_email_after_issue' => 'boolean',
            'last_generated_at' => 'datetime',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BusinessRecurringProfileLine::class)->orderBy('sort_order');
    }
}

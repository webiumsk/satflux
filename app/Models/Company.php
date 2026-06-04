<?php

namespace App\Models;

use App\Enums\CompanyJurisdiction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'legal_name',
        'trade_name',
        'registration_number',
        'tax_id',
        'vat_number',
        'commercial_register',
        'street',
        'city',
        'postal_code',
        'country',
        'state_region',
        'iban',
        'bic',
        'bank_name',
        'bank_account',
        'bank_code',
        'default_currency',
        'jurisdiction',
        'vat_payer',
        'vat_status',
        'vat_rate_default',
        'legal_footer_note',
        'issuer_name',
        'issuer_phone',
        'issuer_email',
        'website',
        'invoice_number_prefix',
        'app_settings',
        'email_settings',
        'logo_path',
        'signature_stamp_path',
    ];

    protected function casts(): array
    {
        return [
            'vat_payer' => 'boolean',
            'vat_rate_default' => 'decimal:2',
            'jurisdiction' => CompanyJurisdiction::class,
            'app_settings' => 'array',
            'email_settings' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedAppSettings(): array
    {
        return \App\Support\Invoicing\CompanyAppSettings::from($this->app_settings)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedEmailSettings(): array
    {
        return app(\App\Services\Invoicing\CompanyEmailSettingsService::class)
            ->publicPayload($this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BusinessExpense::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function bankImportBatches(): HasMany
    {
        return $this->hasMany(BankImportBatch::class);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(CompanyDocumentSequence::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}

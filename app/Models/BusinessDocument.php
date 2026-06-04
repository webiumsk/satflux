<?php

namespace App\Models;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'company_contact_id',
        'store_id',
        'source_document_id',
        'type',
        'status',
        'quote_status',
        'number',
        'title',
        'variable_symbol',
        'constant_symbol',
        'specific_symbol',
        'issue_date',
        'delivery_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_total',
        'discount_percent',
        'total',
        'note_above_lines',
        'note_footer',
        'internal_note',
        'pdf_locale',
        'pdf_show_signature',
        'pdf_show_payment_info',
        'paid_at',
        'amount_paid',
        'tags',
        'btcpay_invoice_id',
        'btcpay_checkout_link',
        'payment_token',
        'btcpay_checkout_created_at',
        'payment_btc_enabled',
        'payment_bank_enabled',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => BusinessDocumentType::class,
            'status' => BusinessDocumentStatus::class,
            'quote_status' => BusinessDocumentQuoteStatus::class,
            'issue_date' => 'date',
            'delivery_date' => 'date',
            'due_date' => 'date',
            'tags' => 'array',
            'payment_btc_enabled' => 'boolean',
            'payment_bank_enabled' => 'boolean',
            'pdf_show_signature' => 'boolean',
            'pdf_show_payment_info' => 'boolean',
            'paid_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'btcpay_checkout_created_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_percent' => 'decimal:2',
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

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function finalInvoice(): HasOne
    {
        return $this->hasOne(self::class, 'source_document_id')
            ->where('type', BusinessDocumentType::Invoice)
            ->where('status', '!=', BusinessDocumentStatus::Cancelled);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BusinessDocumentLine::class)->orderBy('sort_order');
    }

    public function complianceSubmissions(): HasMany
    {
        return $this->hasMany(BusinessDocumentCompliance::class);
    }

    public function canUpdate(): bool
    {
        return in_array($this->status, [
            BusinessDocumentStatus::Draft,
            BusinessDocumentStatus::Issued,
        ], true);
    }

    public function canIssue(): bool
    {
        return $this->status === BusinessDocumentStatus::Draft;
    }

    public function resolvedQuoteStatus(): ?BusinessDocumentQuoteStatus
    {
        if ($this->type !== BusinessDocumentType::Quote) {
            return null;
        }

        if ($this->quote_status === BusinessDocumentQuoteStatus::Pending
            && $this->due_date?->isPast()) {
            return BusinessDocumentQuoteStatus::Expired;
        }

        return $this->quote_status;
    }

    protected function appendResolvedQuoteStatus(array $array): array
    {
        if ($this->type === BusinessDocumentType::Quote) {
            $array['resolved_quote_status'] = $this->resolvedQuoteStatus()?->value;
        }

        return $array;
    }

    public function toArray(): array
    {
        return $this->appendResolvedQuoteStatus(parent::toArray());
    }

}

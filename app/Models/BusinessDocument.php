<?php

namespace App\Models;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Services\Invoicing\DocumentSequenceService;
use App\Support\Invoicing\BuyerSnapshot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessDocument extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        // Any change can move the top number of a series (see
        // DocumentSequenceService::latestNumberedDocumentId()).
        $forget = fn () => app(DocumentSequenceService::class)->forgetLatestNumbered();
        static::saved($forget);
        static::deleted($forget);
    }

    protected $fillable = [
        'company_id',
        'company_contact_id',
        'buyer_snapshot',
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
        'pdf_bank_qr',
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

    protected $appends = [
        'can_update',
        'can_delete',
        'can_cancel',
        'can_unmark_paid',
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
            'buyer_snapshot' => 'array',
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

    /**
     * Buyer for PDF/exports: frozen snapshot when present, else live contact.
     */
    public function resolvedBuyer(): ?CompanyContact
    {
        $snapshot = $this->buyer_snapshot;
        if (is_array($snapshot) && $snapshot !== []) {
            return BuyerSnapshot::asContact($snapshot);
        }

        if ($this->relationLoaded('contact')) {
            return $this->contact;
        }

        if ($this->company_contact_id) {
            return $this->contact()->first();
        }

        return null;
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

    public function derivedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id')
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

    public function bankMatch(): HasOne
    {
        return $this->hasOne(BankTransactionMatch::class, 'business_document_id');
    }

    public function canUpdate(?Company $company = null): bool
    {
        if ($this->hasGermanGobdIssuedContentLock($company)) {
            return false;
        }

        return in_array($this->status, [
            BusinessDocumentStatus::Draft,
            BusinessDocumentStatus::Issued,
        ], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            BusinessDocumentStatus::Issued,
            BusinessDocumentStatus::Paid,
        ], true);
    }

    public function canUnmarkPaid(): bool
    {
        return $this->status === BusinessDocumentStatus::Paid;
    }

    /**
     * Drafts and never-numbered cancelled documents are always deletable.
     * A numbered document (issued, paid or cancelled) only when it holds
     * the top number of its type and has no active derivatives - deleting
     * it lowers the series counter, so its number is reissued (gapless
     * numbering). Deleting a numbered document in the middle would leave a
     * hole; deleting a cancelled one while an issued one was blocked made
     * the two layers disagree (local-first never allowed it either).
     */
    public function canDelete(?Company $company = null): bool
    {
        if ($this->hasGermanGobdIssuedContentLock($company)) {
            return false;
        }

        if ($this->hasStatus(BusinessDocumentStatus::Draft)
            || ($this->hasStatus(BusinessDocumentStatus::Cancelled) && ! $this->hasNumber())) {
            return ! $this->hasBlockingRelations();
        }

        if (! $this->hasStatus(
            BusinessDocumentStatus::Issued,
            BusinessDocumentStatus::Paid,
            BusinessDocumentStatus::Cancelled,
        )) {
            return false;
        }

        return $this->isLatestForCompany() && ! $this->hasBlockingRelations();
    }

    /**
     * Status check on the cast value. Larastan types the raw column here
     * (string), so direct enum comparisons read as "always false".
     */
    public function hasStatus(BusinessDocumentStatus ...$statuses): bool
    {
        return in_array($this->getAttribute('status'), $statuses, true);
    }

    /** The document type's string value (see hasStatus for why). */
    public function typeValue(): string
    {
        $type = $this->getAttribute('type');

        return $type instanceof BusinessDocumentType ? $type->value : (string) $type;
    }

    public function hasNumber(): bool
    {
        $number = $this->getAttribute('number');

        return $number !== null && $number !== '';
    }

    protected function hasGermanGobdIssuedContentLock(?Company $company = null): bool
    {
        if ($this->status === BusinessDocumentStatus::Draft) {
            return false;
        }

        $company ??= $this->relationLoaded('company')
            ? $this->company
            : $this->company()->first(['id', 'jurisdiction']);

        return $company?->jurisdiction === CompanyJurisdiction::EuDe;
    }

    /** Holds the top number of its document type (see canDelete). */
    public function isLatestForCompany(): bool
    {
        return app(DocumentSequenceService::class)
            ->latestNumberedDocumentId((string) $this->company_id, $this->typeValue()) === $this->id;
    }

    protected function hasBlockingRelations(): bool
    {
        // eFaktura submissions are evidence held by the tax authority -
        // deleting would cascade them away and reissue a reported number.
        if ($this->relationLoaded('complianceSubmissions')) {
            if ($this->complianceSubmissions->isNotEmpty()) {
                return true;
            }
        } elseif ($this->complianceSubmissions()->exists()) {
            return true;
        }

        if ($this->relationLoaded('bankMatch')) {
            if ($this->bankMatch !== null) {
                return true;
            }
        } elseif ($this->bankMatch()->exists()) {
            return true;
        }

        if ($this->relationLoaded('derivedDocuments')) {
            return $this->derivedDocuments->isNotEmpty();
        }

        return $this->derivedDocuments()->exists();
    }

    public function canIssue(): bool
    {
        return $this->status === BusinessDocumentStatus::Draft;
    }

    public function getCanUpdateAttribute(): bool
    {
        return $this->canUpdate();
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->canDelete();
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canCancel();
    }

    public function getCanUnmarkPaidAttribute(): bool
    {
        return $this->canUnmarkPaid();
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

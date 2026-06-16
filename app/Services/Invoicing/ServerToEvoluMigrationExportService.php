<?php

namespace App\Services\Invoicing;

use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\BusinessExpense;
use App\Models\BusinessExpenseAttachment;
use App\Models\BusinessRecurringProfile;
use App\Models\BusinessRecurringProfileLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyDocumentSequence;
use App\Models\CompanyStockBalance;
use App\Models\CompanyStockItem;
use App\Models\CompanyStockItemMovement;
use App\Models\CompanyWarehouse;
use App\Models\User;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\CompanyEmailSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ServerToEvoluMigrationExportService
{
    private const MAX_ATTACHMENT_BYTES = 384 * 1024;

    private const MAX_IMAGE_DATA_URL_CHARS = 131_072;

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly CompanyBrandingService $brandingService,
    ) {}

    /**
     * @return array{
     *     snapshot: array<string, list<array<string, mixed>>>,
     *     warnings: list<string>,
     *     counts: array<string, int>
     * }
     */
    public function exportForUser(User $user): array
    {
        $this->warnings = [];

        $companies = Company::query()
            ->where('user_id', $user->id)
            ->with(['stores:id,company_id'])
            ->orderBy('legal_name')
            ->get();

        if ($companies->isEmpty()) {
            return $this->emptyResult();
        }

        $companyIds = $companies->pluck('id')->all();

        $snapshot = [
            'company' => [],
            'contact' => [],
            'numberSeries' => [],
            'document' => [],
            'documentLine' => [],
            'documentEvent' => [],
            'expense' => [],
            'expenseAttachment' => [],
            'recurringProfile' => [],
            'recurringProfileLine' => [],
            'companyWarehouse' => [],
            'companyStockItem' => [],
            'companyStockBalance' => [],
            'companyStockMovement' => [],
            'bankImportBatch' => [],
            'bankTransaction' => [],
            'bankTransactionMatch' => [],
        ];

        foreach ($companies as $company) {
            $snapshot['company'][] = $this->mapCompany($company);
        }

        $contacts = CompanyContact::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('name')
            ->get();
        foreach ($contacts as $contact) {
            $snapshot['contact'][] = $this->mapContact($contact);
        }

        $sequences = CompanyDocumentSequence::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('document_type')
            ->orderBy('name')
            ->get();
        foreach ($sequences as $sequence) {
            $snapshot['numberSeries'][] = $this->mapNumberSeries($sequence);
        }

        $documents = BusinessDocument::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('issue_date')
            ->orderBy('number')
            ->get();
        foreach ($documents as $document) {
            $snapshot['document'][] = $this->mapDocument($document);
        }

        $documentIds = $documents->pluck('id')->all();
        if ($documentIds !== []) {
            $lines = BusinessDocumentLine::query()
                ->whereIn('business_document_id', $documentIds)
                ->orderBy('sort_order')
                ->get();
            foreach ($lines as $line) {
                $snapshot['documentLine'][] = $this->mapDocumentLine($line);
            }
        }

        $expenses = BusinessExpense::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('issue_date')
            ->orderBy('internal_number')
            ->get();
        foreach ($expenses as $expense) {
            $snapshot['expense'][] = $this->mapExpense($expense);
        }

        $expenseIds = $expenses->pluck('id')->all();
        $expensesWithRowAttachments = [];
        if ($expenseIds !== []) {
            $attachments = BusinessExpenseAttachment::query()
                ->whereIn('business_expense_id', $expenseIds)
                ->orderBy('created_at')
                ->get();
            foreach ($attachments as $attachment) {
                $expensesWithRowAttachments[$attachment->business_expense_id] = true;
                $mapped = $this->mapExpenseAttachment($attachment);
                if ($mapped !== null) {
                    $snapshot['expenseAttachment'][] = $mapped;
                }
            }
        }

        foreach ($expenses as $expense) {
            if (isset($expensesWithRowAttachments[$expense->id])) {
                continue;
            }
            $mappedLegacy = $this->mapLegacyExpenseAttachment($expense);
            if ($mappedLegacy !== null) {
                $snapshot['expenseAttachment'][] = $mappedLegacy;
            }
        }

        $profiles = BusinessRecurringProfile::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('title')
            ->get();
        foreach ($profiles as $profile) {
            $snapshot['recurringProfile'][] = $this->mapRecurringProfile($profile);
        }

        $profileIds = $profiles->pluck('id')->all();
        if ($profileIds !== []) {
            $profileLines = BusinessRecurringProfileLine::query()
                ->whereIn('business_recurring_profile_id', $profileIds)
                ->orderBy('sort_order')
                ->get();
            foreach ($profileLines as $line) {
                $snapshot['recurringProfileLine'][] = $this->mapRecurringProfileLine($line);
            }
        }

        $warehouses = CompanyWarehouse::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('name')
            ->get();
        foreach ($warehouses as $warehouse) {
            $snapshot['companyWarehouse'][] = $this->mapWarehouse($warehouse);
        }

        $warehouseIds = $warehouses->pluck('id')->all();
        $stockItems = CompanyStockItem::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('name')
            ->get();
        foreach ($stockItems as $item) {
            $snapshot['companyStockItem'][] = $this->mapStockItem($item);
        }

        if ($warehouseIds !== []) {
            $balances = CompanyStockBalance::query()
                ->whereIn('company_warehouse_id', $warehouseIds)
                ->with('warehouse:id,company_id')
                ->get();
            foreach ($balances as $balance) {
                $mapped = $this->mapStockBalance($balance);
                if ($mapped !== null) {
                    $snapshot['companyStockBalance'][] = $mapped;
                }
            }
        }

        $movements = CompanyStockItemMovement::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('created_at')
            ->get();
        foreach ($movements as $movement) {
            $snapshot['companyStockMovement'][] = $this->mapStockMovement($movement);
        }

        $batches = BankImportBatch::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('created_at')
            ->get();
        foreach ($batches as $batch) {
            $snapshot['bankImportBatch'][] = $this->mapBankImportBatch($batch);
        }

        $transactions = BankTransaction::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('booked_at')
            ->get();
        foreach ($transactions as $transaction) {
            $snapshot['bankTransaction'][] = $this->mapBankTransaction($transaction);
        }

        $transactionIds = $transactions->pluck('id')->all();
        if ($transactionIds !== []) {
            $matches = BankTransactionMatch::query()
                ->whereIn('bank_transaction_id', $transactionIds)
                ->get();
            foreach ($matches as $match) {
                $snapshot['bankTransactionMatch'][] = $this->mapBankTransactionMatch($match);
            }
        }

        return [
            'snapshot' => $snapshot,
            'warnings' => $this->warnings,
            'counts' => $this->countSnapshot($snapshot),
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     companies_count: int,
     *     contacts_count: int,
     *     documents_count: int,
     *     expenses_count: int
     * }
     */
    public function statusForUser(User $user): array
    {
        $companiesCount = Company::query()
            ->where('user_id', $user->id)
            ->count();

        if ($companiesCount === 0) {
            return [
                'available' => false,
                'companies_count' => 0,
                'contacts_count' => 0,
                'documents_count' => 0,
                'expenses_count' => 0,
            ];
        }

        $companyIds = Company::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        return [
            'available' => true,
            'companies_count' => $companiesCount,
            'contacts_count' => CompanyContact::query()->whereIn('company_id', $companyIds)->count(),
            'documents_count' => BusinessDocument::query()->whereIn('company_id', $companyIds)->count(),
            'expenses_count' => BusinessExpense::query()->whereIn('company_id', $companyIds)->count(),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $snapshot
     * @return array<string, int>
     */
    private function countSnapshot(array $snapshot): array
    {
        $counts = [];
        foreach ($snapshot as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /**
     * @return array{
     *     snapshot: array<string, list<array<string, mixed>>>,
     *     warnings: list<string>,
     *     counts: array<string, int>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'snapshot' => [
                'company' => [],
                'contact' => [],
                'numberSeries' => [],
                'document' => [],
                'documentLine' => [],
                'documentEvent' => [],
                'expense' => [],
                'expenseAttachment' => [],
                'recurringProfile' => [],
                'recurringProfileLine' => [],
                'companyWarehouse' => [],
                'companyStockItem' => [],
                'companyStockBalance' => [],
                'companyStockMovement' => [],
                'bankImportBatch' => [],
                'bankTransaction' => [],
                'bankTransactionMatch' => [],
            ],
            'warnings' => [],
            'counts' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCompany(Company $company): array
    {
        $linkedStoreId = $company->stores->first()?->id;

        $logoDataUrl = $this->brandingDataUrl($company->logo_path, 'logo', $company->legal_name);
        $signatureDataUrl = $this->brandingDataUrl($company->signature_stamp_path, 'signature', $company->legal_name);

        return [
            'id' => $company->id,
            'legalName' => $company->legal_name,
            'tradeName' => $company->trade_name,
            'jurisdiction' => $company->jurisdiction->value,
            'defaultCurrency' => $company->default_currency,
            'registrationNumber' => $company->registration_number,
            'taxId' => $company->tax_id,
            'vatNumber' => $company->vat_number,
            'commercialRegister' => $company->commercial_register,
            'street' => $company->street,
            'city' => $company->city,
            'postalCode' => $company->postal_code,
            'country' => $this->countryCodeOrNull($company->country),
            'stateRegion' => $company->state_region,
            'iban' => $company->iban,
            'bic' => $company->bic,
            'bankName' => $company->bank_name,
            'bankAccount' => $company->bank_account,
            'bankCode' => $company->bank_code,
            'vatPayer' => $this->boolToSqlite($company->vat_payer),
            'vatStatus' => $company->vat_status ?? ($company->vat_payer ? 'payer' : 'none'),
            'vatRateDefault' => $this->decimalStr($company->vat_rate_default),
            'legalFooterNote' => $company->legal_footer_note,
            'issuerName' => $company->issuer_name,
            'issuerPhone' => $company->issuer_phone,
            'issuerEmail' => $company->issuer_email,
            'website' => $company->website,
            'invoiceNumberPrefix' => $company->invoice_number_prefix,
            'linkedStoreId' => $linkedStoreId,
            'appSettingsJson' => $this->truncateJsonBlob($this->jsonOrNull($this->appSettingsForMigration($company))),
            'emailSettingsJson' => $this->truncateJsonBlob($this->jsonOrNull($this->emailSettingsForMigration($company))),
            'logoDataUrl' => $logoDataUrl,
            'signatureDataUrl' => $signatureDataUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appSettingsForMigration(Company $company): array
    {
        $settings = CompanyAppSettings::from($company->app_settings)->toArray();
        $efaktura = CompanyEfakturaSettings::fromCompany($company);
        $secret = $efaktura->sapiClientSecret();
        if ($secret !== null) {
            $settings['efaktura_sapi_client_secret'] = $secret;
        }
        unset($settings['efaktura_sapi_client_secret_encrypted']);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailSettingsForMigration(Company $company): array
    {
        $settings = CompanyEmailSettings::from($company->email_settings)->toArray();
        $smtp = is_array($settings['smtp'] ?? null) ? $settings['smtp'] : [];
        $encrypted = $smtp['password_encrypted'] ?? null;
        if (is_string($encrypted) && $encrypted !== '') {
            try {
                $smtp['password'] = Crypt::decryptString($encrypted);
            } catch (Throwable) {
                $this->warnings[] = "smtp_password_decrypt_failed:{$company->id}";
            }
        }
        unset($smtp['password_encrypted']);
        $settings['smtp'] = $smtp;

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapContact(CompanyContact $contact): array
    {
        return [
            'id' => $contact->id,
            'companyId' => $contact->company_id,
            'name' => $contact->name,
            'registrationNumber' => $contact->registration_number,
            'peppolParticipantId' => $contact->peppol_participant_id,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'fax' => $contact->fax,
            'taxId' => $contact->tax_id,
            'vatId' => $contact->vat_id,
            'street' => $contact->street,
            'city' => $contact->city,
            'postalCode' => $contact->postal_code,
            'stateRegion' => $contact->state_region,
            'country' => $contact->country,
            'bankAccount' => $contact->bank_account,
            'bankCode' => $contact->bank_code,
            'iban' => $contact->iban,
            'swift' => $contact->swift,
            'deliveryStreet' => $contact->delivery_street,
            'deliveryPostalCode' => $contact->delivery_postal_code,
            'deliveryCity' => $contact->delivery_city,
            'deliveryCountry' => $contact->delivery_country,
            'defaultPaymentTermsDays' => $contact->default_payment_terms_days !== null
                ? (string) $contact->default_payment_terms_days
                : null,
            'notes' => $contact->notes,
            'contactPersonsJson' => $this->jsonOrNull($contact->contact_persons ?? []),
            'isActive' => $this->boolToSqlite($contact->is_active),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNumberSeries(CompanyDocumentSequence $sequence): array
    {
        return [
            'id' => (string) Str::uuid(),
            'companyId' => $sequence->company_id,
            'name' => $sequence->name,
            'documentType' => $sequence->document_type,
            'format' => $sequence->format,
            'resetPeriod' => $sequence->reset_period,
            'isDefault' => $this->boolToSqlite($sequence->is_default),
            'periodKey' => $sequence->period_key,
            'lastNumber' => $sequence->last_number !== null ? (string) $sequence->last_number : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDocument(BusinessDocument $document): array
    {
        return [
            'id' => $document->id,
            'companyId' => $document->company_id,
            'contactId' => $document->company_contact_id,
            'documentType' => $document->type->value,
            'status' => $document->status->value,
            'quoteStatus' => $document->quote_status?->value,
            'title' => $this->nonEmptyTitle($document->title, $document->number, $document->type->value),
            'number' => $document->number,
            'sourceDocumentId' => $document->source_document_id,
            'issueDate' => $this->dateStr($document->issue_date),
            'deliveryDate' => $this->dateStr($document->delivery_date),
            'dueDate' => $this->dateStr($document->due_date),
            'variableSymbol' => $document->variable_symbol,
            'constantSymbol' => $document->constant_symbol,
            'specificSymbol' => $document->specific_symbol,
            'currency' => $document->currency,
            'subtotal' => $this->decimalStr($document->subtotal),
            'taxTotal' => $this->decimalStr($document->tax_total),
            'discountPercent' => $this->decimalStr($document->discount_percent),
            'total' => $this->decimalStr($document->total),
            'noteAboveLines' => $document->note_above_lines,
            'noteFooter' => $document->note_footer,
            'internalNote' => $document->internal_note,
            'pdfLocale' => $document->pdf_locale,
            'pdfShowSignature' => $this->boolToSqlite($document->pdf_show_signature),
            'pdfShowPaymentInfo' => $this->boolToSqlite($document->pdf_show_payment_info),
            'paymentBankEnabled' => $this->boolToSqlite($document->payment_bank_enabled),
            'paymentBtcEnabled' => $this->boolToSqlite($document->payment_btc_enabled),
            'storeId' => $document->store_id,
            'tagsJson' => $this->jsonOrNull($document->tags ?? []),
            'paidAt' => $this->datetimeStr($document->paid_at),
            'amountPaid' => $this->decimalStr($document->amount_paid),
            'emailSentAt' => $this->datetimeStr($document->email_sent_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDocumentLine(BusinessDocumentLine $line): array
    {
        return [
            'id' => $line->id,
            'documentId' => $line->business_document_id,
            'sortOrder' => (string) $line->sort_order,
            'name' => $line->name,
            'description' => $line->description,
            'quantity' => $this->decimalStr($line->quantity),
            'unit' => $line->unit,
            'unitPrice' => $this->decimalStr($line->unit_price),
            'lineDiscountPercent' => $this->decimalStr($line->line_discount_percent),
            'taxRate' => $this->decimalStr($line->tax_rate),
            'lineTotal' => $this->decimalStr($line->line_total),
            'companyStockItemId' => $line->company_stock_item_id,
            'companyWarehouseId' => $line->company_warehouse_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapExpense(BusinessExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'companyId' => $expense->company_id,
            'status' => $expense->status->value,
            'internalNumber' => $expense->internal_number,
            'externalNumber' => $expense->external_number,
            'title' => $expense->title,
            'variableSymbol' => $expense->variable_symbol,
            'constantSymbol' => $expense->constant_symbol,
            'specificSymbol' => $expense->specific_symbol,
            'issueDate' => $this->dateStr($expense->issue_date),
            'deliveryDate' => $this->dateStr($expense->delivery_date),
            'dueDate' => $this->dateStr($expense->due_date),
            'paidAt' => $this->datetimeStr($expense->paid_at),
            'cancelledAt' => $this->datetimeStr($expense->cancelled_at ?? null),
            'total' => $this->decimalStr($expense->total),
            'currency' => $expense->currency,
            'internalNote' => $expense->internal_note,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapExpenseAttachment(BusinessExpenseAttachment $attachment): ?array
    {
        $payload = $this->readAttachmentBase64($attachment->disk, $attachment->path, $attachment->original_filename);
        if ($payload === null) {
            return null;
        }

        return [
            'id' => $attachment->id,
            'expenseId' => $attachment->business_expense_id,
            'originalFilename' => $attachment->original_filename,
            'mimeType' => $attachment->mime,
            'sizeBytes' => $attachment->size_bytes !== null ? (string) $attachment->size_bytes : null,
            'contentBase64' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapLegacyExpenseAttachment(BusinessExpense $expense): ?array
    {
        if (! $expense->attachment_path) {
            return null;
        }

        $disk = $expense->attachment_disk ?: 'local';
        $payload = $this->readAttachmentBase64($disk, $expense->attachment_path, $expense->original_filename);
        if ($payload === null) {
            return null;
        }

        return [
            'id' => (string) Str::uuid(),
            'expenseId' => $expense->id,
            'originalFilename' => $expense->original_filename,
            'mimeType' => $expense->attachment_mime,
            'sizeBytes' => null,
            'contentBase64' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecurringProfile(BusinessRecurringProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'companyId' => $profile->company_id,
            'contactId' => $profile->company_contact_id,
            'storeId' => $profile->store_id,
            'documentType' => $profile->document_type,
            'isActive' => $this->boolToSqlite($profile->is_active),
            'recurrenceInterval' => $profile->recurrence_interval->value,
            'firstIssueDate' => $this->dateStr($profile->first_issue_date),
            'nextIssueDate' => $this->dateStr($profile->next_issue_date),
            'endsAt' => $this->dateStr($profile->ends_at),
            'repeatIndefinitely' => $this->boolToSqlite($profile->repeat_indefinitely),
            'issueLastDayOfMonth' => $this->boolToSqlite($profile->issue_last_day_of_month),
            'title' => $profile->title,
            'variableSymbol' => $profile->variable_symbol,
            'constantSymbol' => $profile->constant_symbol,
            'specificSymbol' => $profile->specific_symbol,
            'paymentTermsDays' => $profile->payment_terms_days !== null
                ? (string) $profile->payment_terms_days
                : null,
            'deliveryDateMode' => $profile->delivery_date_mode,
            'currency' => $profile->currency,
            'discountPercent' => $this->decimalStr($profile->discount_percent),
            'subtotal' => $this->decimalStr($profile->subtotal),
            'taxTotal' => $this->decimalStr($profile->tax_total),
            'total' => $this->decimalStr($profile->total),
            'noteAboveLines' => $profile->note_above_lines,
            'noteFooter' => $profile->note_footer,
            'internalNote' => $profile->internal_note,
            'pdfLocale' => $profile->pdf_locale,
            'pdfShowSignature' => $this->boolToSqlite($profile->pdf_show_signature),
            'pdfShowPaymentInfo' => $this->boolToSqlite($profile->pdf_show_payment_info),
            'paymentBtcEnabled' => $this->boolToSqlite($profile->payment_btc_enabled),
            'paymentBankEnabled' => $this->boolToSqlite($profile->payment_bank_enabled),
            'sendEmailAfterIssue' => $this->boolToSqlite($profile->send_email_after_issue),
            'emailBcc' => $profile->email_bcc,
            'tagsJson' => $this->jsonOrNull($profile->tags ?? []),
            'lastGeneratedDocumentId' => $profile->last_generated_document_id,
            'lastGeneratedAt' => $this->datetimeStr($profile->last_generated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecurringProfileLine(BusinessRecurringProfileLine $line): array
    {
        return [
            'id' => (string) Str::uuid(),
            'recurringProfileId' => $line->business_recurring_profile_id,
            'sortOrder' => (string) $line->sort_order,
            'name' => $line->name,
            'description' => $line->description,
            'quantity' => $this->decimalStr($line->quantity),
            'unit' => $line->unit,
            'unitPrice' => $this->decimalStr($line->unit_price),
            'lineDiscountPercent' => $this->decimalStr($line->line_discount_percent),
            'taxRate' => $this->decimalStr($line->tax_rate),
            'lineTotal' => $this->decimalStr($line->line_total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWarehouse(CompanyWarehouse $warehouse): array
    {
        return [
            'id' => $warehouse->id,
            'companyId' => $warehouse->company_id,
            'name' => $warehouse->name,
            'type' => $warehouse->type->value,
            'deductOnIssue' => $this->boolToSqlite($warehouse->deduct_on_issue),
            'isDefault' => $this->boolToSqlite($warehouse->is_default),
            'isActive' => $this->boolToSqlite($warehouse->is_active),
            'companyContactId' => $warehouse->company_contact_id,
            'street' => $warehouse->street,
            'city' => $warehouse->city,
            'postalCode' => $warehouse->postal_code,
            'country' => $warehouse->country ? $this->countryCodeOrNull($warehouse->country) : null,
            'notes' => $warehouse->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStockItem(CompanyStockItem $item): array
    {
        return [
            'id' => $item->id,
            'companyId' => $item->company_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'description' => $item->description,
            'unit' => $item->unit,
            'trackInventory' => $this->boolToSqlite($item->track_inventory),
            'purchaseUnitPrice' => $this->decimalStr($item->purchase_unit_price),
            'purchaseCurrency' => $item->purchase_currency,
            'saleUnitPrice' => $this->decimalStr($item->sale_unit_price),
            'internalNote' => $item->internal_note,
            'excludeFromSuggester' => $this->boolToSqlite($item->exclude_from_suggester),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapStockBalance(CompanyStockBalance $balance): ?array
    {
        $companyId = $balance->warehouse?->company_id;
        if (! $companyId) {
            $this->warnings[] = "stock_balance_missing_company:{$balance->id}";

            return null;
        }

        return [
            'id' => $balance->id,
            'companyId' => $companyId,
            'companyWarehouseId' => $balance->company_warehouse_id,
            'companyStockItemId' => $balance->company_stock_item_id,
            'quantityOnHand' => $this->decimalStr($balance->quantity_on_hand),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStockMovement(CompanyStockItemMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'companyId' => $movement->company_id,
            'companyStockItemId' => $movement->company_stock_item_id,
            'companyWarehouseId' => $movement->company_warehouse_id,
            'quantityAfter' => $this->decimalStr($movement->quantity_after),
            'quantityDelta' => $this->decimalStr($movement->quantity_delta),
            'purchaseUnitPrice' => $this->decimalStr($movement->purchase_unit_price),
            'saleUnitPrice' => $this->decimalStr($movement->sale_unit_price),
            'note' => $movement->note,
            'source' => $movement->source->value,
            'businessDocumentId' => $movement->business_document_id,
            'documentNumber' => $movement->document_number,
            'documentType' => $movement->document_type,
            'movementAt' => $this->datetimeStr($movement->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBankImportBatch(BankImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'companyId' => $batch->company_id,
            'source' => $batch->source->value,
            'filename' => $batch->filename,
            'rowCount' => $batch->row_count !== null ? (string) $batch->row_count : null,
            'importedCount' => $batch->imported_count !== null ? (string) $batch->imported_count : null,
            'skippedDuplicates' => $batch->skipped_duplicates !== null ? (string) $batch->skipped_duplicates : null,
            'autoMatchedCount' => $batch->auto_matched_count !== null ? (string) $batch->auto_matched_count : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBankTransaction(BankTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'companyId' => $transaction->company_id,
            'bankImportBatchId' => $transaction->bank_import_batch_id,
            'bookedAt' => $this->datetimeStr($transaction->booked_at),
            'amount' => $this->decimalStr($transaction->amount),
            'currency' => $transaction->currency,
            'direction' => $transaction->direction->value,
            'matchStatus' => $transaction->match_status->value,
            'businessExpenseId' => $transaction->business_expense_id,
            'variableSymbol' => $transaction->variable_symbol,
            'constantSymbol' => $transaction->constant_symbol,
            'specificSymbol' => $transaction->specific_symbol,
            'counterpartyName' => $transaction->counterparty_name,
            'counterpartyIban' => $transaction->counterparty_iban,
            'reference' => $transaction->reference,
            'bankTransactionId' => $transaction->bank_transaction_id,
            'dedupeHash' => $transaction->dedupe_hash,
            'source' => $transaction->source->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBankTransactionMatch(BankTransactionMatch $match): array
    {
        return [
            'id' => $match->id,
            'bankTransactionId' => $match->bank_transaction_id,
            'businessDocumentId' => $match->business_document_id,
            'matchedAmount' => $this->decimalStr($match->matched_amount),
            'matchType' => $match->match_type->value,
            'matchedAt' => $this->datetimeStr($match->matched_at),
        ];
    }

    private function brandingDataUrl(?string $path, string $kind, string $label): ?string
    {
        if (! $path) {
            return null;
        }

        $dataUrl = $this->brandingService->imageDataUri($path);
        if (! $dataUrl) {
            $this->warnings[] = "branding_unreadable:{$kind}:{$label}";

            return null;
        }

        if (strlen($dataUrl) > self::MAX_IMAGE_DATA_URL_CHARS) {
            $this->warnings[] = "branding_too_large:{$kind}:{$label}";

            return null;
        }

        return $dataUrl;
    }

    private function readAttachmentBase64(?string $disk, ?string $path, ?string $label): ?string
    {
        if (! $disk || ! $path) {
            return null;
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                $this->warnings[] = 'attachment_missing:'.($label ?: $path);

                return null;
            }

            $binary = Storage::disk($disk)->get($path);
            if (strlen($binary) > self::MAX_ATTACHMENT_BYTES) {
                $this->warnings[] = 'attachment_too_large:'.($label ?: $path);

                return null;
            }

            return base64_encode($binary);
        } catch (Throwable) {
            $this->warnings[] = 'attachment_read_failed:'.($label ?: $path);

            return null;
        }
    }

    private function boolToSqlite(?bool $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    private function dateStr(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }

    private function datetimeStr(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }

    private function decimalStr(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     */
    private function jsonOrNull(array $value): ?string
    {
        if ($value === []) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    private function truncateJsonBlob(?string $json, int $maxLen = 4000): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        if (strlen($json) <= $maxLen) {
            return $json;
        }

        return substr($json, 0, $maxLen);
    }

    private function nonEmptyTitle(?string $title, ?string $number, string $documentType): string
    {
        foreach ([$title, $number, $documentType, 'Document'] as $candidate) {
            $text = trim((string) ($candidate ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return 'Document';
    }

    private function countryCodeOrNull(?string $country): ?string
    {
        $text = trim((string) ($country ?? ''));
        if ($text === '') {
            return null;
        }

        if (strlen($text) === 2) {
            return strtoupper($text);
        }

        return strtoupper(substr($text, 0, 2));
    }
}

<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\Canonical\CanonicalInvoice;
use App\Support\Invoicing\Canonical\CanonicalInvoiceLine;
use App\Support\Invoicing\Canonical\CanonicalTaxBreakdownRow;
use App\Support\Invoicing\EuStructuredDocumentExport;
use XMLWriter;

/**
 * Minimal UBL 2.1 / EN 16931 invoice export from the canonical invoice snapshot.
 */
class BusinessDocumentUblService
{
    public function __construct(
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    public function supports(BusinessDocument $document): bool
    {
        return EuStructuredDocumentExport::supports($document);
    }

    public function xml(BusinessDocument $document): string
    {
        $canonical = $this->canonicalBuilder->fromDocument($document);
        $document = $canonical->document ?? $document;

        $isCreditNote = $document->type === BusinessDocumentType::CreditNote;

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');

        $rootNs = $isCreditNote
            ? 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2'
            : 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $rootLocal = $isCreditNote ? 'CreditNote' : 'Invoice';

        $writer->startElementNs(null, $rootLocal, $rootNs);
        $writer->writeAttributeNs('xmlns', 'cac', null, 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $writer->writeAttributeNs('xmlns', 'cbc', null, 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->element($writer, 'cbc', 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
        $this->element($writer, 'cbc', 'ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $this->element($writer, 'cbc', 'ID', (string) $document->number);
        $this->element($writer, 'cbc', 'IssueDate', $document->issue_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
        $this->element($writer, 'cbc', 'DueDate', $document->due_date?->format('Y-m-d'));
        $this->element($writer, 'cbc', 'DocumentCurrencyCode', $canonical->currency);
        $this->element($writer, 'cbc', 'BuyerReference', $document->variable_symbol);
        if ($isCreditNote) {
            $this->element($writer, 'cbc', 'CreditNoteTypeCode', $this->invoiceTypeCode($document->type));
        } else {
            $this->element($writer, 'cbc', 'InvoiceTypeCode', $this->invoiceTypeCode($document->type));
        }

        if ($document->note_footer) {
            $this->element($writer, 'cbc', 'Note', $document->note_footer);
        }

        $this->party($writer, 'AccountingSupplierParty', $canonical->company);
        if ($canonical->contact) {
            $this->party($writer, 'AccountingCustomerParty', $canonical->contact, isCustomer: true);
        }

        $this->taxTotal($writer, $canonical);
        $this->legalMonetaryTotal($writer, $canonical);

        foreach ($canonical->lines as $index => $line) {
            $this->invoiceLine($writer, $line, $index + 1, $canonical, $isCreditNote);
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    protected function invoiceTypeCode(BusinessDocumentType $type): string
    {
        return match ($type) {
            BusinessDocumentType::CreditNote => '381',
            BusinessDocumentType::Proforma => '325',
            default => '380',
        };
    }

    protected function taxTotal(XMLWriter $writer, CanonicalInvoice $canonical): void
    {
        $writer->startElementNs('cac', 'TaxTotal', null);
        $this->amountElement($writer, 'cbc', 'TaxAmount', $canonical->taxTotal, $canonical->currency);

        foreach ($canonical->taxBreakdown as $row) {
            $this->taxSubtotal($writer, $row, $canonical->currency);
        }

        if ($canonical->taxBreakdown === [] && (float) $canonical->taxTotal <= 0) {
            $this->taxSubtotalZero($writer, $canonical);
        }

        $writer->endElement();
    }

    protected function taxSubtotal(XMLWriter $writer, CanonicalTaxBreakdownRow $row, string $currency): void
    {
        $writer->startElementNs('cac', 'TaxSubtotal', null);
        $this->amountElement($writer, 'cbc', 'TaxableAmount', $row->taxableAmount, $currency);
        $this->amountElement($writer, 'cbc', 'TaxAmount', $row->taxAmount, $currency);
        $writer->startElementNs('cac', 'TaxCategory', null);
        $this->element($writer, 'cbc', 'ID', 'S');
        $this->element($writer, 'cbc', 'Percent', $this->formatRate($row->ratePercent));
        $writer->startElementNs('cac', 'TaxScheme', null);
        $this->element($writer, 'cbc', 'ID', 'VAT');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
    }

    protected function taxSubtotalZero(XMLWriter $writer, CanonicalInvoice $canonical): void
    {
        $writer->startElementNs('cac', 'TaxSubtotal', null);
        $this->amountElement($writer, 'cbc', 'TaxableAmount', $canonical->subtotal, $canonical->currency);
        $this->amountElement($writer, 'cbc', 'TaxAmount', '0.00', $canonical->currency);
        $writer->startElementNs('cac', 'TaxCategory', null);
        $this->element($writer, 'cbc', 'ID', 'Z');
        $this->element($writer, 'cbc', 'Percent', '0');
        $writer->startElementNs('cac', 'TaxScheme', null);
        $this->element($writer, 'cbc', 'ID', 'VAT');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
    }

    protected function legalMonetaryTotal(XMLWriter $writer, CanonicalInvoice $canonical): void
    {
        $writer->startElementNs('cac', 'LegalMonetaryTotal', null);
        $this->amountElement($writer, 'cbc', 'LineExtensionAmount', $canonical->subtotal, $canonical->currency);
        $this->amountElement($writer, 'cbc', 'TaxExclusiveAmount', $canonical->subtotal, $canonical->currency);
        $this->amountElement($writer, 'cbc', 'TaxInclusiveAmount', $canonical->total, $canonical->currency);
        $this->amountElement($writer, 'cbc', 'PayableAmount', $canonical->amountDue, $canonical->currency);
        $writer->endElement();
    }

    protected function invoiceLine(
        XMLWriter $writer,
        CanonicalInvoiceLine $line,
        int $lineId,
        CanonicalInvoice $canonical,
        bool $isCreditNote,
    ): void {
        $tag = $isCreditNote ? 'CreditNoteLine' : 'InvoiceLine';

        $writer->startElementNs('cac', $tag, null);
        $this->element($writer, 'cbc', 'ID', (string) $lineId);
        $this->quantityElement($writer, $line, $isCreditNote);
        $this->amountElement($writer, 'cbc', 'LineExtensionAmount', $line->netAmount, $canonical->currency);

        $writer->startElementNs('cac', 'Item', null);
        $this->element($writer, 'cbc', 'Name', $line->name);
        if ($line->description) {
            $this->element($writer, 'cbc', 'Description', $line->description);
        }
        $writer->startElementNs('cac', 'ClassifiedTaxCategory', null);
        $this->element($writer, 'cbc', 'ID', (float) $line->taxRate > 0 ? 'S' : 'Z');
        $this->element($writer, 'cbc', 'Percent', $this->formatRate($line->taxRate));
        $writer->startElementNs('cac', 'TaxScheme', null);
        $this->element($writer, 'cbc', 'ID', 'VAT');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        $writer->startElementNs('cac', 'Price', null);
        $this->amountElement($writer, 'cbc', 'PriceAmount', $this->formatMoney($line->unitPrice), $canonical->currency);
        $writer->endElement();

        $writer->endElement();
    }

    protected function party(XMLWriter $writer, string $wrapper, Company|CompanyContact $entity, bool $isCustomer = false): void
    {
        $writer->startElementNs('cac', $wrapper, null);
        $writer->startElementNs('cac', 'Party', null);

        $endpointId = $entity->registration_number ?? $entity->tax_id ?? null;
        if ($endpointId) {
            $writer->startElementNs('cbc', 'EndpointID', null);
            $writer->writeAttribute('schemeID', '9938');
            $writer->text($endpointId);
            $writer->endElement();
        }

        $writer->startElementNs('cac', 'PartyName', null);
        $this->element($writer, 'cbc', 'Name', $entity->name ?? ($isCustomer ? 'Customer' : 'Supplier'));
        $writer->endElement();

        $writer->startElementNs('cac', 'PostalAddress', null);
        $this->element($writer, 'cbc', 'StreetName', $entity->street);
        $this->element($writer, 'cbc', 'CityName', $entity->city);
        $this->element($writer, 'cbc', 'PostalZone', $entity->postal_code);
        $writer->startElementNs('cac', 'Country', null);
        $this->element($writer, 'cbc', 'IdentificationCode', $this->countryCode($entity, $isCustomer));
        $writer->endElement();
        $writer->endElement();

        $vat = $entity instanceof Company ? $entity->vat_number : $entity->vat_id;
        if ($vat) {
            $writer->startElementNs('cac', 'PartyTaxScheme', null);
            $this->element($writer, 'cbc', 'CompanyID', $vat);
            $writer->startElementNs('cac', 'TaxScheme', null);
            $this->element($writer, 'cbc', 'ID', 'VAT');
            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
    }

    protected function countryCode(Company|CompanyContact $entity, bool $isCustomer): string
    {
        $raw = strtoupper(trim((string) ($entity->country ?? '')));
        if (strlen($raw) === 2) {
            return $raw;
        }

        if ($entity instanceof Company) {
            return match ($entity->jurisdiction) {
                CompanyJurisdiction::EuSk => 'SK',
                CompanyJurisdiction::EuCz => 'CZ',
                default => 'EU',
            };
        }

        return 'SK';
    }

    protected function quantityElement(XMLWriter $writer, CanonicalInvoiceLine $line, bool $isCreditNote = false): void
    {
        $tag = $isCreditNote ? 'CreditedQuantity' : 'InvoicedQuantity';
        $writer->startElementNs('cbc', $tag, null);
        if ($line->unit) {
            $writer->writeAttribute('unitCode', $line->unit);
        }
        $writer->text($this->formatQty($line->quantity));
        $writer->endElement();
    }

    protected function amountElement(XMLWriter $writer, string $prefix, string $name, string|float $amount, string $currency): void
    {
        $writer->startElementNs($prefix, $name, null);
        $writer->writeAttribute('currencyID', $currency);
        $writer->text(is_string($amount) ? $amount : $this->formatMoney($amount));
        $writer->endElement();
    }

    protected function element(XMLWriter $writer, string $prefix, string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $writer->startElementNs($prefix, $name, null);
        $writer->text($value);
        $writer->endElement();
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    protected function formatQty(float $qty): string
    {
        $s = number_format($qty, 4, '.', '');

        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    protected function formatRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }
}

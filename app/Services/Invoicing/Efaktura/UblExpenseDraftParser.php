<?php

namespace App\Services\Invoicing\Efaktura;

use App\Services\Compliance\XmlParser;
use App\Support\Invoicing\SupplierPaymentIban;
use Carbon\Carbon;

class UblExpenseDraftParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $ublXml): array
    {
        $root = XmlParser::loadString($ublXml, 'UBL expense');
        $root->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $root->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $invoiceNumber = $this->xpathString($root, './cbc:ID');
        $issueDate = $this->parseDate($this->xpathString($root, './cbc:IssueDate'));
        $dueDate = $this->parseDate($this->xpathString($root, './cbc:DueDate'));
        $currency = $this->xpathString($root, './cbc:DocumentCurrencyCode') ?: 'EUR';
        $supplierName = $this->xpathString($root, './cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name');
        $total = $this->parseAmount(
            $this->xpathString($root, './cac:LegalMonetaryTotal/cbc:PayableAmount')
            ?: $this->xpathString($root, './cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount')
            ?: '0',
        );
        $variableSymbol = $this->paymentVariableSymbol($root);
        $creditNote = $root->getName() === 'CreditNote';
        if ($creditNote) {
            $total = bcsub('0', $total, 2);
        }

        return [
            'document_type' => $creditNote ? 'credit_note' : 'invoice',
            'external_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
            'title' => $supplierName !== '' ? $supplierName : 'Prijatá e-faktúra',
            'variable_symbol' => $variableSymbol,
            'issue_date' => $issueDate ?? now()->toDateString(),
            'delivery_date' => $issueDate,
            'due_date' => $dueDate,
            'total' => $total,
            'currency' => $currency,
            'internal_note' => $this->supplierPaymentNote($root, $supplierName, $creditNote, $variableSymbol),
        ];
    }

    protected function supplierPaymentNote(\SimpleXMLElement $root, string $supplier, bool $creditNote, ?string $variableSymbol): string
    {
        $parts = [$creditNote ? 'Dobropis prijatý z Peppol (SAPI-SK).' : 'Importované z Peppol (SAPI-SK).'];
        if ($supplier === '' || preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $supplier)) {
            return $parts[0];
        }
        $parts[] = 'Supplier: '.$supplier;
        // An explicit payee may be a factor rather than the supplier. This
        // expense model has no separate payee identity, so do not pair its
        // payment account with the supplier name. Credit notes never pay out.
        $supplierNames = $root->xpath('./cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name') ?: [];
        if ($creditNote || $variableSymbol === null || ($root->xpath('./cac:PayeeParty') ?: []) !== []
            || count($supplierNames) !== 1 || trim((string) $supplierNames[0]) !== $supplier
            || ! $this->hasUnambiguousPaymentTotal($root)) {
            return implode("\n\n", $parts);
        }

        $accounts = [];
        $means = $root->xpath('./cac:PaymentMeans') ?: [];
        if (count($means) !== 1) {
            return implode("\n\n", $parts);
        }
        foreach ($means as $payment) {
            $payment->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $payment->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            if (! in_array($this->xpathString($payment, './cbc:PaymentMeansCode'), ['30', '58'], true)) {
                return implode("\n\n", $parts);
            }
            $nodes = $payment->xpath('./cac:PayeeFinancialAccount/cbc:ID') ?: [];
            $accountNames = $payment->xpath('./cac:PayeeFinancialAccount/cbc:Name') ?: [];
            if (count($nodes) !== 1) {
                return implode("\n\n", $parts);
            }
            if (count($accountNames) > 1
                || ($accountNames !== [] && trim((string) $accountNames[0]) !== $supplier)) {
                return implode("\n\n", $parts);
            }
            $iban = SupplierPaymentIban::normalize((string) $nodes[0]);
            if ($iban === null) {
                return implode("\n\n", $parts);
            }
            $accounts[$iban] = true;
        }
        if (count($accounts) === 1) {
            $parts[] = 'Supplier bank account: '.array_key_first($accounts);
        }

        return implode("\n\n", $parts);
    }

    protected function hasUnambiguousPaymentTotal(\SimpleXMLElement $root): bool
    {
        $currencies = $root->xpath('./cbc:DocumentCurrencyCode') ?: [];
        $totals = $root->xpath('./cac:LegalMonetaryTotal') ?: [];
        $payables = $root->xpath('./cac:LegalMonetaryTotal/cbc:PayableAmount') ?: [];
        $prepayments = $root->xpath('./cac:LegalMonetaryTotal/cbc:PrepaidAmount') ?: [];
        if (count($currencies) !== 1 || count($totals) !== 1 || count($payables) !== 1 || count($prepayments) > 1) {
            return false;
        }
        $currency = trim((string) $currencies[0]);
        $payable = str_replace(',', '.', trim((string) $payables[0]));
        $payableCurrency = trim((string) $payables[0]['currencyID']);
        if (! preg_match('/^[A-Z]{3}$/D', $currency)
            || ! preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $payable) || bccomp($payable, '0', 2) <= 0
            || (isset($payables[0]['currencyID']) && $payableCurrency !== $currency)) {
            return false;
        }
        if ($prepayments !== []) {
            $prepaid = trim((string) $prepayments[0]);
            $prepaidCurrency = trim((string) $prepayments[0]['currencyID']);
            if (! preg_match('/^0+(?:[.,]0+)?$/D', $prepaid)
                || (isset($prepayments[0]['currencyID']) && $prepaidCurrency !== $currency)) {
                return false;
            }
        }

        return true;
    }

    protected function paymentVariableSymbol(\SimpleXMLElement $root): ?string
    {
        $means = $root->xpath('./cac:PaymentMeans') ?: [];
        if (count($means) !== 1) {
            return null;
        }
        $payment = $means[0];
        $payment->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $codes = $payment->xpath('./cbc:PaymentMeansCode') ?: [];
        $references = $payment->xpath('./cbc:PaymentID') ?: [];
        if (count($codes) !== 1 || ! in_array(trim((string) $codes[0]), ['30', '58'], true)
            || count($references) !== 1) {
            return null;
        }

        // Peppol BT-83 is the seller's reconciliation reference. BuyerReference
        // (BT-10) is routing data, never a payment instruction. Only explicit
        // numeric VS or /VS notation is supported; additional symbols are not
        // silently dropped and arbitrary reference digits are not concatenated.
        $reference = trim((string) $references[0]);

        return preg_match('/^(?:\/VS)?([0-9]{1,10})$/D', $reference, $matches) === 1 ? $matches[1] : null;
    }

    protected function xpathString(\SimpleXMLElement $root, string $query): string
    {
        $nodes = $root->xpath($query);
        if ($nodes === false || $nodes === []) {
            return '';
        }

        return trim((string) $nodes[0]);
    }

    protected function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseAmount(string $value): string
    {
        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            return '0.00';
        }

        return bcadd($normalized, '0', 2);
    }
}

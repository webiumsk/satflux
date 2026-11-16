<?php

namespace App\Services\Invoicing\Efaktura;

use App\Services\Compliance\XmlParser;
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

        $invoiceNumber = $this->xpathString($root, '//cbc:ID');
        $issueDate = $this->parseDate($this->xpathString($root, '//cbc:IssueDate'));
        $dueDate = $this->parseDate($this->xpathString($root, '//cbc:DueDate'));
        $currency = $this->xpathString($root, '//cbc:DocumentCurrencyCode') ?: 'EUR';
        $supplierName = $this->xpathString($root, '//cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name');
        $total = $this->parseAmount(
            $this->xpathString($root, '//cac:LegalMonetaryTotal/cbc:PayableAmount')
            ?: $this->xpathString($root, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount')
            ?: '0',
        );
        $paymentId = $this->xpathString($root, '//cbc:BuyerReference');
        $creditNote = $root->getName() === 'CreditNote';
        if ($creditNote) {
            $total = bcsub('0', $total, 2);
        }

        return [
            'document_type' => $creditNote ? 'credit_note' : 'invoice',
            'external_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
            'title' => $supplierName !== '' ? $supplierName : 'Prijatá e-faktúra',
            'variable_symbol' => $paymentId !== '' ? preg_replace('/\D/', '', $paymentId) : null,
            'issue_date' => $issueDate ?? now()->toDateString(),
            'delivery_date' => $issueDate,
            'due_date' => $dueDate,
            'total' => $total,
            'currency' => $currency,
            'internal_note' => $this->supplierPaymentNote($root, $supplierName, $creditNote),
        ];
    }

    protected function supplierPaymentNote(\SimpleXMLElement $root, string $supplier, bool $creditNote): string
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
        $prepaid = $this->xpathString($root, './cac:LegalMonetaryTotal/cbc:PrepaidAmount');
        $payable = str_replace(',', '.', $this->xpathString($root, './cac:LegalMonetaryTotal/cbc:PayableAmount'));
        if ($creditNote || ($root->xpath('./cac:PayeeParty') ?: []) !== []
            || count($supplierNames) !== 1 || trim((string) $supplierNames[0]) !== $supplier
            || ! preg_match('/^[0-9]+(\.[0-9]+)?$/D', $payable) || bccomp($payable, '0', 2) <= 0
            || ($prepaid !== '' && ! preg_match('/^0+([.,]0+)?$/D', $prepaid))) {
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
            $iban = $this->paymentIban((string) $nodes[0]);
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

    protected function paymentIban(string $value): ?string
    {
        $iban = strtoupper(str_replace(' ', '', $value));
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)) {
            return null;
        }
        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1 ? $iban : null;
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

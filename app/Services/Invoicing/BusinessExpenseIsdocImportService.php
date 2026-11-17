<?php

namespace App\Services\Invoicing;

use Adawolfa\ISDOC\Manager;
use Adawolfa\ISDOC\ReaderException;
use Adawolfa\ISDOC\Schema\Invoice as IsdocInvoiceSchema;
use Adawolfa\ISDOC\Schema\Invoice\Details;
use Adawolfa\ISDOC\Schema\Invoice\DocumentType;
use Adawolfa\ISDOC\Schema\Invoice\Payment;
use Adawolfa\ISDOC\Schema\Invoice\PaymentMeansCode;
use App\Support\Invoicing\SupplierPaymentIban;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BusinessExpenseIsdocImportService
{
    public function hasIsdocInUpload(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return false;
        }

        return $this->hasIsdocAtPath($path, $file->getClientOriginalExtension());
    }

    public function hasIsdocAtPath(string $path, ?string $clientExtension = null): bool
    {
        try {
            $this->readInvoice($path, $clientExtension);

            return true;
        } catch (ReaderException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromUpload(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['Could not read the uploaded file.'],
            ]);
        }

        return $this->extractFromPath($path, $file->getClientOriginalExtension());
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromPath(string $path, ?string $clientExtension = null): array
    {
        try {
            $invoice = $this->readInvoice($path, $clientExtension);
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages([
                'file' => [$this->readerErrorMessage($exception)],
            ]);
        }

        return $this->mapInvoice($invoice);
    }

    protected function readInvoice(string $path, ?string $clientExtension = null): IsdocInvoiceSchema
    {
        $reader = Manager::create()->getReader();
        $format = $this->readerFormatForExtension($clientExtension);

        if ($format !== null) {
            return $reader->file($path, IsdocInvoiceSchema::class, $format);
        }

        return $reader->file($path);
    }

    protected function readerFormatForExtension(?string $extension): ?string
    {
        return match (strtolower((string) $extension)) {
            'pdf' => Manager::FORMAT_PDF,
            'isdocx' => Manager::FORMAT_ISDOCX,
            'isdoc', 'xml' => Manager::FORMAT_ISDOC,
            default => null,
        };
    }

    protected function readerErrorMessage(ReaderException $exception): string
    {
        if (str_contains($exception->getMessage(), 'No ISDOC data found in PDF')) {
            return 'No ISDOC data found in this PDF. Use a PDF with embedded ISDOC or upload .isdoc / XML.';
        }

        return 'Could not read ISDOC from this file. Use .isdoc, ISDOC XML, or PDF with embedded ISDOC.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapInvoice(IsdocInvoiceSchema $invoice): array
    {
        $details = $this->firstPaymentDetails($invoice);
        $monetary = $invoice->getLegalMonetaryTotal();
        $total = (float) $monetary->getPayableAmount();
        if ($total <= 0) {
            $total = (float) $monetary->getTaxInclusiveAmount();
        }

        $supplierName = $invoice->getAccountingSupplierParty()
            ->getParty()
            ->getPartyName()
            ->getName();

        $externalNumber = $invoice->getId();
        $means = $invoice->getPaymentMeans();
        $reference = trim($details?->getVariableSymbol() ?? '');
        $variableSymbol = $means !== null && count($means) === 1 && preg_match('/^[0-9]{1,10}$/D', $reference)
            ? $reference : null;

        return [
            'source' => 'isdoc',
            'title' => $supplierName !== '' ? $supplierName : null,
            'external_number' => $externalNumber,
            'variable_symbol' => $variableSymbol,
            'constant_symbol' => $details?->getConstantSymbol(),
            'specific_symbol' => $details?->getSpecificSymbol(),
            'issue_date' => $this->formatDate($invoice->getIssueDate()),
            'delivery_date' => $this->formatDate($invoice->getTaxPointDate() ?? $invoice->getIssueDate()),
            'due_date' => $details?->getPaymentDueDate()
                ? $this->formatDate($details->getPaymentDueDate())
                : null,
            'total' => round($total, 2),
            'currency' => strtoupper($invoice->getLocalCurrencyCode()),
            'internal_note' => $this->supplierPaymentNote($invoice, $supplierName, $variableSymbol),
        ];
    }

    protected function supplierPaymentNote(IsdocInvoiceSchema $invoice, string $supplier, ?string $variableSymbol): string
    {
        $parts = ['Imported from ISDOC.'];
        if ($supplier === '' || preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $supplier)) {
            return $parts[0];
        }
        $parts[] = 'Supplier: '.$supplier;
        // Preserve existing total mapping while withholding a payment QR for
        // credit notes or documents with deposits/claimed advances. The expense
        // model does not store a separate outstanding-balance ledger.
        $monetary = $invoice->getLegalMonetaryTotal();
        if ($variableSymbol === null || $invoice->getDocumentType() !== DocumentType::Invoice
            || bccomp($monetary->getPayableAmount(), '0', 2) <= 0
            || bccomp($monetary->getPaidDepositsAmount(), '0', 2) !== 0
            || bccomp($monetary->getAlreadyClaimedTaxInclusiveAmount(), '0', 2) !== 0) {
            return implode("\n\n", $parts);
        }

        $accounts = [];
        $means = $invoice->getPaymentMeans();
        if ($means === null || count($means) !== 1) {
            return implode("\n\n", $parts);
        }
        foreach ($means as $payment) {
            if (! in_array($payment->getPaymentMeansCode(), [PaymentMeansCode::CreditTransfer, PaymentMeansCode::MoneyTransferToAnAccount], true)
                || $payment->getPartialPayment() === true
                || bccomp($payment->getPaidAmount(), $monetary->getPayableAmount(), 2) !== 0) {
                return implode("\n\n", $parts);
            }
            $iban = SupplierPaymentIban::normalize($payment->getDetails()?->getIban() ?? '');
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

    protected function firstPaymentDetails(IsdocInvoiceSchema $invoice): ?Details
    {
        $means = $invoice->getPaymentMeans();
        if ($means === null) {
            return null;
        }

        /** @var Payment $payment */
        foreach ($means as $payment) {
            if ($payment->getDetails() !== null) {
                return $payment->getDetails();
            }
        }

        return null;
    }

    protected function formatDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}

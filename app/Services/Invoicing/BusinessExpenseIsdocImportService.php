<?php

namespace App\Services\Invoicing;

use Adawolfa\ISDOC\Manager;
use Adawolfa\ISDOC\ReaderException;
use Adawolfa\ISDOC\Schema\Invoice as IsdocInvoiceSchema;
use Adawolfa\ISDOC\Schema\Invoice\Details;
use Adawolfa\ISDOC\Schema\Invoice\Payment;
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

        try {
            $this->extractFromPath($path);

            return true;
        } catch (ValidationException) {
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

        return $this->extractFromPath($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromPath(string $path): array
    {
        try {
            $invoice = Manager::create()->getReader()->file($path);
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages([
                'file' => ['Could not read ISDOC from this file. Use .isdoc, ISDOC XML, or PDF with embedded ISDOC.'],
            ]);
        }

        return $this->mapInvoice($invoice);
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
        $variableSymbol = $details?->getVariableSymbol() ?? $externalNumber;

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
        ];
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

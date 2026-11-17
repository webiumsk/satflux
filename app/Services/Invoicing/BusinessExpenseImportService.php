<?php

namespace App\Services\Invoicing;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class BusinessExpenseImportService
{
    public function __construct(
        protected BusinessExpenseIsdocImportService $isdoc,
        protected BusinessExpensePdfTextImportService $pdfText,
    ) {}

    /** @return array{has_isdoc: bool, extraction_source: ?string} */
    public function detectFromUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'isdoc', 'xml'], true)) {
            return ['has_isdoc' => false, 'extraction_source' => null];
        }
        if ($extension === 'pdf') {
            $inspection = $this->pdfText->inspectFromPath($this->path($file));
            if (! $inspection['has_embedded_files']) {
                return [
                    'has_isdoc' => false,
                    'extraction_source' => $this->pdfText->hasExtractableFields($inspection['text']) ? 'pdf_text' : null,
                ];
            }
        }
        try {
            $hasIsdoc = $this->isdoc->hasIsdocInUpload($file);
        } catch (Throwable) {
            $this->invalidStructuredData();
        }

        return ['has_isdoc' => $hasIsdoc, 'extraction_source' => $hasIsdoc ? 'isdoc' : null];
    }

    /** @return array<string, mixed> */
    public function extractFromUpload(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'pdf') {
            $inspection = $this->pdfText->inspectFromPath($this->path($file));
            if (! $inspection['has_embedded_files']) {
                return $this->pdfText->extractFromText($inspection['text']);
            }
        }

        try {
            return $this->isdoc->extractFromUpload($file);
        } catch (Throwable) {
            $this->invalidStructuredData();
        }
    }

    private function path(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['Could not read the uploaded file.']]);
        }

        return $path;
    }

    private function invalidStructuredData(): never
    {
        throw ValidationException::withMessages([
            'file' => ['Could not read structured invoice data. Use valid ISDOC XML or another PDF.'],
        ]);
    }
}

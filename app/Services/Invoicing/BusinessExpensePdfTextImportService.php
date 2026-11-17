<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\SupplierPaymentIban;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Parser;
use Throwable;

class BusinessExpensePdfTextImportService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_PAGES = 25;

    private const MAX_OBJECTS = 20000;

    private const MAX_TEXT_BYTES = 200000;

    private const DECODE_BYTES = 16 * 1024 * 1024;

    private const INVOICE_NUMBER_LABELS = '[čc][íi]slo\h+fakt[úu]ry|fakt[úu]ra\h+[čc](?:[íi]slo)?\.?|invoice\h+(?:number|no\.?|#)';

    private const CREDIT_NUMBER_LABELS = 'dobropis\h+[čc](?:[íi]slo)?\.?|credit\h+note\h+(?:number|no\.?|#)';

    private const NUMBER_LABELS = self::INVOICE_NUMBER_LABELS.'|'.self::CREDIT_NUMBER_LABELS;

    private const ISSUE_DATE_LABELS = 'd[áa]tum\h+(?:vystavenia|vyhotovenia)|datum\h+vystaven[íi]|invoice\h+date|issue\h+date|date\h+of\h+issue';

    private const DUE_DATE_LABELS = 'd[áa]tum\h+splatnosti|datum\h+splatnosti|due\h+date|payment\h+due\h+date';

    private const TOTAL_LABELS = 'celkom\h+k\h+[úu]hrade|celkem\h+k\h+[úu]hrad[ěe]|celkov[áa]\h+suma|amount\h+due|total\h+due|grand\h+total|invoice\h+total';

    /**
     * Internal inspection only. The orchestration service never exposes raw text.
     * Any embedded file keeps the document on the existing structured reader path.
     *
     * @return array{has_embedded_files: bool, text: string}
     */
    public function inspectFromPath(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > self::MAX_BYTES) {
            $this->invalidPdf();
        }

        try {
            $config = new Config;
            $config->setDecodeMemoryLimit(self::DECODE_BYTES);
            $config->setRetainImageContent(false);
            $parser = new Parser(['ignore_filter_decoding_errors' => false], $config);
            $pdf = $parser->parseFile($path);
            if (count($pdf->getObjects()) > self::MAX_OBJECTS) {
                $this->invalidPdf();
            }
            if ($pdf->getObjectsByType('EmbeddedFile') !== []) {
                return ['has_embedded_files' => true, 'text' => ''];
            }
            foreach ($pdf->getObjects() as $object) {
                $header = $object->getHeader();
                if ($header !== null && $this->hasEmbeddedFileReference($header)) {
                    return ['has_embedded_files' => true, 'text' => ''];
                }
            }

            $pages = $pdf->getPages();
            if (count($pages) > self::MAX_PAGES) {
                $this->invalidPdf();
            }
            $text = '';
            foreach ($pages as $page) {
                // Only actual PDF pages, not visible control characters, delimit pages.
                $part = str_replace("\f", ' ', $page->getText());
                if (strlen($text) + strlen($part) + 3 > self::MAX_TEXT_BYTES) {
                    $this->invalidPdf();
                }
                $text .= $part."\n\f\n";
            }

            return ['has_embedded_files' => false, 'text' => $text];
        } catch (Throwable) {
            // Parser messages can contain document text or local paths.
            $this->invalidPdf();
        }
    }

    private function hasEmbeddedFileReference(Header|ElementArray $container): bool
    {
        // /EF also identifies attachments whose streams omit optional /Type headers.
        if ($container instanceof Header && $container->has('EF')) {
            return true;
        }
        $elements = $container instanceof Header ? $container->getElements() : $container->getRawContent();
        foreach ($elements as $element) {
            // Referenced objects are inspected separately; do not follow cyclic PDF links.
            if (($element instanceof Header || $element instanceof ElementArray) && $this->hasEmbeddedFileReference($element)) {
                return true;
            }
        }

        return false;
    }

    public function hasExtractableFields(string $text): bool
    {
        return $this->fieldsFromText($text) !== [];
    }

    /** @return array<string, mixed> */
    public function extractFromText(string $text): array
    {
        $fields = $this->fieldsFromText($text);
        if ($fields === []) {
            throw ValidationException::withMessages([
                'file' => ['No supported invoice fields were found in this PDF. Image-only scans require OCR.'],
            ]);
        }

        $note = 'Imported from text PDF. Check extracted fields against the original document.';
        $paid = $this->values($text, '(?:uhraden[áaéeo]|uhrazen[áaéeo]|zaplaten[áaéeo]|zaplacen[áaéeo])(?:\h+(?:z[áa]loh[ay]|sum[ay]|[čc][áa]stk[ay]))?|paid\h+amount|amount\h+paid|prijat[áa]\h+z[áa]loha|paid\h+deposit|paid', true);
        if ($paid !== []) {
            $paidAmount = $this->unique($paid, fn (string $value) => $this->amount($value));
            $note .= "\nPayment amount: ".($paidAmount ?? 'unknown');
        }
        $paidDates = $this->values($text, 'd[áa]tum\h+[úu]hrady|datum\h+[úu]hrady|payment\h+date|date\h+paid', true);
        if ($paidDates !== []) {
            $note .= "\nPayment date: ".($this->unique($paidDates, fn (string $value) => $this->date($value)) ?? 'unknown');
        }

        return [
            'source' => 'pdf_text',
            'payment_review_required' => true,
            ...$fields,
            'internal_note' => $note,
        ];
    }

    /** @return array<string, mixed> */
    private function fieldsFromText(string $text): array
    {
        if (strlen($text) > self::MAX_TEXT_BYTES || ! mb_check_encoding($text, 'UTF-8')) {
            $this->invalidPdf();
        }
        $text = str_replace(["\r\n", "\r", "\xc2\xa0", "\xe2\x80\xaf"], ["\n", "\n", ' ', ' '], $text);
        $text = preg_replace('/\n?\f\n?/u', "\n\f\n", $text);
        if (! preg_match('/\b(?:fakt[úu]ra|faktury|invoice|credit\h+note|dobropis)\b/iu', $text)) {
            return [];
        }

        $fields = [];
        $number = $this->unique($this->values($text, self::NUMBER_LABELS), fn (string $value) => $this->invoiceNumber($value));
        if ($number !== null) {
            $fields['external_number'] = $number;
        }
        $symbol = $this->unique($this->values($text, 'variabiln[ýyíi]\h+symbol|variable\h+symbol|VS'),
            fn (string $value) => preg_match('/^\d{1,10}$/D', $value) ? $value : null);
        if ($symbol !== null) {
            $fields['variable_symbol'] = $symbol;
        }

        foreach ([
            'issue_date' => self::ISSUE_DATE_LABELS,
            'delivery_date' => 'd[áa]tum\h+dodania(?:\h+slu[žz]by)?|datum\h+(?:dod[áa]n[íi]|zdaniteln[ée]ho\h+pln[ěe]n[íi])|delivery\h+date|tax\h+point\h+date',
            'due_date' => self::DUE_DATE_LABELS,
        ] as $field => $labels) {
            $date = $this->unique($this->values($text, $labels), fn (string $value) => $this->date($value));
            if ($date !== null) {
                $fields[$field] = $date;
            }
        }

        $amount = $this->invoiceTotal($text, $number);
        if ($amount !== null && (float) $amount > 0) {
            $amount = match ($this->documentType($text)) {
                'invoice' => $amount,
                'credit_note' => '-'.$amount,
                default => null,
            };
        }
        if ($amount !== null) {
            $fields['total'] = (float) $amount;
        }
        preg_match_all('/\b(?:EUR|USD|GBP|CZK|CHF|PLN|HUF|SEK|NOK|DKK)\b|€/u', $text, $currencies);
        $currency = $this->unique($currencies[0], fn (string $value) => $value === '€' ? 'EUR' : $value);
        if ($currency !== null) {
            $fields['currency'] = $currency;
        }

        // A currency or payment account alone does not establish an invoice draft.
        if (! array_intersect_key($fields, array_flip(['external_number', 'issue_date', 'delivery_date', 'due_date', 'total']))) {
            return [];
        }
        $supplier = $this->supplier($text);
        if ($supplier !== null) {
            $fields['supplier_name'] = $supplier;
            $fields['title'] = $supplier;
        }
        // Accounts in the buyer's section are not supplier payment instructions.
        $ibans = $this->issuerIbans($this->supplierHeader($text));
        $iban = $this->unique($ibans, fn (string $value) => SupplierPaymentIban::normalize($value));
        if ($iban !== null) {
            $fields['supplier_iban'] = $iban;
        }

        return $fields;
    }

    private function documentType(string $text): ?string
    {
        $types = [];
        foreach ([
            'invoice' => [self::INVOICE_NUMBER_LABELS, 'fakt[úu]ra|invoice'],
            'credit_note' => [self::CREDIT_NUMBER_LABELS, 'dobropis|credit\h+note'],
        ] as $type => [$numberLabels, $heading]) {
            if ($this->values($text, $numberLabels, true) !== []
                || preg_match('/(?:^|\n)\h*(?:'.$heading.')\h*(?=\n|$)/iu', $text)) {
                $types[$type] = true;
            }
        }

        return count($types) === 1 ? (string) array_key_first($types) : null;
    }

    /** @return list<string> */
    private function issuerIbans(string $header): array
    {
        return $this->values($this->alignBankTable($header), 'IBAN');
    }

    private function alignBankTable(string $header): string
    {
        // Some PDF tables emit their three labels before their three values.
        // Realign only the complete bank-name/IBAN/BIC table, never arbitrary gaps.
        return (string) preg_replace_callback('/((?:^|\n)\h*(?:bankov[ée]\h+spojenie|bank\h+details)\h*\n)\h*IBAN\h*\n\h*(?:BIC\/SWIFT|BIC|SWIFT)\h*\n[^\n]+\n([^\n]+)\n\h*([A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)\h*(?=\n|$)/iu',
            static fn (array $matches): string => $matches[1].'IBAN: '.trim($matches[2])."\nBIC/SWIFT: ".$matches[3], $header);
    }

    private function invoiceNumber(string $value): ?string
    {
        return preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\/._-]{0,63}$/uD', $value) ? $value : null;
    }

    private function invoiceTotal(string $text, ?string $number): ?string
    {
        $amounts = [];
        foreach ($this->values($text, self::TOTAL_LABELS) as $value) {
            $amount = $this->amount($value);
            if ($amount === null) {
                return null;
            }
            $amounts[$amount] = true;
        }
        if (count($amounts) === 1) {
            return (string) array_key_first($amounts);
        }
        if ($number === null || ! str_contains($text, "\f") || $amounts === []) {
            return null;
        }

        $headers = [];
        foreach (explode("\f", $text) as $page) {
            $pageNumber = $this->unique($this->values($page, self::NUMBER_LABELS), fn (string $value) => $this->invoiceNumber($value));
            $issue = $this->unique($this->values($page, self::ISSUE_DATE_LABELS), fn (string $value) => $this->date($value));
            $due = $this->unique($this->values($page, self::DUE_DATE_LABELS), fn (string $value) => $this->date($value));
            if ($pageNumber !== null && $issue !== null && $due !== null) {
                $headers[] = ['number' => $pageNumber,
                    'total' => $this->unique($this->values($page, self::TOTAL_LABELS), fn (string $value) => $this->amount($value))];
            }
        }

        // An annex subtotal cannot replace the one coherent invoice summary.
        // More than one header page or any conflicting document identity stays unresolved.
        return count($headers) === 1 && $headers[0]['number'] === $number ? $headers[0]['total'] : null;
    }

    /** @return list<string> */
    private function values(string $text, string $labels, bool $lineOnly = false): array
    {
        $prefix = $lineOnly ? '(?:^|\n|\t)\h*' : '(?<![\p{L}\p{N}])';
        preg_match_all('/'.$prefix.'(?:'.$labels.')(?![\p{L}\p{N}])\h*:?[ \t]*(?:\n[ \t]*)?([^\n\r\t]+)/iu', $text, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * One invalid or conflicting candidate means the field remains unresolved.
     *
     * @param  list<string>  $values
     * @param  callable(string): ?string  $normalize
     */
    private function unique(array $values, callable $normalize): ?string
    {
        $result = [];
        foreach ($values as $value) {
            $normalized = $normalize($value);
            if ($normalized === null) {
                return null;
            }
            $result[$normalized] = true;
        }

        return count($result) === 1 ? (string) array_key_first($result) : null;
    }

    private function date(string $value): ?string
    {
        if (! preg_match('/^(?:\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.\d{4}|\d{1,2}\/\d{1,2}\/\d{4})$/D', trim($value))) {
            return null;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/D', trim($value), $parts)) {
            $dates = [];
            foreach ([[(int) $parts[1], (int) $parts[2]], [(int) $parts[2], (int) $parts[1]]] as [$month, $day]) {
                if (checkdate($month, $day, (int) $parts[3])) {
                    $dates[sprintf('%04d-%02d-%02d', (int) $parts[3], $month, $day)] = true;
                }
            }

            return count($dates) === 1 ? (string) array_key_first($dates) : null;
        }
        foreach (['Y-m-d', 'd.m.Y', 'j.n.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, trim($value));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function amount(string $value): ?string
    {
        $value = trim(preg_replace('/\h*(?:EUR|USD|GBP|CZK|CHF|PLN|HUF|SEK|NOK|DKK|€)$/u', '', trim($value)));
        if (! preg_match('/^[+-]?\h*\d[\d .,\x{00a0}\x{202f}]*$/uD', $value)) {
            return null;
        }
        $value = preg_replace('/^([+-])\h+/u', '$1', $value);
        if (preg_match('/\h/u', $value) && ! preg_match('/^[+-]?\d{1,3}(?:\h+\d{3})+(?:[.,]\d{1,2})?$/uD', $value)) {
            return null;
        }
        $value = preg_replace('/\h/u', '', $value);
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            if (! preg_match('/^[+-]?\d{1,3}(?:'.preg_quote($thousands, '/').'\d{3})+'.preg_quote($decimal, '/').'\d{1,2}$/D', $value)) {
                return null;
            }
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }
        if (! preg_match('/^([+-]?)(\d{1,12})(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            return null;
        }
        $integer = ltrim($parts[2], '0') ?: '0';
        $fraction = str_pad($parts[3] ?? '', 2, '0');
        $sign = $parts[1] === '-' && ($integer !== '0' || $fraction !== '00') ? '-' : '';

        return $sign.$integer.'.'.$fraction;
    }

    private function supplier(string $text): ?string
    {
        $header = $this->alignBankTable($this->supplierHeader($text));
        $labelled = $this->values($header, 'dod[áa]vate[ľl]|dodavatel|supplier|seller|vendor');
        if ($labelled !== []) {
            return $this->unique($labelled, fn (string $value) => $this->supplierName($value));
        }
        $candidates = [];
        foreach (explode("\n", $header) as $line) {
            $line = trim($line);
            if (preg_match('/\b(?:bank|banka|bankov[ée]|IBAN|BIC|SWIFT)\b/iu', $line)) {
                continue;
            }
            if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N} &\x{27}.,()\/-]+(?:s\.\h*r\.\h*o\.|a\.\h*s\.|ltd\.?|gmbh)$/iuD', $line)) {
                $candidates[] = $line;
            }
        }

        return $this->unique($candidates, fn (string $value) => $this->supplierName($value));
    }

    private function supplierHeader(string $text): string
    {
        return preg_split('/\b(?:odberate[ľl]|odb[ěe]ratel|customer|buyer|bill\h+to)\b\h*:?/iu', $text, 2)[0];
    }

    private function supplierName(string $value): ?string
    {
        return $value !== '' && mb_strlen($value) <= 255 && ! preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $value)
            ? $value : null;
    }

    private function invalidPdf(): never
    {
        throw ValidationException::withMessages([
            'file' => ['Could not safely read this PDF. Use an unencrypted PDF of at most 10 MiB, 25 pages and 200,000 text bytes.'],
        ]);
    }
}

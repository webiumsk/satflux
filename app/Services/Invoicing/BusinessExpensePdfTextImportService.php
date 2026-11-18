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

    private const DOCUMENT_MARKER = '/\b(?:fakt[úu]ra|faktury|invoice|credit\h+note|dobropis)\b/iu';

    private const INVOICE_NUMBER_LABELS = '[čc][íi]slo\h+fakt[úu]ry|fakt[úu]ra\h+[čc](?:[íi]slo)?\.?|invoice\h+(?:number|no\.?|#)';

    private const CREDIT_NUMBER_LABELS = 'dobropis\h+[čc](?:[íi]slo)?\.?|credit\h+note\h+(?:number|no\.?|#)';

    private const SELLER_LABELS = 'dod[áa]vate[ľl]|dodavatel|supplier(?:\h+name)?|seller|vendor';

    private const BUYER_LABELS = 'odberate[ľl]|odb[ěe]ratel|customer|buyer|bill\h+to';

    private const NUMBER_LABELS = self::INVOICE_NUMBER_LABELS.'|'.self::CREDIT_NUMBER_LABELS;

    private const ISSUE_DATE_LABELS = 'd[áa]tum\h+(?:vystavenia|vyhotovenia)|datum\h+vystaven[íi]|invoice\h+date|issue\h+date|date\h+of\h+issue';

    private const DUE_DATE_LABELS = 'd[áa]tum\h+splatnosti|datum\h+splatnosti|due\h+date|payment\h+due\h+date';

    private const DELIVERY_DATE_LABELS = 'd[áa]tum\h+dodania(?:\h+slu[žz]by)?|datum\h+(?:dod[áa]n[íi]|zdaniteln[ée]ho\h+pln[ěe]n[íi])|delivery\h+date|tax\h+point\h+date';

    private const SYMBOL_LABELS = 'variabiln[ýyíi]\h+symbol|variable\h+symbol|VS';

    private const CURRENCIES = 'EUR|USD|GBP|CZK|CHF|PLN|HUF|SEK|NOK|DKK';

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
        if (! preg_match(self::DOCUMENT_MARKER, $text)) {
            return [];
        }

        $fields = [];
        $number = $this->unique($this->values($text, self::NUMBER_LABELS), fn (string $value) => $this->invoiceNumber($value));
        if ($number !== null) {
            $fields['external_number'] = $number;
        }
        $summary = $this->invoiceSummary($text, $number);
        $invoicePage = $summary['header'];
        $symbol = $this->unique($this->values($summary['fields'], self::SYMBOL_LABELS),
            fn (string $value) => preg_match('/^\d{1,10}$/D', $value) ? $value : null);
        if ($symbol !== null) {
            $fields['variable_symbol'] = $symbol;
        }

        foreach ([
            'issue_date' => self::ISSUE_DATE_LABELS,
            'delivery_date' => self::DELIVERY_DATE_LABELS,
            'due_date' => self::DUE_DATE_LABELS,
        ] as $field => $labels) {
            $date = $this->unique($this->values($summary['fields'], $labels), fn (string $value) => $this->date($value));
            if ($date !== null) {
                $fields[$field] = $date;
            }
        }

        $amount = $this->unique($this->values($summary['payment'], self::TOTAL_LABELS), fn (string $value) => $this->amount($value));
        if ($amount !== null && (float) $amount > 0) {
            $amount = match ($this->documentType($summary['fields'])) {
                'invoice' => $amount,
                'credit_note' => '-'.$amount,
                default => null,
            };
        }
        if ($amount !== null) {
            $fields['total'] = (float) $amount;
        }
        preg_match_all('/\b(?:'.self::CURRENCIES.')\b|€/u', $summary['payment'], $currencies);
        $currency = $this->unique($currencies[0], fn (string $value) => $value === '€' ? 'EUR' : $value);
        if ($currency !== null) {
            $fields['currency'] = $currency;
        }

        // A currency or payment account alone does not establish an invoice draft.
        if (! array_intersect_key($fields, array_flip(['external_number', 'issue_date', 'delivery_date', 'due_date', 'total']))) {
            return [];
        }
        $header = $this->supplierHeader($invoicePage ?? '');
        $sellers = $this->partyNames($invoicePage ?? '', self::SELLER_LABELS);
        $supplier = $this->unique($sellers, fn (string $value) => $this->supplierName($value));
        if ($supplier !== null) {
            $buyers = $this->partyNames($invoicePage ?? '', self::BUYER_LABELS);
            if (in_array($this->partyKey($supplier), array_map($this->partyKey(...), $buyers), true)) {
                $supplier = null;
            }
        }
        if ($supplier !== null) {
            $fields['supplier_name'] = $supplier;
            $fields['title'] = $supplier;
        }
        // Accounts in the buyer's section are not supplier payment instructions.
        // A seller identified only after the buyer boundary cannot establish ownership of an earlier account.
        $ibans = $supplier !== null && $this->partyNames($header, self::SELLER_LABELS) === []
            ? [] : $this->issuerIbans($header);
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
        if (! preg_match('/^([\p{L}\p{N}][\p{L}\p{N}\/._-]{0,63})(?:\h+strana\h+([1-9][0-9]{0,5})\/([1-9][0-9]{0,5}))?$/iuD', $value, $parts)) {
            return null;
        }

        return isset($parts[2]) && (int) $parts[2] > (int) $parts[3] ? null : $parts[1];
    }

    /** @return array{header: ?string, fields: string, payment: string} */
    private function invoiceSummary(string $text, ?string $number): array
    {
        // Annex numbers, dates and payees are references, even when the invoice itself is unnumbered.
        $pages = array_values(array_filter(explode("\f", $text), static fn (string $page): bool => trim($page) !== ''
            && ! preg_match('/^\s*(?:service\h+details?|annex|appendix|príloha|příloha)(?:\h+\d+)?\h*:?\h*(?=\n|$)/iu', $page)));
        $text = implode("\n\f\n", $pages);
        if (! preg_match(self::DOCUMENT_MARKER, $text)) {
            return ['header' => null, 'fields' => '', 'payment' => ''];
        }
        if (count($pages) === 1) {
            return ['header' => $text, 'fields' => $text, 'payment' => $text];
        }
        $unresolved = ['header' => null, 'fields' => '', 'payment' => ''];
        if ($number === null) {
            // Keep dates from one dated page without combining different invoice identities.
            $dateLabels = self::ISSUE_DATE_LABELS.'|'.self::DELIVERY_DATE_LABELS.'|'.self::DUE_DATE_LABELS;
            $dated = array_filter($pages, fn (string $page): bool => $this->values($page, $dateLabels) !== []);

            return ['header' => null, 'fields' => count($dated) === 1 ? reset($dated) : '', 'payment' => ''];
        }

        $numbered = array_filter($pages, fn (string $page): bool => $this->values($page, self::NUMBER_LABELS) !== []);
        $header = null;
        foreach ($numbered as $page) {
            $issue = $this->unique($this->values($page, self::ISSUE_DATE_LABELS), fn (string $value) => $this->date($value));
            $due = $this->unique($this->values($page, self::DUE_DATE_LABELS), fn (string $value) => $this->date($value));
            if ($issue !== null && $due !== null) {
                if ($header !== null) {
                    return $unresolved;
                }
                $header = $page;
            }
        }

        if ($header === null) {
            // A repeated footer on a page of its own is not a competing invoice.
            $numbered = array_filter($numbered, static fn (string $page): bool => ! preg_match(
                '/^\s*(?:'.self::NUMBER_LABELS.')(?![\p{L}\p{N}])\h*:?[ \t]*[^\n\r]+\s*$/iuD', $page
            ));
            if (count($numbered) !== 1) {
                return $unresolved;
            }
            // A partial header cannot resolve conflicting totals elsewhere in the document.
            $page = reset($numbered);
            $total = $this->unique($this->values($text, self::TOTAL_LABELS), fn (string $value) => $this->amount($value));

            return ['header' => null, 'fields' => $page, 'payment' => $total === null ? '' : $page];
        }

        $sellers = $this->sellerKeys($header);
        $accounts = $this->accountKeys($header);
        $payablePages = [];
        foreach ($numbered as $page) {
            $totals = $this->values($page, self::TOTAL_LABELS);
            if ($page !== $header) {
                $pageSellers = $this->sellerKeys($page);
                $pageAccounts = $this->accountKeys($page);
                if ($pageSellers === [] && $pageAccounts === [] && $totals === []) {
                    continue;
                }
                if ($pageSellers === [] || array_diff($pageSellers, $sellers) !== [] || array_diff($pageAccounts, $accounts) !== []) {
                    // A shared number cannot establish which of two different payees issued the invoice.
                    return ['header' => null, 'fields' => '', 'payment' => ''];
                }
            }
            if ($totals !== []) {
                $payablePages[] = $page;
            }
        }

        // An unreadable total outside a marked annex still requires manual review.
        foreach ($this->values($text, self::TOTAL_LABELS) as $value) {
            if ($this->amount($value) === null) {
                return ['header' => $header, 'fields' => $header, 'payment' => ''];
            }
        }
        $payment = $payablePages === [] || in_array($header, $payablePages, true) ? $header : implode("\n\f\n", $payablePages);

        return ['header' => $header, 'fields' => $header, 'payment' => $payment];
    }

    /** @return list<string> */
    private function accountKeys(string $page): array
    {
        $accounts = $this->issuerIbans($this->supplierHeader($page));

        return array_values(array_unique(array_map(static fn (string $value): string => SupplierPaymentIban::normalize($value) ?? $value, $accounts)));
    }

    /** @return list<string> */
    private function sellerKeys(string $page): array
    {
        return array_map($this->partyKey(...), $this->partyNames($page, self::SELLER_LABELS));
    }

    private function partyKey(string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($name));
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
        $value = trim(preg_replace('/\h*(?:'.self::CURRENCIES.'|€)$/u', '', trim($value)));
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

    /** @return list<string> */
    private function partyNames(string $text, string $labels): array
    {
        // Postal windows, logos and signatures can name someone other than the supplier.
        // Accept a role label only at the start of a line or cell with a separated value.
        preg_match_all('/(?:^|[\n\t])[ ]*(?:'.$labels.')[ ]*(?::[ \t]*(?:\n[ \t]*)?|\t+[ ]*|\n[ \t]*)([^\n\r\t\f]+)/iu', $text, $matches);

        $headings = self::BUYER_LABELS.'|'.self::SELLER_LABELS.'|invoice|fakt[úu]ra|credit\h+note|dobropis';
        $metadata = self::NUMBER_LABELS.'|'.self::ISSUE_DATE_LABELS.'|'.self::DUE_DATE_LABELS.'|'.self::DELIVERY_DATE_LABELS
            .'|'.self::TOTAL_LABELS.'|'.self::SYMBOL_LABELS.'|IBAN|BIC(?:/SWIFT)?|SWIFT|currency|mena|měna|bank\h+details|bankov[ée]\h+spojenie';

        $structured = '(?:'.self::NUMBER_LABELS.')\h+[\p{L}\p{N}][\p{L}\p{N}/._-]{0,63}'
            .'|(?:currency|mena|měna)\h+(?:'.self::CURRENCIES.'|€)'
            .'|IBAN\h+[A-Z]{2}[0-9]{2}[A-Z0-9 ]+|(?:BIC(?:/SWIFT)?|SWIFT)\h+(?-i:[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)';
        $names = array_map('trim', $matches[1]);

        return array_values(array_filter($names, static fn (string $name): bool => ! preg_match(
            '~^(?:'.$headings.'|'.$metadata.')\h*:?$|^(?:'.$metadata.')(?:\h*:|\h+[+\-\d])|^(?:'.$structured.')$~iuD', $name
        )));
    }

    private function supplierHeader(string $text): string
    {
        return preg_split('/(?:^|[\n\t])[ ]*(?:'.self::BUYER_LABELS.')(?=[:\h\n]|$)/iu', $text, 2)[0];
    }

    private function supplierName(string $value): ?string
    {
        return $value !== '' && mb_strlen($value) <= 255 && ! preg_match('/[:\p{Cc}\p{Zl}\p{Zp}]/u', $value)
            ? $value : null;
    }

    private function invalidPdf(): never
    {
        throw ValidationException::withMessages([
            'file' => ['Could not safely read this PDF. Use an unencrypted PDF of at most 10 MiB, 25 pages and 200,000 text bytes.'],
        ]);
    }
}

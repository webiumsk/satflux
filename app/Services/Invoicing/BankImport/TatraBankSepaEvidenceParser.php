<?php

namespace App\Services\Invoicing\BankImport;

use App\Support\Invoicing\BankAccountIdentifier;

/**
 * Strict plain-text SEPA evidence, separate from permissive company importing.
 *
 * Structural witnesses: ISPadmin's 2016 Tatra import example and REMP CRM's
 * docs/tatrabanka.eml (2019). Both put three numeric balances and a numeric
 * CCINT description before the reference and all free text. Tatra's official
 * B-mail page documents CCINT/CSO numeric account descriptions. QR E2E values
 * in that bounded slot are a supported synthetic adaptation, not an observed
 * current-bank template. Other layouts need separately reviewed profiles.
 *
 * https://wiki4.ispadmin.eu/cz/faq/centralni-sklad/360-cz/fakturace/menu-fakturace.html
 * https://github.com/remp2020/crm-payments-module/blob/master/docs/tatrabanka.eml
 * https://www.tatrabanka.sk/sk/personal/ucet-platby/internet-banking/b-mail/
 */
final class TatraBankSepaEvidenceParser
{
    /** @return array{e2e: ?string, vs: ?string, identity: string}|null */
    public function parse(string $body): ?array
    {
        $stage = 'unsupported_content';

        return $this->evaluate($body, $stage);
    }

    /** First failed validation in the existing strict profile; never parsed values. */
    public function diagnose(string $body): string
    {
        $stage = 'unsupported_content';
        $this->evaluate($body, $stage);

        return $stage;
    }

    /** @return array{e2e: ?string, vs: ?string, identity: string}|null */
    private function evaluate(string $body, string &$stage): ?array
    {
        $stage = 'unsupported_content';
        // Never flatten HTML/entities into apparent bank-owned text fields.
        $text = str_replace("\r\n", "\n", $body);
        if (strlen($text) > 65535 || ! mb_check_encoding($text, 'UTF-8')
            || preg_match('/[<>\x00-\x09\x0B-\x1F\x7F]|\p{Cf}|&(?:#(?:x[0-9a-f]+|[0-9]+)|[a-z][a-z0-9]+);/iu', $text) !== 0) {
            return null;
        }
        $lines = array_values(array_filter(array_map(
            fn (string $line) => trim($line, ' '), explode("\n", $text),
        ), fn (string $line) => $line !== ''));
        $stage = 'greeting_or_line_count';
        if (count($lines) < 10 || count($lines) > 16 || $lines[0] !== 'Vazeny klient,') {
            return null;
        }

        $stage = 'credit_header';
        $money = '(?:0|[1-9][0-9]{0,11}|[1-9][0-9]{0,2}(?: [0-9]{3}){1,3}),[0-9]{2}';
        if (preg_match('/\A([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4}) ([0-9]{1,2}):([0-9]{2}) bol zostatok Vasho uctu ((?:SK|CZ)[0-9]{22}) zvyseny o ('.$money.') EUR\.\z/D', $lines[1], $header) !== 1) {
            return null;
        }
        $stage = 'credit_datetime';
        [$day, $month, $year, $hour, $minute] = array_map(intval(...), array_slice($header, 1, 5));
        if (! checkdate($month, $day, $year) || $year < 2000 || $hour > 23 || $minute > 59) {
            return null;
        }
        $timestamp = sprintf('%04d-%02d-%02d %02d:%02d', $year, $month, $day, $hour, $minute);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $timestamp, new \DateTimeZone('Europe/Bratislava'));
        if ($date === false || $date->format('Y-m-d H:i') !== $timestamp) {
            return null;
        }
        $stage = 'creditor_or_amount';
        $iban = BankAccountIdentifier::normalize($header[6]);
        $amount = str_replace(' ', '', $header[7]);
        if ($iban === null || $amount === '0,00') {
            return null;
        }

        $stage = 'balances';
        $balances = [];
        foreach (['uctovny zostatok', 'aktualny zostatok', 'disponibilny zostatok'] as $index => $label) {
            if (preg_match('/\A'.$label.': (-?'.$money.') EUR\z/D', $lines[$index + 2], $balance) !== 1) {
                return null;
            }
            $canonicalBalance = str_replace(' ', '', $balance[1]);
            $balances[] = $canonicalBalance === '-0,00' ? '0,00' : $canonicalBalance;
        }
        $stage = 'transaction_description';
        if (preg_match('/\APopis transakcie: (CCINT|CSO|Platba) ([0-9]{4})\/([0-9]{1,6})-([0-9]{1,10})\z/D', $lines[5], $description) !== 1) {
            return null;
        }
        $stage = 'trusted_reference';
        if (preg_match('/\AReferencia platitela: (QR-[0-9a-fA-F]{32}|\/VS[0-9]{1,10}\/SS[0-9]{0,10}\/KS[0-9]{0,10})\z/D', $lines[6], $reference) !== 1) {
            return null;
        }
        $e2e = null;
        $vs = null;
        if (str_starts_with($reference[1], 'QR-')) {
            // Provider references are case-sensitive, including NOP-issued IDs.
            $e2e = $reference[1];
        } else {
            preg_match('/\A\/VS([0-9]{1,10})\//D', $reference[1], $symbol);
            $vs = $symbol[1];
        }
        $stage = 'tail';
        if (! $this->validTail(array_slice($lines, 7))) {
            return null;
        }

        // This conservative fingerprint is not a bank-issued transaction ID.
        // Keep its schema stable across template variants. Excluding invoice
        // references, free notes and rendering prevents reallocation on replay;
        // indistinguishable credits may collide and must not pay twice.
        $identity = hash('sha256', json_encode([
            'tatra-credit-v1', $iban, $timestamp, $amount, 'EUR', $balances,
            $description[1], $description[2],
            ltrim($description[3], '0') ?: '0', ltrim($description[4], '0') ?: '0',
        ], JSON_THROW_ON_ERROR));

        $stage = 'matched';

        return ['e2e' => $e2e, 'vs' => $vs, 'identity' => $identity];
    }

    /** @param list<string> $lines */
    private function validTail(array $lines): bool
    {
        $index = 0;
        // Enumerated witnessed post-reference fields. Their values never
        // participate in matching and cannot introduce structural labels.
        foreach (['Informacia pre prijemcu: ', 'Ucet protistrany: '] as $label) {
            if (isset($lines[$index]) && str_starts_with($lines[$index], $label)) {
                if (! $this->safeFreeText(substr($lines[$index], strlen($label)))) {
                    return false;
                }
                $index++;
            }
        }
        if ($index === 0 || ($lines[$index] ?? null) !== 'S pozdravom'
            || ($lines[$index + 1] ?? null) !== 'TATRA BANKA, a.s.') {
            return false;
        }
        $index += 2;
        if (in_array($lines[$index] ?? null, ['http://www.tatrabanka.sk', 'https://www.tatrabanka.sk'], true)) {
            $index++;
        }
        foreach (['Poznamka: ', 'Odporucame Vam '] as $label) {
            if (isset($lines[$index]) && str_starts_with($lines[$index], $label)) {
                if (! $this->safeFreeText(substr($lines[$index], strlen($label)))) {
                    return false;
                }
                $index++;
            }
        }

        return $index === count($lines);
    }

    private function safeFreeText(string $value): bool
    {
        return $value !== '' && strlen($value) <= 2048
            && preg_match('/(?:Referencia\s+platite[lľ]a|Popis\s+transakcie|Inform[aá]cia\s+pre\s+pr[ií]jemcu|[UÚ][cč]et\s+protistrany|(?:u[cč]tovn[yý]|aktu[aá]lny|disponibiln[yý])\s+zostatok|bol\s+zostatok|V[aá][zž]en[yý]\s+klient|S\s+pozdravom|TATRA\s+BANKA)/iu', $value) === 0;
    }
}

<?php

namespace App\Services\Invoicing\BankImport;

use App\Enums\BankTransactionDirection;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;

class Camt053Parser implements BankStatementParser
{
    public function supports(string $filename, string $contents): bool
    {
        $lower = strtolower($filename);

        if (str_ends_with($lower, '.xml')) {
            return str_contains($contents, 'BkToCstmrStmt') || str_contains($contents, 'Camt.053');
        }

        return str_contains(ltrim($contents), '<?xml') && str_contains($contents, 'Ntry');
    }

    public function parse(string $contents): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw ValidationException::withMessages([
                'file' => ['Invalid CAMT.053 XML file.'],
            ]);
        }

        $xml->registerXPathNamespace('n', $xml->getNamespaces(true)[''] ?? 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');

        $entries = $xml->xpath('//*[local-name()="Ntry"]') ?: [];
        $parsed = [];

        foreach ($entries as $ntry) {
            $row = $this->parseEntry($ntry);
            if ($row !== null) {
                $parsed[] = $row;
            }
        }

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'file' => ['No transactions found in CAMT.053 file.'],
            ]);
        }

        return $parsed;
    }

    protected function parseEntry(SimpleXMLElement $ntry): ?ParsedBankTransaction
    {
        $amountNode = $this->child($ntry, 'Amt');
        if ($amountNode === null) {
            return null;
        }

        $amount = (float) $amountNode;
        if ($amount == 0.0) {
            return null;
        }

        $currency = strtoupper((string) ($amountNode['Ccy'] ?? 'EUR'));
        $cdtDbt = strtoupper((string) $this->childValue($ntry, 'CdtDbtInd'));
        $direction = $cdtDbt === 'DBIT'
            ? BankTransactionDirection::Debit
            : BankTransactionDirection::Credit;

        $booked = $this->childValue($ntry, 'BookgDt', 'Dt')
            ?: $this->childValue($ntry, 'ValDt', 'Dt');
        if ($booked === '') {
            return null;
        }

        $symbols = $this->extractSymbols($ntry);
        $reference = $this->extractReference($ntry);

        return new ParsedBankTransaction(
            bookedAt: Carbon::parse($booked),
            amount: $amount,
            currency: $currency,
            direction: $direction,
            variableSymbol: $symbols['vs'],
            constantSymbol: $symbols['ks'],
            specificSymbol: $symbols['ss'],
            counterpartyName: $this->extractCounterparty($ntry),
            reference: $reference,
            bankTransactionId: $this->childValue($ntry, 'NtryRef') ?: null,
        );
    }

    /**
     * @return array{vs: ?string, ks: ?string, ss: ?string}
     */
    protected function extractSymbols(SimpleXMLElement $ntry): array
    {
        $vs = null;
        $ks = null;
        $ss = null;

        foreach ($ntry->xpath('.//*[local-name()="CdtrRefInf"]') ?: [] as $ref) {
            $tp = strtoupper((string) $this->childValue($ref, 'Tp', 'Cd'));
            $value = BankSymbolNormalizer::variableSymbol($this->childValue($ref, 'Ref'));
            if ($value === null) {
                continue;
            }
            if ($tp === 'KS') {
                $ks = $value;
            } elseif ($tp === 'SS') {
                $ss = $value;
            } else {
                $vs = $value;
            }
        }

        if ($vs === null) {
            foreach ($ntry->xpath('.//*[local-name()="EndToEndId"]') ?: [] as $e2e) {
                $candidate = BankSymbolNormalizer::variableSymbol((string) $e2e);
                if ($candidate !== null) {
                    $vs = $candidate;
                    break;
                }
            }
        }

        return ['vs' => $vs, 'ks' => $ks, 'ss' => $ss];
    }

    protected function extractReference(SimpleXMLElement $ntry): ?string
    {
        $parts = [];
        foreach ($ntry->xpath('.//*[local-name()="Ustrd"]') ?: [] as $u) {
            $text = trim((string) $u);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    protected function extractCounterparty(SimpleXMLElement $ntry): ?string
    {
        foreach (['Dbtr', 'Cdtr'] as $party) {
            foreach ($ntry->xpath('.//*[local-name()="'.$party.'"]/*[local-name()="Nm"]') ?: [] as $nm) {
                $name = trim((string) $nm);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    protected function child(SimpleXMLElement $parent, string $localName): ?SimpleXMLElement
    {
        $nodes = $parent->xpath('./*[local-name()="'.$localName.'"]') ?: [];

        return isset($nodes[0]) ? $nodes[0] : null;
    }

    protected function childValue(SimpleXMLElement $parent, string $localName, ?string $childLocal = null): string
    {
        $node = $this->child($parent, $localName);
        if ($node === null) {
            return '';
        }
        if ($childLocal !== null) {
            $child = $this->child($node, $childLocal);

            return $child !== null ? trim((string) $child) : '';
        }

        return trim((string) $node);
    }
}

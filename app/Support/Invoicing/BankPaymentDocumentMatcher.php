<?php

namespace App\Support\Invoicing;

use App\Models\BusinessDocument;

/**
 * Match bank payment hints (VS column and/or payment reference) to invoice documents.
 */
final class BankPaymentDocumentMatcher
{
    public static function hasMatchableHints(?string $bankVariableSymbol, ?string $bankReference): bool
    {
        $bankVs = BankSymbolNormalizer::variableSymbol($bankVariableSymbol);
        if ($bankVs !== null) {
            return true;
        }

        return $bankReference !== null && trim($bankReference) !== '';
    }

    public static function matches(
        BusinessDocument $document,
        ?string $bankVariableSymbol,
        ?string $bankReference,
    ): bool {
        $bankVs = BankSymbolNormalizer::variableSymbol($bankVariableSymbol);
        if ($bankVs !== null && self::matchesDocumentSymbol($document, $bankVs)) {
            return true;
        }

        return self::matchesDocumentReference($document, $bankReference);
    }

    public static function matchesDocumentSymbol(BusinessDocument $document, ?string $symbol): bool
    {
        if ($symbol === null || $symbol === '') {
            return false;
        }

        if ((string) $document->variable_symbol === $symbol) {
            return true;
        }

        $fromNumber = preg_replace('/\D/', '', (string) ($document->number ?? ''));

        return $fromNumber !== '' && $fromNumber === $symbol;
    }

    public static function matchesDocumentReference(BusinessDocument $document, ?string $reference): bool
    {
        if ($reference === null || trim($reference) === '') {
            return false;
        }

        $reference = trim($reference);

        $docVs = trim((string) ($document->variable_symbol ?? ''));
        if ($docVs !== '' && self::referenceMatchesToken($reference, $docVs)) {
            return true;
        }

        $number = trim((string) ($document->number ?? ''));
        if ($number !== '' && self::referenceMatchesToken($reference, $number)) {
            return true;
        }

        $digits = BankSymbolNormalizer::variableSymbol($reference);

        return $digits !== null && self::matchesDocumentSymbol($document, $digits);
    }

    public static function matchReason(
        BusinessDocument $document,
        ?string $bankVariableSymbol,
        ?string $bankReference,
    ): ?string {
        $bankVs = BankSymbolNormalizer::variableSymbol($bankVariableSymbol);
        if ($bankVs !== null && self::matchesDocumentSymbol($document, $bankVs)) {
            return 'variable_symbol';
        }
        if (self::matchesDocumentReference($document, $bankReference)) {
            return 'payment_reference';
        }

        return null;
    }

    protected static function referenceMatchesToken(string $reference, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        if (strcasecmp($reference, $token) === 0) {
            return true;
        }

        if (stripos($reference, $token) !== false) {
            return true;
        }

        $refDigits = preg_replace('/\D/', '', $reference);
        $tokenDigits = preg_replace('/\D/', '', $token);

        return $tokenDigits !== ''
            && $refDigits !== ''
            && str_contains($refDigits, $tokenDigits);
    }
}

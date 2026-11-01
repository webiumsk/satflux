<?php

namespace Tests\Unit;

use App\Services\Invoicing\BankImport\TatraBankSepaEvidenceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TatraBankSepaEvidenceDiagnosticsTest extends TestCase
{
    public static function body(string $reference = 'QR-ab29e346f1d841c8a95a63d857490818'): string
    {
        return "Vazeny klient,\n"
            ."01.06.2026 12:31 bol zostatok Vasho uctu SK4811000000002944116480 zvyseny o 12,50 EUR.\n"
            ."uctovny zostatok: 1 012,50 EUR\naktualny zostatok: 1 012,50 EUR\n"
            ."disponibilny zostatok: 1 012,50 EUR\nPopis transakcie: CCINT 1100/000000-261426464\n"
            .'Referencia platitela: '.$reference."\nInformacia pre prijemcu: Invoice payment\n"
            ."S pozdravom\nTATRA BANKA, a.s.";
    }

    /** Existing test account and synthetic values; only the field structure is observed. */
    public static function platbaBody(string $reference = 'QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): string
    {
        return str_replace([
            'CCINT 1100/000000-261426464', 'Informacia pre prijemcu: Invoice payment',
        ], [
            'Platba 1100/000000-0000000123', 'Ucet protistrany: Synthetic Payer',
        ], self::body($reference))."\nhttp://www.tatrabanka.sk\nPoznamka: Synthetic bank notice\nOdporucame Vam Synthetic notice";
    }

    /** Corpus of supported historical adaptations and hostile/unsupported frames. */
    public static function frames(): iterable
    {
        $body = self::body();
        $evidence = [
            'e2e' => 'QR-ab29e346f1d841c8a95a63d857490818', 'vs' => null,
            'identity' => '9860306e4ad7d2e1d68a7dd255bc569ecd1b885e502016a62ee3299e175f4137',
        ];
        yield 'classic' => [$body, 'matched', $evidence];
        yield 'rendering' => [str_replace(['01.06.2026', '1 012,50', "\n"], ['1.6.2026', '1012,50', "\r\n  "], $body), 'matched', $evidence];
        yield 'reference-case' => [self::body(strtoupper($evidence['e2e'])), 'matched', array_replace($evidence, ['e2e' => strtoupper($evidence['e2e'])])];
        yield 'variable-symbol' => [self::body('/VS1234567890/SS/KS'), 'matched', array_replace($evidence, ['e2e' => null, 'vs' => '1234567890'])];
        yield 'counterparty-tail' => [str_replace('Informacia pre prijemcu: Invoice payment', 'Ucet protistrany: Synthetic Payer', $body), 'matched', $evidence];
        yield 'cso' => [str_replace('CCINT', 'CSO', $body), 'matched', array_replace($evidence, ['identity' => 'b50db53a436a2b0d5c99cbcfad4a55fc7c603bb455f42d6fa017edb30ddf3bd6'])];
        yield 'optional-tail' => [$body."\nhttps://www.tatrabanka.sk\nPoznamka: Synthetic note\nOdporucame Vam Synthetic notice", 'matched', $evidence];
        $platbaEvidence = [
            'e2e' => 'QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'vs' => null,
            'identity' => '592726f4848318a1d34384d1f834da3f320de0086a4a68f5a5ddc9e068f1e3f8',
        ];
        yield 'platba-qr' => [self::platbaBody(), 'matched', $platbaEvidence];
        yield 'platba-vs' => [self::platbaBody('/VS1234567890/SS/KS'), 'matched', array_replace($platbaEvidence, ['e2e' => null, 'vs' => '1234567890'])];
        foreach ([
            'html' => ['<div>'.$body.'</div>', 'unsupported_content'],
            'entity' => [str_replace('Invoice payment', 'Invoice &#10;payment', $body), 'unsupported_content'],
            'control' => [$body."\0", 'unsupported_content'],
            'bidi' => [$body."\u{202e}", 'unsupported_content'],
            'invalid-utf8' => [$body."\xE9", 'unsupported_content'],
            'oversized' => [str_repeat('x', 65536), 'unsupported_content'],
            'greeting' => [str_replace('Vazeny klient,', 'Synthetic greeting', $body), 'greeting_or_line_count'],
            'too-few-lines' => [str_replace("aktualny zostatok: 1 012,50 EUR\n", '', $body), 'greeting_or_line_count'],
            'too-many-lines' => [$body.str_repeat("\nSynthetic line", 7), 'greeting_or_line_count'],
            'header' => [str_replace('zvyseny', 'znizeny', $body), 'credit_header'],
            'wrong-currency' => [str_replace('EUR', 'USD', $body), 'credit_header'],
            'header-suffix' => [str_replace('o 12,50 EUR.', 'o 12,50 EUR. Synthetic', $body), 'credit_header'],
            'date' => [str_replace('01.06.2026', '31.02.2026', $body), 'credit_datetime'],
            'year' => [str_replace('2026', '1999', $body), 'credit_datetime'],
            'hour' => [str_replace('12:31', '25:31', $body), 'credit_datetime'],
            'minute' => [str_replace('12:31', '12:60', $body), 'credit_datetime'],
            'dst-gap' => [str_replace('01.06.2026 12:31', '29.03.2026 02:31', $body), 'credit_datetime'],
            'iban-checksum' => [str_replace('SK4811000000002944116480', 'SK4811000000002944116481', $body), 'creditor_or_amount'],
            'zero-credit' => [str_replace('o 12,50 EUR.', 'o 0,00 EUR.', $body), 'creditor_or_amount'],
            'balance-label' => [str_replace('aktualny zostatok:', 'uctovny zostatok:', $body), 'balances'],
            'balance-value' => [str_replace('disponibilny zostatok: 1 012,50 EUR', 'disponibilny zostatok: Synthetic', $body), 'balances'],
            'description' => [str_replace('CCINT 1100/000000-261426464', 'Synthetic Payer', $body), 'transaction_description'],
            'blank-reference' => [self::body(''), 'trusted_reference'],
            'arbitrary-reference' => [self::body('synthetic-private-reference'), 'trusted_reference'],
            'reference-in-remittance' => [str_replace('Invoice payment', 'Invoice payment Referencia platitela: /VS123/SS/KS', self::body('')), 'trusted_reference'],
            'duplicate-reference' => [str_replace('Informacia pre prijemcu: Invoice payment', 'Referencia platitela: /VS123/SS/KS'."\nInformacia pre prijemcu: Invoice payment", $body), 'tail'],
            'nested-tail' => [str_replace('Invoice payment', 'Invoice payment bol zostatok Vasho uctu', $body), 'tail'],
            'footer' => [str_replace('S pozdravom', 'Synthetic footer', $body), 'tail'],
            'tail-extra' => [$body."\nSynthetic unexpected tail", 'tail'],
            'tail-unsafe-url' => [$body."\nhttps://synthetic.example.test", 'tail'],
            'first-failure' => [str_replace(['zvyseny', 'S pozdravom'], ['znizeny', 'Synthetic'], $body), 'credit_header'],
        ] as $name => [$frame, $stage]) {
            yield $name => [$frame, $stage, null];
        }
    }

    #[DataProvider('frames')]
    public function test_diagnostic_stage_and_existing_evidence_shape(string $body, string $stage, ?array $evidence): void
    {
        $parser = new TatraBankSepaEvidenceParser;
        $this->assertSame($evidence, $parser->parse($body));
        $this->assertSame($stage, $parser->diagnose($body));
        $this->assertSame($evidence, $parser->parse($body));
    }

    public function test_diagnostics_never_return_body_fragments_or_private_values(): void
    {
        $body = str_replace('Invoice payment', 'synthetic-private-note-marker', self::body());
        $parser = new TatraBankSepaEvidenceParser;
        $this->assertSame('matched', $parser->diagnose($body));
        $this->assertSame('trusted_reference', $parser->diagnose(str_replace('QR-ab29e346f1d841c8a95a63d857490818', 'synthetic-private-ref-marker', $body)));
    }

    public function test_platba_identity_keeps_canonical_numbers_and_excludes_references_and_free_tail(): void
    {
        $parser = new TatraBankSepaEvidenceParser;
        $body = self::platbaBody();
        $identity = $parser->parse($body)['identity'];
        foreach ([
            self::platbaBody('QR-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
            self::platbaBody('/VS1234567890/SS/KS'),
            str_replace(['000000-0000000123', '01.06.2026', '1 012,50'], ['0-123', '1.6.2026', '1012,50'], $body),
            str_replace(['Synthetic Payer', 'Synthetic bank notice'], ['Another Payer', 'Another bank notice'], $body),
            str_replace("\n", "\r\n  \r\n", $body),
        ] as $variant) {
            $this->assertSame('matched', $parser->diagnose($variant));
            $this->assertSame($identity, $parser->parse($variant)['identity']);
        }
        $this->assertNotSame($identity, $parser->parse(str_replace('1 012,50', '1 025,00', $body))['identity']);
        foreach (['CCINT', 'CSO'] as $label) {
            $legacy = $parser->parse(str_replace('Platba', $label, $body));
            $this->assertSame($parser->parse($body)['e2e'], $legacy['e2e']);
            $this->assertNotSame($identity, $legacy['identity']);
        }
    }

    public static function rejectedPlatbaFrames(): iterable
    {
        $body = self::platbaBody();
        foreach (['platba', 'PLATBA', 'PlatbaX', 'Platba '] as $label) {
            yield 'literal-'.$label => [str_replace('Platba', $label, $body), 'transaction_description'];
        }
        yield 'missing-numeric-description' => [str_replace('Platba 1100/000000-0000000123', 'Platba', $body), 'transaction_description'];
        foreach (['Platba 110/000000-0000000123', 'Platba 1100/0000000-0000000123', 'Platba 1100/000000-00000000123'] as $description) {
            yield $description => [str_replace('Platba 1100/000000-0000000123', $description, $body), 'transaction_description'];
        }
        yield 'embedded-description' => [str_replace('Ucet protistrany: Synthetic Payer', 'Ucet protistrany: Popis transakcie: Platba 1100/000000-0000000123', $body), 'tail'];
        yield 'blank-bank-reference-with-remittance-reference' => [str_replace([
            'QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Synthetic Payer',
        ], ['', 'Synthetic Payer Referencia platitela: QR-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'], $body), 'trusted_reference'];
        yield 'unrecognized-tail' => [$body."\nSynthetic unexpected tail", 'tail'];
    }

    #[DataProvider('rejectedPlatbaFrames')]
    public function test_platba_does_not_relax_numeric_reference_or_tail_guards(string $body, string $stage): void
    {
        $parser = new TatraBankSepaEvidenceParser;
        $this->assertNull($parser->parse($body));
        $this->assertSame($stage, $parser->diagnose($body));
    }
}

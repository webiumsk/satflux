<?php

namespace Tests\Unit\Services;

use App\Services\WalletConnectionValidator;
use Tests\TestCase;

class WalletConnectionValidatorTest extends TestCase
{
    protected WalletConnectionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WalletConnectionValidator;
    }

    public function test_valid_blink_connection_string(): void
    {
        // Valid Blink connection string format
        $connectionString = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_test123;wallet-id=wallet456';

        $result = $this->validator->validate('blink', $connectionString);

        $this->assertTrue($result['valid']);
        $this->assertEquals('blink', $result['type']);
    }

    public function test_invalid_blink_connection_string_missing_parts(): void
    {
        // Missing required parts
        $connectionString = 'type=blink;server=https://api.blink.sv/graphql';

        $result = $this->validator->validate('blink', $connectionString);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_invalid_blink_connection_string_wrong_format(): void
    {
        $connectionString = 'invalid:format';

        $result = $this->validator->validate('blink', $connectionString);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_valid_aqua_boltz_descriptor(): void
    {
        $descriptor = 'ct(slip77(xpub6D4BDPcP2GT577Vvch3Reb8P8CH),elsh(wpkh(xpub6E8...)))';

        $result = $this->validator->validate('aqua_descriptor', $descriptor);

        $this->assertTrue($result['valid']);
        $this->assertEquals('aqua_descriptor', $result['type']);
    }

    public function test_valid_bull_bitcoin_descriptor_with_checksum(): void
    {
        $descriptor = 'ct(slip77(5bd88956b5c0782248ad31f92d24712cff8c4cd761759dd629c08e2b60c9e6a7),elwpkh([0eb9c7d5/84h/1776h/0h]xpub6CE9h9pKdmMzM11sbeuRA1AAnmL3k6PWNzPDNw2gAGHMthvbVChXbhAADsKanndLJ7neMMBeC3oEA4uqadycLz8xYQbCdMF2NoMVZjJU7rB/<0;1>/*))#hw28w0rx';

        $result = $this->validator->validate('aqua_descriptor', $descriptor);

        $this->assertTrue($result['valid']);
    }

    public function test_valid_nwc_uri(): void
    {
        $uri = 'nostr+walletconnect://abc1234567890123456789012345678901234567890123456789012345678901234?relay=wss%3A%2F%2Frelay.example.com&secret=deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

        $result = $this->validator->validate('nwc', $uri);

        $this->assertTrue($result['valid']);
        $this->assertSame('nwc', $result['type']);
    }

    public function test_formats_btcpay_nwc_connection_string(): void
    {
        $uri = 'nostr+walletconnect:abc1234567890123456789012345678901234567890123456789012345678901234?relay=wss%3A%2F%2Frelay.example.com&secret=deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

        $formatted = $this->validator->formatBtcpayNwcConnectionString($uri);

        $this->assertStringStartsWith('type=nwc;key=nostr+walletconnect:', $formatted);
    }

    public function test_rejects_descriptor_without_ct_slip77(): void
    {
        $descriptor = 'wpkh(xpub6D4BDPcP2GT577Vvch3Reb8P8CH)';

        $this->assertFalse($this->validator->validateAquaDescriptor($descriptor));
    }

    public function test_rejects_descriptor_with_trailing_garbage(): void
    {
        $descriptor = 'ct(slip77(xpub6D4BDPcP2GT577Vvch3Reb8P8CH),elsh(wpkh(xpub6E8...))))extra';

        $this->assertFalse($this->validator->validateAquaDescriptor($descriptor));
    }

    public function test_valid_samrock_placeholder_shape(): void
    {
        $seed = hash('sha256', 'samrock:019e6827-e61c-7000-8000-000000000001');
        $slip77 = substr($seed, 0, 64);
        $fp = substr($seed, 0, 8);
        $xpubBody = 'xpub'.str_pad(substr($seed, 8, 100), 100, '0');
        $descriptor = "ct(slip77({$slip77}),elsh(wpkh([{$fp}/84h/0h/0h]{$xpubBody}/0/*)))";

        $this->assertTrue($this->validator->validateAquaDescriptor($descriptor));
    }

    public function test_validation_rejects_empty_string(): void
    {
        $result = $this->validator->validate('blink', '');

        $this->assertFalse($result['valid']);
    }

    public function test_validation_rejects_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->validator->validate('unsupported_type', 'test');
    }

    public function test_detect_aqua_brand_from_descriptor(): void
    {
        $descriptor = 'ct(slip77(xpub6D4BDPcP2GT577Vvch3Reb8P8CH),elsh(wpkh(xpub6E8...)))';

        $this->assertSame('aqua', $this->validator->detectAquaBrandFromDescriptor($descriptor));
    }

    public function test_detect_bull_brand_from_descriptor(): void
    {
        $descriptor = 'ct(slip77(5bd88956b5c0782248ad31f92d24712cff8c4cd761759dd629c08e2b60c9e6a7),elwpkh([0eb9c7d5/84h/1776h/0h]xpub6CE9h9pKdmMzM11sbeuRA1AAnmL3k6PWNzPDNw2gAGHMthvbVChXbhAADsKanndLJ7neMMBeC3oEA4uqadycLz8xYQbCdMF2NoMVZjJU7rB/<0;1>/*))#hw28w0rx';

        $this->assertSame('bull', $this->validator->detectAquaBrandFromDescriptor($descriptor));
    }
}

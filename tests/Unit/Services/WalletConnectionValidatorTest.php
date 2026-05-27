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

    public function test_rejects_descriptor_without_ct_slip77(): void
    {
        $descriptor = 'wpkh(xpub6D4BDPcP2GT577Vvch3Reb8P8CH)';

        $this->assertFalse($this->validator->validateAquaDescriptor($descriptor));
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
}

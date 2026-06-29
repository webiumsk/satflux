<?php

namespace Tests\Unit;

use App\Services\Invoicing\OpenRegistryService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenRegistrySwissMappingTest extends TestCase
{
    private OpenRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OpenRegistryService;
    }

    #[Test]
    public function map_summary_extracts_swiss_registration_and_tax_ids(): void
    {
        $mapped = $this->service->mapSummary([
            'jurisdiction' => 'CH',
            'company_id' => 'CHE-216.915.662',
            'company_name' => 'Institut für Franklin Methode GmbH',
            'registered_address' => 'Wetzikon (ZH)',
            'jurisdiction_data' => [
                'uid' => 'CHE216915662',
                'chid' => 'CH02040530375',
                'canton' => 'ZH',
                'address' => [
                    'street' => 'Hittnauerstrasse',
                    'houseNumber' => '40',
                    'city' => 'Wetzikon ZH',
                    'swissZipCode' => '8623',
                ],
            ],
        ]);

        $this->assertSame('CH-020.4.053.037-5', $mapped['ico']);
        $this->assertSame('CHE-216.915.662', $mapped['dic']);
        $this->assertStringContainsString('Hittnauerstrasse 40', $mapped['address_line']);
        $this->assertStringContainsString('CH-8623', $mapped['address_line']);
        $this->assertStringContainsString('Wetzikon', $mapped['address_line']);
    }

    #[Test]
    public function format_swiss_uid_and_chid_helpers_via_summary(): void
    {
        $mapped = $this->service->mapSummary([
            'jurisdiction' => 'CH',
            'company_id' => 'CHE216915662',
            'company_name' => 'Example GmbH',
            'jurisdiction_data' => [
                'chid' => 'CH02040530375',
            ],
        ]);

        $this->assertSame('CH-020.4.053.037-5', $mapped['ico']);
        $this->assertSame('CHE-216.915.662', $mapped['dic']);
    }
}

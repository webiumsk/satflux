<?php

namespace Tests\Feature;

use App\Services\Invoicing\Efaktura\SapiSkClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapiSkReceiveCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['efaktura.allowed_sapi_hosts' => ['custom-provider.test']]);
        Http::preventStrayRequests();
    }

    public function test_custom_provider_xml_detail_and_empty_acknowledgement_keep_the_legacy_fallbacks(): void
    {
        $xml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';
        Http::fake([
            'https://custom-provider.test/sapi/v1/document/receive/synthetic-document' => Http::response($xml, 200, ['Content-Type' => 'application/xml']),
            'https://custom-provider.test/sapi/v1/document/receive/synthetic-document/acknowledge' => Http::response('', 204),
        ]);
        $client = app(SapiSkClient::class);
        $this->assertSame(['payload' => $xml], $client->receivedDocument('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://custom-provider.test'));
        $this->assertSame(['status' => 'acknowledged'], $client->acknowledgeReceived('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://custom-provider.test'));
    }

    public function test_custom_provider_json_detail_and_acknowledgement_remain_unchanged(): void
    {
        Http::fake([
            'https://custom-provider.test/sapi/v1/document/receive/synthetic-document' => Http::response(['payload' => '<Invoice/>', 'provider_field' => 'synthetic']),
            'https://custom-provider.test/sapi/v1/document/receive/synthetic-document/acknowledge' => Http::response(['status' => 'received', 'provider_field' => 'synthetic']),
        ]);
        $client = app(SapiSkClient::class);
        $this->assertSame(['payload' => '<Invoice/>', 'provider_field' => 'synthetic'], $client->receivedDocument('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://custom-provider.test'));
        $this->assertSame(['status' => 'received', 'provider_field' => 'synthetic'], $client->acknowledgeReceived('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://custom-provider.test'));
    }

    public function test_custom_provider_fallbacks_do_not_treat_redirects_as_success(): void
    {
        Http::fake(['https://custom-provider.test/*' => Http::response('', 302, ['Location' => 'https://other.invalid'])]);
        $client = app(SapiSkClient::class);
        foreach (['receivedDocument', 'acknowledgeReceived'] as $method) {
            try {
                $client->$method('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://custom-provider.test');
                $this->fail('Custom-provider compatibility must not accept redirects.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('SAPI-SK response was not successful.', $exception->getMessage());
            }
        }
    }

    public function test_epostman_still_rejects_non_object_xml_and_empty_acknowledgements(): void
    {
        Http::fake([
            'https://testpostman.slovakodata.com/sapi/document/receive/synthetic-document' => Http::response('<Invoice/>', 200),
            'https://testpostman.slovakodata.com/sapi/document/receive/synthetic-document/acknowledge' => Http::response('', 204),
        ]);
        $client = app(SapiSkClient::class);
        foreach (['receivedDocument', 'acknowledgeReceived'] as $method) {
            try {
                $client->$method('synthetic-token', '0245:1234567890', 'synthetic-document', 'https://testpostman.slovakodata.com');
                $this->fail('ePostman must retain its strict JSON-object response contract.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('SAPI-SK response is not a successful JSON object.', $exception->getMessage());
            }
        }
    }
}

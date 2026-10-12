<?php

namespace Tests\Unit;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BtcPayInvoiceListSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => '']);
    }

    public function test_invoice_query_uses_boolean_literals_and_restores_an_empty_original_key(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $client = new BtcPayClient;
        $result = (new InvoiceService($client))->listInvoices('store', ['includeArchived' => true, 'includePaymentMethods' => false], 0, 100, 'merchant-key');
        $this->assertSame([], $result);
        $this->assertSame('', $client->getApiKey());
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['includeArchived'] === 'true' && $query['includePaymentMethods'] === 'false'
                && $request->hasHeader('Authorization', 'Bearer merchant-key');
        });
    }

    public static function invalidBodies(): array
    {
        return [['null'], ['{}'], ['{"unexpected":[]}'], ['<html>proxy error</html>'], ['true'], ['"[]"']];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_success_response_is_never_an_empty_invoice_list(string $body): void
    {
        Http::fake(['*' => Http::response($body, 200)]);
        $client = new BtcPayClient;
        try {
            (new InvoiceService($client))->listInvoices('store', [], 0, 100, 'merchant-key');
            $this->fail('Malformed invoice response must fail closed.');
        } catch (BtcPayException $e) {
            $this->assertSame('BTCPay returned an invalid JSON list.', $e->getMessage());
            $this->assertSame('', $client->getApiKey());
        }
    }
}

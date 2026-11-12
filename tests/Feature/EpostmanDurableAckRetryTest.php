<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Models\User;
use App\Services\Invoicing\Efaktura\EfakturaInboundService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpostmanDurableAckRetryTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(storage_path('framework/testing'), '.env.epostman-durable-');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'database.connections.sqlite.url' => null,
            'efaktura.enabled' => true,
            'invoicing.local_first' => true,
        ]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_an_independent_connection_observes_committed_original_before_ack_and_failed_ack_does_not_roll_it_back(): void
    {
        $company = Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic durable receiver',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'country' => 'SK',
            'tax_id' => '1234567890',
            'vat_status' => 'none',
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_inbound_enabled' => true,
                'efaktura_sapi_base_url' => 'https://testpostman.slovakodata.com',
                'efaktura_sapi_client_id' => 'synthetic-client',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-secret'),
            ],
        ]);
        $xml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>SYNTHETIC-DURABLE</cbc:ID><cbc:IssueDate>2026-11-12</cbc:IssueDate><cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode><cac:AccountingCustomerParty><cac:Party><cbc:EndpointID schemeID="0245">1234567890</cbc:EndpointID></cac:Party></cac:AccountingCustomerParty><cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="EUR">1.00</cbc:PayableAmount></cac:LegalMonetaryTotal></Invoice>';
        $detailCalls = 0;
        $ackCalls = 0;
        Http::fake(function ($request) use ($xml, &$detailCalls, &$ackCalls) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                $ackCalls++;
                $this->assertSame(1, DB::transactionLevel());
                $independent = new \PDO('sqlite:'.$this->databasePath);
                $rows = $independent->query('SELECT ubl_encrypted, original_sha256, acknowledged_at FROM efaktura_inbound_receipts')->fetchAll(\PDO::FETCH_ASSOC);
                $this->assertCount(1, $rows);
                $this->assertSame($xml, Crypt::decryptString($rows[0]['ubl_encrypted']));
                $this->assertSame(hash('sha256', $xml), $rows[0]['original_sha256']);
                $this->assertNull($rows[0]['acknowledged_at']);

                return $ackCalls === 1 ? Http::response(['error' => 'synthetic-retry'], 503)
                    : Http::response(['documentId' => 'synthetic-durable', 'status' => 'ACKNOWLEDGED']);
            }
            if (str_ends_with($request->url(), '/receive/synthetic-durable')) {
                $detailCalls++;
                $this->assertSame(0, DB::transactionLevel());

                return Http::response(['metadata' => ['documentId' => 'synthetic-durable', 'receiverParticipantId' => '0245:1234567890'], 'payload' => $xml, 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }

            return Http::response(['documents' => [['documentId' => 'synthetic-durable', 'receiverParticipantId' => '0245:1234567890']]]);
        });
        $first = app(EfakturaInboundService::class)->pollCompany($company);
        $this->assertSame(0, $first['acknowledged']);
        $this->assertSame(1, $first['failed']);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $this->assertNull($receipt->acknowledged_at);
        $this->assertSame($xml, Crypt::decryptString($receipt->ubl_encrypted));
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
        $this->assertSame(1, $detailCalls);
        $this->assertSame(2, $ackCalls);
        $this->assertSame(1, EfakturaInboundReceipt::count());
    }
}

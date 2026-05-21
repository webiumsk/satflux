<?php

namespace Tests\Unit\Services\Raffle;

use App\Services\Raffle\RafflePayloadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RafflePayloadServiceTest extends TestCase
{
    private RafflePayloadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RafflePayloadService;
    }

    public function test_validates_sats_pricing(): void
    {
        $request = Request::create('/', 'POST', [
            'name' => 'Test',
            'ticketPriceSats' => 1000,
        ]);

        $payload = $this->service->validate($request);

        $this->assertSame('Test', $payload['name']);
        $this->assertSame(1000, $payload['ticketPriceSats']);
    }

    public function test_validates_fiat_pricing(): void
    {
        $request = Request::create('/', 'POST', [
            'name' => 'Euro',
            'ticketCurrency' => 'eur',
            'ticketPrice' => 5,
        ]);

        $payload = $this->service->validate($request);

        $this->assertSame('EUR', $payload['ticketCurrency']);
        $this->assertEquals(5, $payload['ticketPrice']);
    }

    public function test_requires_pricing(): void
    {
        $request = Request::create('/', 'POST', ['name' => 'No price']);

        $this->expectException(ValidationException::class);

        $this->service->validate($request);
    }
}

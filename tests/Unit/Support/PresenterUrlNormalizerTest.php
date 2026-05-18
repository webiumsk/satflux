<?php

namespace Tests\Unit\Support;

use App\Support\PresenterUrlNormalizer;
use Tests\TestCase;

class PresenterUrlNormalizerTest extends TestCase
{
    private PresenterUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new PresenterUrlNormalizer;
    }

    public function test_rebuilds_relative_presenter_url(): void
    {
        config(['services.btcpay.public_url' => 'https://pay.example.com']);

        $result = $this->normalizer->normalize([
            'token' => 'abc',
            'presenterUrl' => '/raffle/raffle-1/present?token=abc',
        ], 'raffle-1');

        $this->assertSame(
            'https://pay.example.com/raffle/raffle-1/present?token=abc',
            $result['presenterUrl']
        );
    }

    public function test_keeps_valid_absolute_url_with_matching_origin_and_path(): void
    {
        config(['services.btcpay.public_url' => 'https://pay.example.com']);

        $url = 'https://pay.example.com/raffle/raffle-1/present?token=abc';
        $result = $this->normalizer->normalize([
            'token' => 'abc',
            'presenterUrl' => $url,
        ], 'raffle-1');

        $this->assertSame($url, $result['presenterUrl']);
    }

    public function test_rebuilds_when_path_only_partially_matches(): void
    {
        config(['services.btcpay.public_url' => 'https://pay.example.com']);

        $result = $this->normalizer->normalize([
            'token' => 'abc',
            'presenterUrl' => 'https://pay.example.com/prefix/raffle/raffle-1/present?token=abc',
        ], 'raffle-1');

        $this->assertSame(
            'https://pay.example.com/raffle/raffle-1/present?token=abc',
            $result['presenterUrl']
        );
    }

    public function test_rebuilds_when_raffle_id_in_path_differs(): void
    {
        config(['services.btcpay.public_url' => 'https://pay.example.com']);

        $result = $this->normalizer->normalize([
            'token' => 'abc',
            'presenterUrl' => 'https://pay.example.com/raffle/other-id/present?token=abc',
        ], 'raffle-1');

        $this->assertSame(
            'https://pay.example.com/raffle/raffle-1/present?token=abc',
            $result['presenterUrl']
        );
    }

    public function test_rebuilds_when_origin_differs(): void
    {
        config(['services.btcpay.public_url' => 'https://pay.example.com']);

        $result = $this->normalizer->normalize([
            'token' => 'xyz',
            'presenterUrl' => 'http://btcpay-internal:49392/raffle/raffle-1/present?token=xyz',
        ], 'raffle-1');

        $this->assertSame(
            'https://pay.example.com/raffle/raffle-1/present?token=xyz',
            $result['presenterUrl']
        );
    }
}

<?php

namespace Tests\Feature;

use App\Support\Invoicing\QrPngRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentQrPrivacyTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==';

    #[Test]
    public function existing_local_node_runtime_generates_a_real_payment_png_without_http(): void
    {
        if (getenv('PAYMENT_QR_NATIVE_TEST') !== '1') {
            $this->markTestSkipped('Set PAYMENT_QR_NATIVE_TEST=1 in a verified environment with the existing Node QR dependencies.');
        }
        Http::fake();
        $output = QrPngRenderer::dataUri('https://satflux.test/pay/e/synthetic-capability', 180, allowExternalFallback: false);
        $this->assertIsString($output);
        $png = base64_decode(substr($output, 22), true);
        $dimensions = getimagesizefromstring($png);
        $this->assertSame([180, 180], [$dimensions[0], $dimensions[1]]);
        Http::assertNothingSent();
    }

    #[Test]
    public function private_payment_capabilities_use_local_stdin_and_never_external_http(): void
    {
        Http::fake();
        Process::fake(['*' => Process::result(output: self::PNG)]);
        $capability = 'https://satflux.test/pay/e/'.str_repeat('a', 64);
        $this->assertSame(self::PNG, QrPngRenderer::dataUri($capability, 180, allowExternalFallback: false));
        Process::assertRan(fn ($process) => $process->command === ['node', base_path('scripts/render-payment-qr.cjs')]
            && json_decode($process->input, true) === ['data' => $capability, 'size' => 180]
            && $process->timeout === 5);
        Http::assertNothingSent();
    }

    #[Test]
    public function local_failure_does_not_send_the_private_capability_to_the_http_fallback(): void
    {
        Http::fake();
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->assertNull(QrPngRenderer::dataUri('https://satflux.test/pay/e/private', 180, allowExternalFallback: false));
        Http::assertNothingSent();
    }

    #[Test]
    public function malformed_or_oversized_local_output_is_rejected(): void
    {
        Http::fake();
        foreach (['data:image/png;base64,'.base64_encode('not a PNG'), str_repeat('a', 1_048_577)] as $output) {
            Process::fake(['*' => Process::result(output: $output)]);
            $this->assertNull(QrPngRenderer::dataUri('private', 180, allowExternalFallback: false));
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function existing_bank_qr_http_fallback_is_preserved_without_starting_a_node_process(): void
    {
        Process::fake();
        Http::fake(['*' => Http::response(base64_decode(substr(self::PNG, 22)), 200)]);
        $this->assertSame(self::PNG, QrPngRenderer::dataUri('synthetic-bank-data'));
        Process::assertNothingRan();
        Http::assertSent(fn ($request) => $request['data'] === 'synthetic-bank-data');
    }
}

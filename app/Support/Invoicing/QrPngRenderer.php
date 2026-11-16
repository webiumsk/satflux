<?php

namespace App\Support\Invoicing;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Shared QR PNG rendering for payment QRs (PayBySquare, EPC, Swiss) -
 * local chillerlan/Node renderers, with the historical bank-only HTTP
 * fallback available to callers that explicitly permit it.
 */
final class QrPngRenderer
{
    /** @return string|null data:image/png;base64,... for embedding in PDF */
    public static function dataUri(string $data, int $size = 200, bool $allowExternalFallback = true): ?string
    {
        if (class_exists(QRCode::class)) {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'scale' => max(4, (int) floor($size / 25)),
                'imageBase64' => true,
            ]);
            $qr = new QRCode($options);

            return $qr->render($data);
        }

        if (! $allowExternalFallback) {
            try {
                // Laravel's Process wrapper uses Symfony Process with an argument array.
                // Capabilities travel only over stdin, never argv, a shell, or external QR APIs.
                $result = Process::input(json_encode(['data' => $data, 'size' => $size], JSON_THROW_ON_ERROR))
                    ->timeout(5)->run(['node', base_path('scripts/render-payment-qr.cjs')]);
                $rawOutput = $result->output();
                $output = trim($rawOutput);
                if ($result->successful() && strlen($rawOutput) <= 1_048_576
                    && str_starts_with($output, 'data:image/png;base64,')) {
                    $png = base64_decode(substr($output, 22), true);
                    if (is_string($png) && str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                        $dimensions = @getimagesizefromstring($png);
                        if ($dimensions && $dimensions[0] > 0 && $dimensions[0] <= 512 && $dimensions[1] > 0 && $dimensions[1] <= 512) {
                            return $output;
                        }
                    }
                }
            } catch (\Throwable) {
                // Missing local runtime or invalid renderer output: fail closed for private payment links.
            }

            return null;
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)->get(
                'https://api.qrserver.com/v1/create-qr-code/',
                ['size' => "{$size}x{$size}", 'data' => $data],
            );
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($response->body());
    }
}

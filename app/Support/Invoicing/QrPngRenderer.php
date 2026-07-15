<?php

namespace App\Support\Invoicing;

/**
 * Shared QR PNG rendering for payment QRs (PayBySquare, EPC, Swiss) -
 * chillerlan locally, with the qrserver.com fallback the PayBySquare
 * generator historically used.
 */
final class QrPngRenderer
{
    /** @return string|null data:image/png;base64,... for embedding in PDF */
    public static function dataUri(string $data, int $size = 200): ?string
    {
        if (class_exists(\chillerlan\QRCode\QRCode::class)) {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'scale' => max(4, (int) floor($size / 25)),
                'imageBase64' => true,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($options);

            return $qr->render($data);
        }

        $url = 'https://api.qrserver.com/v1/create-qr-code/?'.http_build_query([
            'size' => "{$size}x{$size}",
            'data' => $data,
        ]);
        $png = @file_get_contents($url);
        if ($png === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}

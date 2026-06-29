<?php

namespace App\Support\Invoicing;

/**
 * Tiny PNG icons for invoice PDF footers (dompdf renders PNG reliably, not inline SVG).
 */
final class InvoiceFooterIcons
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function dataUri(string $type): string
    {
        if (! isset(self::$cache[$type])) {
            self::$cache[$type] = 'data:image/png;base64,'.base64_encode(self::render($type));
        }

        return self::$cache[$type];
    }

    private static function render(string $type): string
    {
        $size = 20;
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $border = imagecolorallocate($image, 156, 163, 175);
        $foreground = imagecolorallocate($image, 107, 114, 128);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, 10, 10, 18, 18, $white);
        imageellipse($image, 10, 10, 18, 18, $border);

        match ($type) {
            'phone' => self::drawPhone($image, $foreground, $white),
            'web' => self::drawWeb($image, $foreground),
            default => self::drawEmail($image, $foreground, $white),
        };

        ob_start();
        imagepng($image);
        $png = ob_get_clean() ?: '';
        imagedestroy($image);

        return $png;
    }

    private static function drawPhone(\GdImage $image, int $foreground, int $white): void
    {
        imagefilledrectangle($image, 7, 4, 12, 14, $foreground);
        imagefilledrectangle($image, 8, 5, 11, 6, $white);
        imagefilledellipse($image, 9, 13, 3, 3, $white);
    }

    private static function drawWeb(\GdImage $image, int $foreground): void
    {
        imageellipse($image, 10, 10, 10, 10, $foreground);
        imageline($image, 5, 10, 15, 10, $foreground);
        imagearc($image, 10, 10, 6, 10, 0, 180, $foreground);
    }

    private static function drawEmail(\GdImage $image, int $foreground, int $white): void
    {
        imagefilledrectangle($image, 5, 7, 15, 13, $foreground);
        imagefilledpolygon($image, [5, 7, 10, 11, 15, 7], $white);
    }
}

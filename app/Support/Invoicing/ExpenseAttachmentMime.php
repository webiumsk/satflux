<?php

namespace App\Support\Invoicing;

/**
 * Content type policy for user-uploaded expense attachments.
 *
 * The type is derived from the (validated) extension, never sniffed from the
 * bytes: an ".xml" upload containing SVG or HTML would otherwise be served as
 * active content on our origin. Only passive formats may render inline.
 */
final class ExpenseAttachmentMime
{
    private const BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'isdoc' => 'application/xml',
        'xml' => 'application/xml',
    ];

    private const INLINE = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public static function forFilename(?string $filename): string
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        return self::BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }

    public static function isInline(string $mime): bool
    {
        return in_array($mime, self::INLINE, true);
    }
}

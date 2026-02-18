<?php

namespace App\Support;

/**
 * Maps allowed image MIME types to file extensions. Used when storing uploads
 * so the stored filename extension is server-derived, not client-controlled.
 */
final class ImageExtensionFromMime
{
    /**
     * MIME types allowed by UploadRestaurantMediaRequest → extension.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Return file extension for the given MIME type. Falls back to 'jpg' if unknown.
     */
    public static function extension(string $mime): string
    {
        $mime = strtolower(trim($mime));

        return self::MAP[$mime] ?? 'jpg';
    }
}

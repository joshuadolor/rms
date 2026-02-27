<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Resize images to fit within max dimensions (proportional, no cropping).
 * Uses PHP GD. Only resizes when width or height exceeds the given maximums.
 */
final class ImageResizer
{
    /**
     * Resize image to fit within max width and height. Preserves aspect ratio.
     * If the image is already within bounds, returns the original file contents.
     *
     * @param  int  $maxWidth  Maximum width in pixels.
     * @param  int  $maxHeight  Maximum height in pixels.
     * @return string Binary image content (same format as input).
     *
     * @throws \InvalidArgumentException When the file is not a supported image or GD cannot load it.
     */
    public static function resizeToFit(UploadedFile $file, int $maxWidth, int $maxHeight): string
    {
        if (! function_exists('imagecreatefrompng')) {
            throw new \InvalidArgumentException(
                'PHP GD extension with PNG support is required for image uploads. ' .
                'Docker: rebuild the API image so GD is installed (api/Dockerfile already includes it): docker compose build api && docker compose up -d api. ' .
                'Local PHP: install the GD extension (e.g. apt install php8.2-gd, or on macOS: brew install php and ensure gd is enabled in php.ini).'
            );
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new \InvalidArgumentException(__('Could not read uploaded image.'));
        }

        $mime = strtolower($file->getMimeType());
        $image = self::loadImage($path, $mime);
        if ($image === false) {
            throw new \InvalidArgumentException(__('Unsupported or invalid image.'));
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        if ($width === false || $height === false) {
            \imagedestroy($image);
            throw new \InvalidArgumentException(__('Could not read image dimensions.'));
        }

        $newWidth = $width;
        $newHeight = $height;
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height, 1.0);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
            $newWidth = max(1, $newWidth);
            $newHeight = max(1, $newHeight);
        }

        if ($newWidth === $width && $newHeight === $height) {
            $content = (string) file_get_contents($path);
            \imagedestroy($image);

            return $content;
        }

        $resized = \imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            \imagedestroy($image);
            throw new \InvalidArgumentException(__('Could not create resized image.'));
        }

        // Preserve transparency for PNG and GIF.
        if (in_array($mime, ['image/png', 'image/gif'], true)) {
            \imagealphablending($resized, false);
            \imagesavealpha($resized, true);
            $transparent = \imagecolorallocatealpha($resized, 255, 255, 255, 127);
            if ($transparent !== false) {
                \imagefill($resized, 0, 0, $transparent);
            }
        }

        $success = \imagecopyresampled(
            $resized,
            $image,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );
        \imagedestroy($image);
        if (! $success) {
            \imagedestroy($resized);
            throw new \InvalidArgumentException(__('Could not resize image.'));
        }

        $content = self::encodeImage($resized, $mime);
        \imagedestroy($resized);

        return $content;
    }

    /**
     * @return \GdImage|false
     */
    private static function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @\imagecreatefromjpeg($path),
            'image/png' => @\imagecreatefrompng($path),
            'image/gif' => @\imagecreatefromgif($path),
            'image/webp' => @\imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * @return string Binary content.
     */
    private static function encodeImage(\GdImage $image, string $mime): string
    {
        ob_start();
        try {
            $result = match ($mime) {
                'image/jpeg', 'image/jpg' => \imagejpeg($image, null, 90),
                'image/png' => \imagepng($image, null, 9),
                'image/gif' => \imagegif($image, null),
                'image/webp' => \imagewebp($image, null, 90),
                default => false,
            };
            if ($result === false) {
                ob_end_clean();
                throw new \InvalidArgumentException(__('Could not encode image.'));
            }
        } finally {
            $content = ob_get_clean();
        }

        return (string) $content;
    }
}

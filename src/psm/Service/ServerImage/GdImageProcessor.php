<?php

declare(strict_types=1);

namespace psm\Service\ServerImage;

use GdImage;
use InvalidArgumentException;

final class GdImageProcessor implements ImageProcessorInterface
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_DIMENSION = 512;

    public function process(string $temporaryPath): ProcessedImage
    {
        if (!is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new InvalidArgumentException('The uploaded image is not readable.');
        }

        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('The image must not exceed 5 MiB.');
        }

        $bytes = file_get_contents($temporaryPath);
        $info = @getimagesize($temporaryPath);
        if ($bytes === false || $info === false || !isset($info[0], $info[1], $info[2])) {
            throw new InvalidArgumentException('The uploaded file is not a valid image.');
        }

        $type = (int) $info[2];
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Only JPEG, PNG, and WebP images are supported.');
        }
        if (!$this->hasExactImageContainer($bytes, $type)) {
            throw new InvalidArgumentException('The image contains data outside its image container.');
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($temporaryPath),
            IMAGETYPE_PNG => @imagecreatefrompng($temporaryPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($temporaryPath),
        };
        if (!$source instanceof GdImage) {
            throw new InvalidArgumentException('The image could not be decoded.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('The image dimensions are invalid.');
        }

        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas instanceof GdImage) {
            throw new InvalidArgumentException('The image could not be normalized.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        $copied = imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );
        if (!$copied) {
            throw new InvalidArgumentException('The image could not be resized.');
        }

        ob_start();
        $encoded = imagewebp($canvas, null, 82);
        $output = ob_get_clean();
        if (!$encoded || !is_string($output) || $output === '') {
            throw new InvalidArgumentException('The image could not be encoded as WebP.');
        }

        return new ProcessedImage($output, 'webp', $targetWidth, $targetHeight);
    }

    private function hasExactImageContainer(string $bytes, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => str_ends_with($bytes, "\xFF\xD9"),
            IMAGETYPE_PNG => str_ends_with($bytes, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"),
            IMAGETYPE_WEBP => strlen($bytes) >= 12
                && substr($bytes, 0, 4) === 'RIFF'
                && substr($bytes, 8, 4) === 'WEBP'
                && unpack('Vsize', substr($bytes, 4, 4))['size'] + 8 === strlen($bytes),
            default => false,
        };
    }
}

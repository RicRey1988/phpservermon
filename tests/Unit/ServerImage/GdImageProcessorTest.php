<?php

declare(strict_types=1);

namespace Tests\Unit\ServerImage;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use psm\Service\ServerImage\GdImageProcessor;

final class GdImageProcessorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psm-image-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    /** @return iterable<string, array{string}> */
    public static function imageTypes(): iterable
    {
        yield 'jpeg' => ['jpeg'];
        yield 'png' => ['png'];
        yield 'webp' => ['webp'];
    }

    #[DataProvider('imageTypes')]
    public function testNormalizesSupportedImagesToBoundedWebp(string $type): void
    {
        $path = $this->createImage($type, 800, 400);

        $image = (new GdImageProcessor())->process($path);
        $info = getimagesizefromstring($image->bytes);

        self::assertSame('webp', $image->extension);
        self::assertSame(512, $image->width);
        self::assertSame(256, $image->height);
        self::assertIsArray($info);
        self::assertSame(IMAGETYPE_WEBP, $info[2]);
    }

    public function testRejectsMalformedAndPolyglotFiles(): void
    {
        $processor = new GdImageProcessor();
        $malformed = $this->directory . '/malformed.png';
        file_put_contents($malformed, 'not an image');

        try {
            $processor->process($malformed);
            self::fail('Malformed bytes must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $polyglot = $this->createImage('png', 10, 10);
        file_put_contents($polyglot, '<?php echo 1;', FILE_APPEND);

        $this->expectException(InvalidArgumentException::class);
        $processor->process($polyglot);
    }

    public function testRejectsFilesOverFiveMibBeforeDecode(): void
    {
        $path = $this->directory . '/large.bin';
        file_put_contents($path, str_repeat('x', GdImageProcessor::MAX_BYTES + 1));

        $this->expectException(InvalidArgumentException::class);
        (new GdImageProcessor())->process($path);
    }

    private function createImage(string $type, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(\GdImage::class, $image);
        $color = imagecolorallocate($image, 48, 87, 232);
        imagefill($image, 0, 0, $color);
        $path = $this->directory . '/fixture.' . $type;

        $saved = match ($type) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
            default => false,
        };
        self::assertTrue($saved);

        return $path;
    }
}

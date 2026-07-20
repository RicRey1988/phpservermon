<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use psm\Service\Database;
use psm\Service\ServerImage\ImageProcessorInterface;
use psm\Service\ServerImage\ProcessedImage;
use psm\Service\ServerImage\ServerImageManager;
use psm\Service\ServerImage\ServerImageStorage;

final class ServerImageFormTest extends TestCase
{
    private string $directory;
    private RecordingImageDatabase $database;
    private ServerImageStorage $storage;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psm-form-image-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $this->database = new RecordingImageDatabase();
        $this->storage = new ServerImageStorage($this->directory, 'images', 'generic.svg');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testSuccessfulUploadUsesTheSavedServerIdAndPersistsMetadata(): void
    {
        $manager = $this->managerWith(new FixedImageProcessor('new-image'));

        $fileName = $manager->apply(42, ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], false);

        self::assertSame('server-42.webp', $fileName);
        self::assertSame('new-image', file_get_contents($this->directory . '/server-42.webp'));
        self::assertSame('server-42.webp', $this->database->saved[0]['data']['image_file']);
        self::assertNotEmpty($this->database->saved[0]['data']['image_updated_at']);
        self::assertSame(['server_id' => 42], $this->database->saved[0]['where']);
    }

    public function testNoUploadKeepsExistingImageAndReplacementOverwritesIt(): void
    {
        $this->storage->store(9, new ProcessedImage('old-image', 'webp', 1, 1));
        $manager = $this->managerWith(new FixedImageProcessor('new-image'));

        self::assertNull($manager->apply(9, ['error' => UPLOAD_ERR_NO_FILE], false));
        self::assertSame('old-image', file_get_contents($this->directory . '/server-9.webp'));
        self::assertSame([], $this->database->saved);

        $manager->apply(9, ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], false);
        self::assertSame('new-image', file_get_contents($this->directory . '/server-9.webp'));
    }

    public function testRemoveFlagDeletesImageAndClearsMetadata(): void
    {
        $this->storage->store(7, new ProcessedImage('old-image', 'webp', 1, 1));

        $result = $this->managerWith(new FixedImageProcessor('unused'))
            ->apply(7, ['error' => UPLOAD_ERR_NO_FILE], true);

        self::assertNull($result);
        self::assertFileDoesNotExist($this->directory . '/server-7.webp');
        self::assertSame(['image_file' => null, 'image_updated_at' => null], $this->database->saved[0]['data']);
    }

    public function testInvalidUploadDoesNotChangeExistingImageOrDatabasePath(): void
    {
        $this->storage->store(5, new ProcessedImage('old-image', 'webp', 1, 1));
        $manager = $this->managerWith(new RejectingImageProcessor());

        try {
            $manager->apply(5, ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], false);
            self::fail('Invalid upload must fail.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        self::assertSame('old-image', file_get_contents($this->directory . '/server-5.webp'));
        self::assertSame([], $this->database->saved);
    }

    public function testDeletingServerRemovesItsPhysicalImage(): void
    {
        $this->storage->store(3, new ProcessedImage('image', 'webp', 1, 1));

        $this->managerWith(new FixedImageProcessor('unused'))->deleteForServer(3);

        self::assertFileDoesNotExist($this->directory . '/server-3.webp');
    }

    public function testControllerAndTemplateIntegrateNativeMultipartUpload(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = file_get_contents($root . '/src/psm/Module/Server/Controller/ServerController.php');
        $template = file_get_contents($root . '/src/templates/default/module/server/server/update.tpl.html');
        self::assertIsString($controller);
        self::assertIsString($template);

        self::assertStringContainsString("\$_FILES['server_image']", $controller);
        self::assertStringContainsString('deleteForServer(', $controller);
        self::assertStringContainsString('enctype="multipart/form-data"', $template);
        self::assertStringContainsString('name="server_image"', $template);
        self::assertStringContainsString('name="remove_image"', $template);
        self::assertStringContainsString('image/jpeg,image/png,image/webp', $template);
        self::assertStringContainsString('data-dropzone', $template);
    }

    public function testDropzoneEnhancementProvidesPreviewWithoutReplacingNativeInput(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/static/';
        $javascript = file_get_contents($root . 'js/app-shell.js');
        $styles = file_get_contents($root . 'css/hs-monitor.css');
        self::assertIsString($javascript);
        self::assertIsString($styles);

        self::assertStringContainsString('[data-dropzone]', $javascript);
        self::assertStringContainsString('[data-image-preview]', $javascript);
        self::assertStringContainsString('.dropzone-preview', $styles);
    }

    private function managerWith(ImageProcessorInterface $processor): ServerImageManager
    {
        return new ServerImageManager($this->database, $processor, $this->storage);
    }
}

final class RecordingImageDatabase extends Database
{
    /** @var list<array{table: string, data: array<string, mixed>, where: mixed}> */
    public array $saved = [];

    public function save($table, array $data, $where = null)
    {
        $this->saved[] = ['table' => $table, 'data' => $data, 'where' => $where];

        return 1;
    }
}

final readonly class FixedImageProcessor implements ImageProcessorInterface
{
    public function __construct(private string $bytes)
    {
    }

    public function process(string $temporaryPath): ProcessedImage
    {
        return new ProcessedImage($this->bytes, 'webp', 1, 1);
    }
}

final class RejectingImageProcessor implements ImageProcessorInterface
{
    public function process(string $temporaryPath): ProcessedImage
    {
        throw new InvalidArgumentException('invalid');
    }
}

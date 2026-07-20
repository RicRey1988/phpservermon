<?php

declare(strict_types=1);

namespace psm\Service\ServerImage;

use InvalidArgumentException;
use psm\Service\Database;

final readonly class ServerImageManager
{
    public function __construct(
        private Database $database,
        private ImageProcessorInterface $processor,
        private ServerImageStorage $storage,
    ) {
    }

    /**
     * @param array{error?: int, tmp_name?: string} $file
     */
    public function apply(int $serverId, array $file, bool $remove): ?string
    {
        if ($serverId < 1) {
            throw new InvalidArgumentException('A saved server is required before uploading an image.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_OK) {
            $temporaryPath = $file['tmp_name'] ?? '';
            if ($temporaryPath === '') {
                throw new InvalidArgumentException('The uploaded image is missing its temporary file.');
            }
            $processed = $this->processor->process($temporaryPath);
            $fileName = $this->storage->store($serverId, $processed);
            $this->database->save(
                $this->serverTable(),
                ['image_file' => $fileName, 'image_updated_at' => date('Y-m-d H:i:s')],
                ['server_id' => $serverId]
            );

            return $fileName;
        }

        if ($error !== UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException($this->uploadErrorMessage($error));
        }
        if (!$remove) {
            return null;
        }

        $this->storage->delete($serverId);
        $this->database->save(
            $this->serverTable(),
            ['image_file' => null, 'image_updated_at' => null],
            ['server_id' => $serverId]
        );

        return null;
    }

    public function deleteForServer(int $serverId): void
    {
        $this->storage->delete($serverId);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image exceeds the upload limit.',
            UPLOAD_ERR_PARTIAL => 'The image upload was incomplete.',
            default => 'The image upload could not be completed.',
        };
    }

    private function serverTable(): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . 'servers';
    }
}

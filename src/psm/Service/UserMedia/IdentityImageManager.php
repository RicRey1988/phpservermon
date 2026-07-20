<?php

declare(strict_types=1);

namespace psm\Service\UserMedia;

use InvalidArgumentException;
use psm\Service\ServerImage\ImageProcessorInterface;

final readonly class IdentityImageManager
{
    public function __construct(private ImageProcessorInterface $processor, private UserMediaStorage $storage)
    {
    }

    /** @param array{error?:int,tmp_name?:string} $file */
    public function applyLogo(array $file, bool $remove): ?string
    {
        return $this->apply($file, $remove, fn ($image) => $this->storage->storeLogo($image), fn () => $this->storage->deleteLogo());
    }

    /** @param array{error?:int,tmp_name?:string} $file */
    public function applyAvatar(int $userId, array $file, bool $remove): ?string
    {
        return $this->apply($file, $remove, fn ($image) => $this->storage->storeAvatar($userId, $image), fn () => $this->storage->deleteAvatar($userId));
    }

    /** @param array{error?:int,tmp_name?:string} $file */
    private function apply(array $file, bool $remove, callable $store, callable $delete): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_OK) {
            $temporary = (string) ($file['tmp_name'] ?? '');
            if ($temporary === '') {
                throw new InvalidArgumentException('La imagen subida no tiene archivo temporal.');
            }
            return $store($this->processor->process($temporary));
        }
        if ($error !== UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite de carga.',
                UPLOAD_ERR_PARTIAL => 'La carga de la imagen quedó incompleta.',
                default => 'No se pudo completar la carga de la imagen.',
            });
        }
        if ($remove) {
            $delete();
        }
        return null;
    }
}

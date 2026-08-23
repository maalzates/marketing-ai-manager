<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Under the `drive.file` scope a permanently deleted file and a file this app was never
 * granted are the same 404 — neither is recoverable without the user re-linking.
 */
class DriveFileNotFoundException extends ClientException
{
    public static function withFileId(string $fileId): self
    {
        $exception = new self('The file no longer exists in Google Drive.', Response::HTTP_NOT_FOUND);
        $exception->context = ['drive_file_id' => $fileId];

        return $exception;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Clients;

use App\Modules\Assets\Domain\Exceptions\DriveFileNotFoundException;
use App\Modules\Assets\Domain\Exceptions\DriveOperationFailedException;
use App\Modules\Assets\Domain\Exceptions\ResumableUploadFailedException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google Drive v3, built per account by DriveClientFactory. Every method returns decoded
 * arrays; no Guzzle or PSR-7 type leaves this class, and no failure carries the bearer token.
 */
class DriveClient extends ApiClientAbstract
{
    public const string FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    private const string FILES_ENDPOINT = 'files';

    private const string FILE_ENDPOINT = 'files/%s';

    private const string FILE_FIELDS = 'id,name,mimeType,size,parents,trashed,explicitlyTrashed,videoMediaMetadata,imageMediaMetadata,webViewLink,createdTime,modifiedTime';

    private const string FOLDER_FIELDS = 'id,name,mimeType,parents,webViewLink';

    private const string LIST_FIELDS = 'nextPageToken,files(id,name,mimeType,parents,trashed)';

    /** Drive requires every resumable chunk but the last to be a multiple of 256 KiB. */
    private const int CHUNK_BYTES = 8 * 1024 * 1024;

    private const int RESUME_INCOMPLETE = 308;

    public function __construct(Client $client, private readonly string $uploadUrl)
    {
        parent::__construct($client);
    }

    public function createFolder(string $name, ?string $parentId = null): array
    {
        try {
            return $this->post(self::FILES_ENDPOINT, [
                RequestOptions::QUERY => ['fields' => self::FOLDER_FIELDS],
                RequestOptions::JSON => self::folderPayload($name, $parentId),
            ]);
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, 'create_folder');
        }
    }

    public function findFolder(string $name, ?string $parentId = null): ?array
    {
        try {
            return Arr::get($this->get(self::FILES_ENDPOINT, [
                'q' => self::folderQuery($name, $parentId),
                'fields' => self::LIST_FIELDS,
                'spaces' => 'drive',
                'pageSize' => 1,
            ]), 'files.0');
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, 'find_folder');
        }
    }

    /**
     * Drive has no prefix operator, so `contains` is the closest match and the caller checks the
     * prefix itself. Used to find a folder by an identifier that cannot change, when the rest of
     * the name is display text the user may edit.
     */
    public function findFolderByPrefix(string $prefix, ?string $parentId = null): ?array
    {
        try {
            return array_find(
                (array) Arr::get($this->get(self::FILES_ENDPOINT, [
                    'q' => self::prefixQuery($prefix, $parentId),
                    'fields' => self::LIST_FIELDS,
                    'spaces' => 'drive',
                    'pageSize' => 100,
                ]), 'files', []),
                fn (array $folder): bool => str_starts_with((string) Arr::get($folder, 'name'), $prefix),
            );
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, 'find_folder_by_prefix');
        }
    }

    /** Multipart is Drive's only single-request upload that also carries the name and parent. */
    public function uploadSimple(string $name, string $parentId, string $mimeType, string $sourcePath): array
    {
        $boundary = 'mam'.bin2hex(random_bytes(12));

        try {
            return $this->decode($this->client->request(Request::METHOD_POST, $this->uploadUrl, [
                RequestOptions::QUERY => ['uploadType' => 'multipart', 'fields' => self::FILE_FIELDS],
                RequestOptions::HEADERS => ['Content-Type' => 'multipart/related; boundary='.$boundary],
                RequestOptions::BODY => self::relatedBody($boundary, $name, $parentId, $mimeType, $sourcePath),
            ]));
        } catch (RequestException $exception) {
            throw $this->translateRequestException($exception, 'upload_simple');
        }
    }

    /**
     * Initiate → PUT chunks → final response. The bytes are read lazily one chunk at a time,
     * so a 300 MB reel never exists in memory.
     */
    public function uploadResumable(string $name, string $parentId, string $mimeType, string $sourcePath, int $sizeBytes): array
    {
        $session = $this->initiateResumableSession($name, $parentId, $mimeType, $sizeBytes);
        $handle = new LazyOpenStream($sourcePath, 'rb');

        for ($offset = 0; $offset < $sizeBytes; $offset += self::CHUNK_BYTES) {
            $response = $this->putChunk($session, $handle, $offset, min(self::CHUNK_BYTES, $sizeBytes - $offset), $sizeBytes, $mimeType);

            if ($response->getStatusCode() !== self::RESUME_INCOMPLETE) {
                return $this->decode($response);
            }
        }

        throw ResumableUploadFailedException::withStatus('final_chunk', Response::HTTP_BAD_GATEWAY, [
            'size_bytes' => $sizeBytes,
        ]);
    }

    /** Writes the bytes into $sink as they arrive; nothing is held in memory or on disk. */
    public function download(string $fileId, mixed $sink): void
    {
        try {
            $this->client->request(Request::METHOD_GET, sprintf(self::FILE_ENDPOINT, $fileId), [
                RequestOptions::QUERY => ['alt' => 'media'],
                RequestOptions::SINK => $sink,
            ]);
        } catch (RequestException $exception) {
            throw $this->translateRequestException($exception, 'download', ['drive_file_id' => $fileId]);
        }
    }

    /** Parents move through query parameters, never the body. */
    public function move(string $fileId, string $addParentId, string $removeParentId): array
    {
        try {
            return $this->patch(sprintf(self::FILE_ENDPOINT, $fileId), [
                RequestOptions::QUERY => [
                    'addParents' => $addParentId,
                    'removeParents' => $removeParentId,
                    'fields' => 'id,name,parents',
                ],
            ]);
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, 'move', ['drive_file_id' => $fileId]);
        }
    }

    public function rename(string $fileId, string $name): array
    {
        try {
            return $this->patch(sprintf(self::FILE_ENDPOINT, $fileId), [
                RequestOptions::QUERY => ['fields' => 'id,name,parents'],
                RequestOptions::JSON => ['name' => $name],
            ]);
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, 'rename', ['drive_file_id' => $fileId]);
        }
    }

    public function metadata(string $fileId): array
    {
        try {
            return $this->get(sprintf(self::FILE_ENDPOINT, $fileId), ['fields' => self::FILE_FIELDS]);
        } catch (ApiCallFailedException $exception) {
            throw $exception->getHttpStatusCode() === Response::HTTP_NOT_FOUND
                ? DriveFileNotFoundException::withFileId($fileId)
                : $this->translate($exception, 'metadata', ['drive_file_id' => $fileId]);
        }
    }

    /** True when the file is gone for this app: trashed, or a 404 that drive.file cannot tell from a deletion. */
    public function trashCheck(string $fileId): bool
    {
        try {
            return (bool) Arr::get($this->get(sprintf(self::FILE_ENDPOINT, $fileId), ['fields' => 'id,trashed']), 'trashed', false);
        } catch (ApiCallFailedException $exception) {
            return $exception->getHttpStatusCode() === Response::HTTP_NOT_FOUND
                ? true
                : throw $this->translate($exception, 'trash_check', ['drive_file_id' => $fileId]);
        }
    }

    private function initiateResumableSession(string $name, string $parentId, string $mimeType, int $sizeBytes): string
    {
        try {
            return $this->client->request(Request::METHOD_POST, $this->uploadUrl, [
                RequestOptions::QUERY => ['uploadType' => 'resumable', 'fields' => self::FILE_FIELDS],
                RequestOptions::HEADERS => [
                    'X-Upload-Content-Type' => $mimeType,
                    'X-Upload-Content-Length' => (string) $sizeBytes,
                ],
                RequestOptions::JSON => ['name' => $name, 'parents' => [$parentId]],
            ])->getHeaderLine('Location')
                ?: throw ResumableUploadFailedException::withStatus('initiate', Response::HTTP_BAD_GATEWAY);
        } catch (RequestException $exception) {
            throw $this->translateRequestException($exception, 'upload_resumable_initiate');
        }
    }

    private function putChunk(
        string $session,
        LazyOpenStream $handle,
        int $offset,
        int $length,
        int $total,
        string $mimeType,
    ): ResponseInterface {
        try {
            return $this->client->request(Request::METHOD_PUT, $session, [
                RequestOptions::HEADERS => [
                    'Content-Type' => $mimeType,
                    'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $offset + $length - 1, $total),
                ],
                RequestOptions::BODY => new LimitStream($handle, $length, $offset),
                // 308 is Drive asking for the next chunk, not an error.
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $exception) {
            throw $this->translateRequestException($exception, 'upload_resumable_chunk', ['offset' => $offset]);
        }
    }

    private function decode(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    private function translate(ApiCallFailedException $exception, string $operation, array $context = []): DriveOperationFailedException
    {
        return DriveOperationFailedException::masked(
            $exception,
            $operation,
            $exception->getHttpStatusCode(),
            Arr::get($exception->getContext(), 'response_body'),
            $context,
        );
    }

    private function translateRequestException(RequestException $exception, string $operation, array $context = []): DriveOperationFailedException
    {
        return DriveOperationFailedException::masked(
            $exception,
            $operation,
            $exception->getResponse()?->getStatusCode() ?? Response::HTTP_INTERNAL_SERVER_ERROR,
            json_decode((string) $exception->getResponse()?->getBody(), true),
            $context,
        );
    }

    private static function folderPayload(string $name, ?string $parentId): array
    {
        return $parentId === null
            ? ['name' => $name, 'mimeType' => self::FOLDER_MIME_TYPE]
            : ['name' => $name, 'mimeType' => self::FOLDER_MIME_TYPE, 'parents' => [$parentId]];
    }

    private static function folderQuery(string $name, ?string $parentId): string
    {
        return sprintf(
            "name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
            self::escape($name),
            self::FOLDER_MIME_TYPE,
            self::escape($parentId ?? 'root'),
        );
    }

    private static function prefixQuery(string $prefix, ?string $parentId): string
    {
        return sprintf(
            "name contains '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
            self::escape($prefix),
            self::FOLDER_MIME_TYPE,
            self::escape($parentId ?? 'root'),
        );
    }

    /** A folder name comes from a brand or an experiment title: unescaped it is a query injection. */
    private static function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private static function relatedBody(string $boundary, string $name, string $parentId, string $mimeType, string $sourcePath): AppendStream
    {
        return new AppendStream([
            Utils::streamFor(sprintf(
                "--%s\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n%s\r\n--%s\r\nContent-Type: %s\r\n\r\n",
                $boundary,
                json_encode(['name' => $name, 'parents' => [$parentId]]),
                $boundary,
                $mimeType,
            )),
            new LazyOpenStream($sourcePath, 'rb'),
            Utils::streamFor(sprintf("\r\n--%s--", $boundary)),
        ]);
    }
}

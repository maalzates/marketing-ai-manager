<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Assets\Domain\ValueObjects\AssetLocation;
use App\Modules\Assets\Infrastructure\Clients\DriveClient;
use Illuminate\Support\Arr;

/**
 * Maintains `/MarketingManager/{Marca}/{Estrategia}/{EXP-042 – tema}/` and the per-brand
 * `_inbox/`. Every level is find-or-create rather than create: the user owns this Drive and
 * may have renamed, moved or restored a folder between two uploads.
 */
readonly class DriveFolderService
{
    public const string ROOT_FOLDER = 'MarketingManager';

    public const string INBOX_FOLDER = '_inbox';

    public function __construct(private DriveClientFactoryInterface $clients) {}

    public function folderFor(int $accountId, AssetLocation $location): string
    {
        return $location->isInbox()
            ? $this->inboxFor($accountId, $location->brandName)
            : $this->experimentFolder($this->clients->forAccount($accountId), $location);
    }

    public function inboxFor(int $accountId, string $brandName): string
    {
        return $this->descend($this->clients->forAccount($accountId), [self::ROOT_FOLDER, $brandName, self::INBOX_FOLDER]);
    }

    /**
     * The folder is matched on the experiment code, which never changes, and only created with the
     * title appended. Matching on the full name would orphan the folder the first time a user
     * edits the title.
     */
    private function experimentFolder(DriveClient $client, AssetLocation $location): string
    {
        $parentId = $this->strategyFolder($client, $location);

        return (string) Arr::get(
            $client->findFolderByPrefix((string) $location->experimentCode, $parentId)
                ?? $client->createFolder((string) $location->experimentFolderName, $parentId),
            'id',
        );
    }

    private function strategyFolder(DriveClient $client, AssetLocation $location): string
    {
        return $this->descend($client, [
            self::ROOT_FOLDER,
            $location->brandName,
            $location->strategyName ?? self::INBOX_FOLDER,
        ]);
    }

    /**
     * @param  list<string>  $path
     */
    private function descend(DriveClient $client, array $path): string
    {
        return array_reduce(
            $path,
            fn (?string $parentId, string $name): string => $this->findOrCreate($client, $name, $parentId),
        );
    }

    private function findOrCreate(DriveClient $client, string $name, ?string $parentId): string
    {
        return (string) Arr::get(
            $client->findFolder($name, $parentId) ?? $client->createFolder($name, $parentId),
            'id',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Repositories;

use App\Modules\Content\Application\DTO\AudienceSnapshotDTO;
use App\Modules\Content\Domain\Contracts\AudienceSnapshotRepositoryInterface;
use App\Modules\Content\Domain\Exceptions\ContentPersistenceFailedException;
use App\Modules\Content\Infrastructure\Persistence\ChannelAudienceSnapshot;
use Throwable;

readonly class AudienceSnapshotRepository implements AudienceSnapshotRepositoryInterface
{
    public function __construct(private ChannelAudienceSnapshot $model) {}

    public function record(AudienceSnapshotDTO $dto): ChannelAudienceSnapshot
    {
        try {
            return $this->model->newQuery()->updateOrCreate(
                [
                    'account_id' => $dto->accountId,
                    'platform' => $dto->platform,
                    'date' => $dto->date->toDateString(),
                ],
                [
                    'followers_count' => $dto->followersCount,
                    'follows_count' => $dto->followsCount,
                    'media_count' => $dto->mediaCount,
                    'raw' => $dto->raw,
                ],
            );
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'platform' => $dto->platform->value,
                'date' => $dto->date->toDateString(),
            ]);
        }
    }
}

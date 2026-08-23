<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Application\DTO\AudienceSnapshotDTO;
use App\Modules\Content\Infrastructure\Persistence\ChannelAudienceSnapshot;

interface AudienceSnapshotRepositoryInterface
{
    public function record(AudienceSnapshotDTO $dto): ChannelAudienceSnapshot;
}

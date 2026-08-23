<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Application\DTO\AudienceSnapshotDTO;
use App\Modules\Content\Application\DTO\ChannelCommentDTO;
use App\Modules\Content\Application\DTO\ChannelMediaSpecDTO;
use App\Modules\Content\Application\DTO\ChannelMetricsDTO;
use App\Modules\Content\Application\DTO\PublishingLimitDTO;
use App\Modules\Content\Application\DTO\PublishRequestDTO;
use App\Modules\Content\Application\DTO\PublishResultDTO;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Support\Collection;

/**
 * One publishing channel. The domain knows only this contract: which platform a piece goes
 * to is an attribute of its experiment, never a branch in a service. A channel whose API
 * cannot publish answers `false` to `supportsPublishing()` and the piece falls back to a
 * manual reminder.
 */
interface ChannelProviderInterface
{
    public function platform(): ExperimentPlatform;

    /** Which stored credential this channel runs on, so a channel is only reached when it is connected. */
    public function credentialProvider(): IntegrationProvider;

    public function supportsPublishing(): bool;

    public function mediaSpec(ContentFormat $format): ChannelMediaSpecDTO;

    public function publish(PublishRequestDTO $request): PublishResultDTO;

    public function metrics(int $accountId, string $externalPostId, ContentFormat $format): ChannelMetricsDTO;

    /** @return Collection<int, ChannelCommentDTO> */
    public function comments(int $accountId, string $externalPostId): Collection;

    public function publishingLimit(int $accountId): PublishingLimitDTO;

    public function audienceSnapshot(int $accountId): AudienceSnapshotDTO;
}

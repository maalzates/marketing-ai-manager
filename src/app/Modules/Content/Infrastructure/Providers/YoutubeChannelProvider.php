<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Providers;

use App\Modules\Content\Application\DTO\AudienceSnapshotDTO;
use App\Modules\Content\Application\DTO\ChannelCommentDTO;
use App\Modules\Content\Application\DTO\ChannelMediaSpecDTO;
use App\Modules\Content\Application\DTO\ChannelMetricsDTO;
use App\Modules\Content\Application\DTO\PublishingLimitDTO;
use App\Modules\Content\Application\DTO\PublishRequestDTO;
use App\Modules\Content\Application\DTO\PublishResultDTO;
use App\Modules\Content\Domain\Contracts\ChannelProviderInterface;
use App\Modules\Content\Domain\Contracts\YoutubeClientFactoryInterface;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Exceptions\UnsupportedChannelException;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only channel. `videos.insert` exists, but it takes a resumable binary upload and the
 * `youtube.upload` scope, which this application does not request — so YouTube pieces are
 * published by hand and only their results are imported.
 */
readonly class YoutubeChannelProvider implements ChannelProviderInterface
{
    public function __construct(private YoutubeClientFactoryInterface $clients) {}

    public function platform(): ExperimentPlatform
    {
        return ExperimentPlatform::Youtube;
    }

    public function credentialProvider(): IntegrationProvider
    {
        return IntegrationProvider::YOUTUBE;
    }

    public function supportsPublishing(): bool
    {
        return false;
    }

    /** Nothing is published through the API, so the only spec that matters is YouTube's own upload ceiling. */
    public function mediaSpec(ContentFormat $format): ChannelMediaSpecDTO
    {
        return new ChannelMediaSpecDTO(
            ['image/jpeg', 'image/png'],
            2 * 1024 * 1024,
            ['video/mp4', 'video/quicktime'],
            256 * 1024 * 1024 * 1024,
            1,
            43200,
        );
    }

    public function publish(PublishRequestDTO $request): PublishResultDTO
    {
        throw UnsupportedChannelException::forPlatform($this->platform());
    }

    public function metrics(int $accountId, string $externalPostId, ContentFormat $format): ChannelMetricsDTO
    {
        $video = $this->clients->forAccount($accountId)->videos([$externalPostId])['items'][0] ?? [];

        return new ChannelMetricsDTO(
            isset($video['snippet']['publishedAt'])
                ? CarbonImmutable::parse($video['snippet']['publishedAt'])
                : CarbonImmutable::now(),
            (int) ($video['statistics']['viewCount'] ?? 0),
            0,
            (int) ($video['statistics']['likeCount'] ?? 0),
            (int) ($video['statistics']['commentCount'] ?? 0),
            0,
            0,
            (int) ($video['statistics']['likeCount'] ?? 0) + (int) ($video['statistics']['commentCount'] ?? 0),
            0,
            0,
            $video,
        );
    }

    public function comments(int $accountId, string $externalPostId): Collection
    {
        return collect($this->clients->forAccount($accountId)->commentThreads($externalPostId)['items'] ?? [])
            ->map(function (array $thread): ChannelCommentDTO {
                $comment = $thread['snippet']['topLevelComment']['snippet'] ?? [];

                return new ChannelCommentDTO(
                    (string) $thread['id'],
                    $comment['authorDisplayName'] ?? null,
                    (string) ($comment['textOriginal'] ?? ''),
                    (int) ($comment['likeCount'] ?? 0),
                    isset($comment['publishedAt']) ? CarbonImmutable::parse($comment['publishedAt']) : null,
                );
            })
            ->values();
    }

    public function publishingLimit(int $accountId): PublishingLimitDTO
    {
        return new PublishingLimitDTO(0, 0, 0);
    }

    public function audienceSnapshot(int $accountId): AudienceSnapshotDTO
    {
        $channel = $this->clients->forAccount($accountId)->ownChannel()['items'][0] ?? [];

        return new AudienceSnapshotDTO(
            $accountId,
            $this->platform(),
            CarbonImmutable::now()->startOfDay(),
            (int) ($channel['statistics']['subscriberCount'] ?? 0),
            0,
            (int) ($channel['statistics']['videoCount'] ?? 0),
            $channel,
        );
    }
}

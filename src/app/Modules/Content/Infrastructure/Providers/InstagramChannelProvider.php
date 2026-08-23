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
use App\Modules\Content\Domain\Contracts\InstagramClientFactoryInterface;
use App\Modules\Content\Domain\Enums\ContainerStatus;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Exceptions\InstagramAccountNotLinkedException;
use App\Modules\Content\Domain\Exceptions\PublishingContainerFailedException;
use App\Modules\Content\Domain\Exceptions\PublishingContainerTimedOutException;
use App\Modules\Content\Infrastructure\Clients\InstagramClient;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

readonly class InstagramChannelProvider implements ChannelProviderInterface
{
    /** Meta's own guidance: "Query a container's status once per minute, for no more than 5 minutes." */
    private const int POLL_ATTEMPTS = 5;

    private const int POLL_INTERVAL_SECONDS = 60;

    /** Publishing needs one of these two task capabilities on the connected Page. */
    private const array PUBLISHING_TASKS = ['MANAGE', 'CREATE_CONTENT'];

    private const array FEED_METRICS = [
        'views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions',
        'profile_visits', 'follows',
    ];

    private const array REEL_METRICS = [
        'views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions',
        'ig_reels_avg_watch_time', 'ig_reels_video_view_total_time',
    ];

    private const array STORY_METRICS = [
        'views', 'reach', 'replies', 'shares', 'total_interactions', 'navigation',
        'profile_visits', 'follows',
    ];

    public function __construct(private InstagramClientFactoryInterface $clients) {}

    public function platform(): ExperimentPlatform
    {
        return ExperimentPlatform::Instagram;
    }

    public function credentialProvider(): IntegrationProvider
    {
        return IntegrationProvider::META;
    }

    public function supportsPublishing(): bool
    {
        return true;
    }

    /** JPEG only for images; stories are capped harder than reels on both weight and length. */
    public function mediaSpec(ContentFormat $format): ChannelMediaSpecDTO
    {
        return new ChannelMediaSpecDTO(
            ['image/jpeg'],
            8 * 1024 * 1024,
            ['video/mp4', 'video/quicktime'],
            $format === ContentFormat::Story ? 100 * 1024 * 1024 : 300 * 1024 * 1024,
            3,
            $format === ContentFormat::Story ? 60 : 900,
        );
    }

    public function publish(PublishRequestDTO $request): PublishResultDTO
    {
        $client = $this->clients->forAccount($request->accountId);
        $igUserId = $this->igUserId($request->accountId, $client);

        return $this->result($client, $igUserId, $client->publishContainer(
            $igUserId,
            $this->awaitFinished($client, $this->buildContainer($client, $igUserId, $request)),
        ));
    }

    public function metrics(int $accountId, string $externalPostId, ContentFormat $format): ChannelMetricsDTO
    {
        $client = $this->clients->forAccount($accountId);
        $media = $client->media($externalPostId);

        // Carousels have no album-level insights, so their numbers come from the media
        // object's own like and comment counts instead.
        return $this->toMetrics(
            $media,
            $format->isCarousel() ? [] : self::readValues($client->mediaInsights($externalPostId, self::metricsFor($format))),
        );
    }

    public function comments(int $accountId, string $externalPostId): Collection
    {
        return collect($this->clients->forAccount($accountId)->comments($externalPostId)['data'] ?? [])
            ->map(fn (array $comment): ChannelCommentDTO => new ChannelCommentDTO(
                (string) $comment['id'],
                $comment['username'] ?? null,
                (string) ($comment['text'] ?? ''),
                (int) ($comment['like_count'] ?? 0),
                isset($comment['timestamp']) ? CarbonImmutable::parse($comment['timestamp']) : null,
            ))
            ->values();
    }

    public function publishingLimit(int $accountId): PublishingLimitDTO
    {
        $client = $this->clients->forAccount($accountId);
        $limit = $client->publishingLimit($this->igUserId($accountId, $client))['data'][0] ?? [];

        return new PublishingLimitDTO(
            (int) ($limit['quota_usage'] ?? 0),
            (int) ($limit['config']['quota_total'] ?? 0),
            (int) ($limit['config']['quota_duration'] ?? 0),
        );
    }

    public function audienceSnapshot(int $accountId): AudienceSnapshotDTO
    {
        $client = $this->clients->forAccount($accountId);
        $profile = $client->profile($this->igUserId($accountId, $client));

        return new AudienceSnapshotDTO(
            $accountId,
            $this->platform(),
            CarbonImmutable::now()->startOfDay(),
            (int) ($profile['followers_count'] ?? 0),
            (int) ($profile['follows_count'] ?? 0),
            (int) ($profile['media_count'] ?? 0),
            $profile,
        );
    }

    private function buildContainer(InstagramClient $client, string $igUserId, PublishRequestDTO $request): string
    {
        return $request->format->isCarousel()
            ? $this->carouselContainer($client, $igUserId, $request)
            : (string) $client->createContainer($igUserId, $this->singleParameters($request))['id'];
    }

    private function carouselContainer(InstagramClient $client, string $igUserId, PublishRequestDTO $request): string
    {
        return (string) $client->createContainer($igUserId, [
            'media_type' => 'CAROUSEL',
            'caption' => $request->caption,
            'children' => collect($request->mediaUrls)
                ->map(fn (string $url): string => (string) $client->createContainer($igUserId, [
                    'image_url' => $url,
                    'is_carousel_item' => 'true',
                ])['id'])
                ->implode(','),
        ])['id'];
    }

    private function singleParameters(PublishRequestDTO $request): array
    {
        return array_filter([
            'media_type' => match ($request->format) {
                ContentFormat::Reel => 'REELS',
                ContentFormat::Story => 'STORIES',
                default => null,
            },
            $request->format->isVideo() ? 'video_url' : 'image_url' => $request->mediaUrls[0],
            // A story carries no caption: the field is silently ignored there.
            'caption' => $request->format === ContentFormat::Story ? null : $request->caption,
            'cover_url' => $request->coverUrl,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * IN_PROGRESS → poll again, up to five times a minute apart. FINISHED → publish.
     * ERROR, EXPIRED and PUBLISHED are definitive; a container still IN_PROGRESS after the
     * documented window is transient and the job retries it.
     */
    private function awaitFinished(InstagramClient $client, string $containerId): string
    {
        foreach (range(1, self::POLL_ATTEMPTS) as $attempt) {
            $container = $client->container($containerId);
            $status = ContainerStatus::tryFrom((string) ($container['status_code'] ?? ''));

            if ($status === ContainerStatus::Finished) {
                return $containerId;
            }

            if ($status !== ContainerStatus::InProgress) {
                throw PublishingContainerFailedException::withStatus(
                    $containerId,
                    $status ?? ContainerStatus::Error,
                    $container['status'] ?? null,
                );
            }

            if ($attempt < self::POLL_ATTEMPTS) {
                Sleep::for(self::POLL_INTERVAL_SECONDS)->seconds();
            }
        }

        throw PublishingContainerTimedOutException::withContainer($containerId, self::POLL_ATTEMPTS);
    }

    private function result(InstagramClient $client, string $igUserId, array $published): PublishResultDTO
    {
        return new PublishResultDTO(
            (string) $published['id'],
            // The permalink is stable; media_url is a CDN URL that expires.
            $client->media((string) $published['id'])['permalink'] ?? null,
            ['ig_user_id' => $igUserId, ...$published],
        );
    }

    private function igUserId(int $accountId, InstagramClient $client): string
    {
        return collect($client->pages()['data'] ?? [])
            ->first(fn (array $page): bool => isset($page['instagram_business_account']['id'])
                && array_intersect(self::PUBLISHING_TASKS, $page['tasks'] ?? []) !== []
            )['instagram_business_account']['id']
            ?? throw InstagramAccountNotLinkedException::forAccount($accountId);
    }

    private function toMetrics(array $media, array $insights): ChannelMetricsDTO
    {
        return new ChannelMetricsDTO(
            isset($media['timestamp']) ? CarbonImmutable::parse($media['timestamp']) : CarbonImmutable::now(),
            (int) ($insights['views'] ?? 0),
            (int) ($insights['reach'] ?? 0),
            (int) ($insights['likes'] ?? $media['like_count'] ?? 0),
            (int) ($insights['comments'] ?? $media['comments_count'] ?? 0),
            (int) ($insights['shares'] ?? 0),
            (int) ($insights['saved'] ?? 0),
            (int) ($insights['total_interactions'] ?? 0),
            (int) ($insights['follows'] ?? 0),
            (int) ($insights['profile_visits'] ?? 0),
            ['media' => $media, 'insights' => $insights],
        );
    }

    /** @return list<string> */
    private static function metricsFor(ContentFormat $format): array
    {
        return match ($format) {
            ContentFormat::Reel, ContentFormat::Video => self::REEL_METRICS,
            ContentFormat::Story => self::STORY_METRICS,
            default => self::FEED_METRICS,
        };
    }

    /**
     * Every metric object carries both `values[]` and `total_value`; the demographics
     * shapes carry only one of them. Read whichever is present.
     *
     * @return array<string, int>
     */
    private static function readValues(array $insights): array
    {
        return collect($insights['data'] ?? [])
            ->mapWithKeys(fn (array $metric): array => [
                $metric['name'] => (int) ($metric['total_value']['value'] ?? $metric['values'][0]['value'] ?? 0),
            ])
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Providers;

use App\Modules\Competitors\Application\DTO\CompetitorCommentDTO;
use App\Modules\Competitors\Application\DTO\CompetitorPostDTO;
use App\Modules\Competitors\Application\DTO\FetchAdsDTO;
use App\Modules\Competitors\Application\DTO\FetchCommentsDTO;
use App\Modules\Competitors\Application\DTO\FetchPostsDTO;
use App\Modules\Competitors\Application\DTO\ProviderRunResultDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorDataProviderInterface;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Competitors\Domain\Exceptions\ApifyRunFailedException;
use App\Modules\Competitors\Domain\Exceptions\ApifyRunUnfinishedException;
use App\Modules\Competitors\Domain\Exceptions\UnsupportedCompetitorPlatformException;
use App\Modules\Competitors\Infrastructure\Clients\ApifyClient;
use App\Modules\Competitors\Infrastructure\Clients\ApifyClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Apify behind the provider seam. Everything provider-shaped stops here: field names,
 * actor ids, run statuses and the DCO placeholder quirk never leave this class.
 */
readonly class ApifyCompetitorProvider implements CompetitorDataProviderInterface
{
    private const int MAX_POLL_ATTEMPTS = 10;

    /** Second guard rail next to maxItems, because a runaway actor spends the user's money. */
    private const float MAX_TOTAL_CHARGE_USD = 2.0;

    private const string INSTAGRAM_PROFILE_URL = 'https://www.instagram.com/%s/';

    private const string FACEBOOK_PAGE_URL = 'https://www.facebook.com/%s';

    private const string AD_PERMALINK_URL = 'https://www.facebook.com/ads/library/?id=%s';

    private const string REEL_PRODUCT_TYPE = 'clips';

    private const int HIDDEN_LIKES = -1;

    public function __construct(private ApifyClientFactory $clients) {}

    public function fetchPosts(FetchPostsDTO $dto): ProviderRunResultDTO
    {
        if ($dto->platform !== CompetitorPlatform::Instagram) {
            throw UnsupportedCompetitorPlatformException::forPlatform($dto->platform);
        }

        return $this->run(
            $dto->accountId,
            (string) config('services.apify.actors.instagram_posts'),
            array_filter([
                'resultsType' => 'posts',
                'directUrls' => [sprintf(self::INSTAGRAM_PROFILE_URL, $dto->handle)],
                'resultsLimit' => $dto->limit,
                'onlyPostsNewerThan' => $dto->onlyNewerThan,
            ], static fn (mixed $value): bool => $value !== null),
            $dto->limit,
            static fn (array $item): array => [self::toPost($item)],
        );
    }

    public function fetchComments(FetchCommentsDTO $dto): ProviderRunResultDTO
    {
        return $this->run(
            $dto->accountId,
            (string) config('services.apify.actors.instagram_comments'),
            [
                'directUrls' => $dto->postUrls,
                'resultsLimit' => $dto->limitPerPost,
            ],
            count($dto->postUrls) * $dto->limitPerPost,
            static fn (array $item): array => self::toComments($item),
        );
    }

    public function fetchAds(FetchAdsDTO $dto): ProviderRunResultDTO
    {
        return $this->run(
            $dto->accountId,
            (string) config('services.apify.actors.facebook_ads'),
            array_filter([
                'startUrls' => [['url' => sprintf(self::FACEBOOK_PAGE_URL, $dto->handle)]],
                'resultsLimit' => $dto->limit,
                'activeStatus' => 'Active',
                'onlyAdsNewerThan' => $dto->onlyNewerThan,
            ], static fn (mixed $value): bool => $value !== null),
            $dto->limit,
            static fn (array $item): array => [self::toAd($item)],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  callable(array<string, mixed>): list<mixed>  $map
     */
    private function run(int $accountId, string $actorId, array $input, int $maxItems, callable $map): ProviderRunResultDTO
    {
        $client = $this->clients->forAccount($accountId);
        $run = self::poll($client, $client->startRun($actorId, $input, $maxItems, self::MAX_TOTAL_CHARGE_USD));

        return new ProviderRunResultDTO(
            $actorId,
            $run['id'] ?? null,
            collect($client->datasetItems((string) ($run['defaultDatasetId'] ?? ''), $maxItems))->flatMap($map)->values(),
            (float) ($run['usageTotalUsd'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private static function poll(ApifyClient $client, array $run): array
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS && ! ApifyClient::isTerminal($run); $attempt++) {
            $run = $client->awaitRun((string) ($run['id'] ?? ''));
        }

        return match (true) {
            ApifyClient::hasSucceeded($run) => $run,
            ApifyClient::isTerminal($run) => throw ApifyRunFailedException::forRun(
                (string) ($run['id'] ?? ''),
                (string) ($run['status'] ?? ''),
            ),
            default => throw ApifyRunUnfinishedException::forRun(
                (string) ($run['id'] ?? ''),
                (string) ($run['status'] ?? ''),
            ),
        };
    }

    /** @param array<string, mixed> $item */
    private static function toPost(array $item): CompetitorPostDTO
    {
        return new CompetitorPostDTO(
            (string) ($item['id'] ?? ''),
            (string) ($item['url'] ?? ''),
            self::postType($item),
            $item['caption'] ?? null,
            self::timestamp($item['timestamp'] ?? null),
            self::likes($item['likesCount'] ?? null),
            (int) ($item['commentsCount'] ?? 0),
            (int) ($item['videoViewCount'] ?? $item['videoPlayCount'] ?? 0),
            $item,
        );
    }

    /** @param array<string, mixed> $item */
    private static function toAd(array $item): CompetitorPostDTO
    {
        $archiveId = (string) ($item['adArchiveID'] ?? $item['adArchiveId'] ?? '');

        return new CompetitorPostDTO(
            $archiveId,
            sprintf(self::AD_PERMALINK_URL, $archiveId),
            'ad',
            self::adCopy($item['snapshot'] ?? []),
            self::timestamp($item['startDateFormatted'] ?? null),
            null,
            0,
            0,
            $item,
        );
    }

    /**
     * Replies carry the same postUrl as their parent and are just as much a signal of a
     * recurring question, so the thread is flattened rather than dropped.
     *
     * @param  array<string, mixed>  $item
     * @return list<CompetitorCommentDTO>
     */
    private static function toComments(array $item, ?string $parentPostUrl = null): array
    {
        $postUrl = (string) ($item['postUrl'] ?? $parentPostUrl ?? '');

        return [
            new CompetitorCommentDTO(
                (string) ($item['id'] ?? ''),
                $postUrl,
                $item['ownerUsername'] ?? null,
                (string) ($item['text'] ?? ''),
                (int) ($item['likesCount'] ?? 0),
                self::timestamp($item['timestamp'] ?? null),
            ),
            ...collect($item['replies'] ?? [])
                ->flatMap(static fn (array $reply): array => self::toComments($reply, $postUrl))
                ->all(),
        ];
    }

    /** @param array<string, mixed> $item */
    private static function postType(array $item): string
    {
        return ($item['productType'] ?? null) === self::REEL_PRODUCT_TYPE
            ? 'reel'
            : Str::lower((string) ($item['type'] ?? 'image'));
    }

    /** Instagram reports -1 when the profile hides likes; that is unknown, not none. */
    private static function likes(mixed $count): ?int
    {
        return $count === null || (int) $count === self::HIDDEN_LIKES ? null : (int) $count;
    }

    /**
     * Dynamic Creative ads return unresolved Mustache placeholders at the top level; the
     * copy a human actually wrote is in the first card.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private static function adCopy(array $snapshot): ?string
    {
        return Str::isMatch('/^\{\{.+\}\}$/', (string) ($snapshot['body']['text'] ?? ''))
            ? $snapshot['cards'][0]['body'] ?? null
            : $snapshot['body']['text'] ?? null;
    }

    private static function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}

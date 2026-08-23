<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Clients;

use App\Modules\Content\Domain\Exceptions\InstagramApiException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Instagram API with Facebook Login for Business, over graph.facebook.com. Parameters
 * travel in the query string even on POST — the Graph API reads them identically there and
 * in a form body, and every outbound client in this project sends `Content-Type:
 * application/json`, which would mislabel a form body.
 *
 * The access token is an `Authorization: Bearer` header set by the factory, never a query
 * parameter, so it cannot reach an exception context or a log line.
 */
class InstagramClient extends ApiClientAbstract
{
    private const string PAGES_ENDPOINT = 'me/accounts';

    private const string PAGE_FIELDS = 'id,name,tasks,instagram_business_account';

    private const string PROFILE_FIELDS = 'id,username,followers_count,follows_count,media_count';

    private const string CONTAINER_FIELDS = 'id,status_code,status';

    private const string MEDIA_FIELDS = 'id,permalink,media_product_type,media_type,timestamp,like_count,comments_count';

    private const string COMMENT_FIELDS = 'id,text,timestamp,username,like_count,hidden';

    /** The comments edge caps a page at 50 regardless of what is asked for. */
    private const int COMMENT_PAGE_SIZE = 50;

    public function __construct(Client $client, private readonly string $graphVersion)
    {
        parent::__construct($client);
    }

    /**
     * @throws InstagramApiException
     */
    public function pages(): array
    {
        return $this->call('pages', fn (): array => $this->get(
            $this->versioned(self::PAGES_ENDPOINT),
            ['fields' => self::PAGE_FIELDS],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function profile(string $igUserId): array
    {
        return $this->call('profile', fn (): array => $this->get(
            $this->versioned($igUserId),
            ['fields' => self::PROFILE_FIELDS],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function createContainer(string $igUserId, array $parameters): array
    {
        return $this->call('create_container', fn (): array => $this->post(
            $this->versioned("{$igUserId}/media"),
            [RequestOptions::QUERY => $parameters],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function container(string $containerId): array
    {
        return $this->call('container_status', fn (): array => $this->get(
            $this->versioned($containerId),
            ['fields' => self::CONTAINER_FIELDS],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function publishContainer(string $igUserId, string $creationId): array
    {
        return $this->call('media_publish', fn (): array => $this->post(
            $this->versioned("{$igUserId}/media_publish"),
            [RequestOptions::QUERY => ['creation_id' => $creationId]],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function publishingLimit(string $igUserId): array
    {
        return $this->call('content_publishing_limit', fn (): array => $this->get(
            $this->versioned("{$igUserId}/content_publishing_limit"),
            ['fields' => 'config,quota_usage'],
        ));
    }

    /**
     * @param  list<string>  $metrics
     *
     * @throws InstagramApiException
     */
    public function mediaInsights(string $mediaId, array $metrics): array
    {
        return $this->call('media_insights', fn (): array => $this->get(
            $this->versioned("{$mediaId}/insights"),
            ['metric' => implode(',', $metrics)],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function media(string $mediaId): array
    {
        return $this->call('media', fn (): array => $this->get(
            $this->versioned($mediaId),
            ['fields' => self::MEDIA_FIELDS],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    public function comments(string $mediaId): array
    {
        return $this->call('comments', fn (): array => $this->get(
            $this->versioned("{$mediaId}/comments"),
            ['fields' => self::COMMENT_FIELDS, 'limit' => self::COMMENT_PAGE_SIZE],
        ));
    }

    /**
     * @throws InstagramApiException
     */
    private function call(string $operation, callable $request): array
    {
        try {
            return $request();
        } catch (ApiCallFailedException $exception) {
            throw InstagramApiException::fromApiCall($exception, $operation);
        }
    }

    private function versioned(string $endpoint): string
    {
        return "{$this->graphVersion}/{$endpoint}";
    }
}

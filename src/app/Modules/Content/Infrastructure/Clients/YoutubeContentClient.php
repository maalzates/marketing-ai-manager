<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Clients;

use App\Modules\Content\Domain\Exceptions\YoutubeApiException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;

/**
 * The read side of YouTube Data API v3 for the account's own channel. Uploading is not
 * here: `videos.insert` needs a resumable binary upload and the `youtube.upload` scope,
 * neither of which this application asks for — YouTube is a manual-publish channel.
 *
 * `search.list` is never called: it has its own 100-calls-a-day bucket, while the
 * uploads-playlist path costs 1 unit out of the general 10,000.
 */
class YoutubeContentClient extends ApiClientAbstract
{
    private const string CHANNELS_ENDPOINT = 'channels';

    private const string VIDEOS_ENDPOINT = 'videos';

    private const string COMMENT_THREADS_ENDPOINT = 'commentThreads';

    private const int COMMENT_PAGE_SIZE = 100;

    /**
     * @throws YoutubeApiException
     */
    public function ownChannel(): array
    {
        return $this->call('channels.list', fn (): array => $this->get(self::CHANNELS_ENDPOINT, [
            'part' => 'snippet,statistics,contentDetails',
            'mine' => 'true',
        ]));
    }

    /**
     * @param  list<string>  $videoIds
     *
     * @throws YoutubeApiException
     */
    public function videos(array $videoIds): array
    {
        return $this->call('videos.list', fn (): array => $this->get(self::VIDEOS_ENDPOINT, [
            'part' => 'snippet,statistics,contentDetails',
            'id' => implode(',', $videoIds),
        ]));
    }

    /**
     * @throws YoutubeApiException
     */
    public function commentThreads(string $videoId): array
    {
        return $this->call('commentThreads.list', fn (): array => $this->get(self::COMMENT_THREADS_ENDPOINT, [
            'part' => 'snippet',
            'videoId' => $videoId,
            'maxResults' => self::COMMENT_PAGE_SIZE,
            'order' => 'time',
            'textFormat' => 'plainText',
        ]));
    }

    /**
     * @throws YoutubeApiException
     */
    private function call(string $operation, callable $request): array
    {
        try {
            return $request();
        } catch (ApiCallFailedException $exception) {
            throw YoutubeApiException::fromApiCall($exception, $operation);
        }
    }
}

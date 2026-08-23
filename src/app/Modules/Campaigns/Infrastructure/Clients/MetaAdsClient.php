<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Clients;

use App\Modules\Campaigns\Domain\Exceptions\MetaAdsClientException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Throwable;

/**
 * Bound to one ad account and one access token for the length of a single operation. The
 * token lives in the Guzzle client's default headers, never in a query string or a
 * request payload, so it cannot reach an exception context or a log line.
 */
class MetaAdsClient extends ApiClientAbstract
{
    private const string CAMPAIGNS_EDGE = 'campaigns';

    private const string ADSETS_EDGE = 'adsets';

    private const string ADCREATIVES_EDGE = 'adcreatives';

    private const string ADS_EDGE = 'ads';

    private const string ADIMAGES_EDGE = 'adimages';

    private const string ADVIDEOS_EDGE = 'advideos';

    private const string INSIGHTS_EDGE = 'insights';

    private const string LEARNING_STAGE_FIELD = 'learning_stage_info';

    private const string INSIGHTS_FIELDS = 'spend,impressions,reach,frequency,clicks,ctr,cpm,cpc,'
        .'actions,cost_per_action_type,video_play_actions';

    /** A day per row over a window this app never lets exceed 90 days, so one page always holds it. */
    private const int INSIGHTS_PAGE_SIZE = 500;

    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * Currencies Meta bills in whole units instead of hundredths: for these, the "minor
     * unit" the budget fields expect is the unit itself.
     *
     * @var list<string>
     */
    private const array ZERO_DECIMAL_CURRENCIES = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public function __construct(
        Client $client,
        private readonly string $graphVersion,
        private readonly string $adAccountId,
        private readonly string $currency,
    ) {
        parent::__construct($client);
    }

    /**
     * The single conversion from the decimal currency this app stores into the integer
     * minor units every Meta budget field expects.
     */
    public function budgetMinorUnits(float $amount): int
    {
        return in_array(strtoupper($this->currency), self::ZERO_DECIMAL_CURRENCIES, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    public function createCampaign(array $payload): string
    {
        return $this->createOnAdAccount(self::CAMPAIGNS_EDGE, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    public function createAdSet(array $payload): string
    {
        return $this->createOnAdAccount(self::ADSETS_EDGE, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    public function createAdCreative(array $payload): string
    {
        return $this->createOnAdAccount(self::ADCREATIVES_EDGE, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    public function createAd(array $payload): string
    {
        return $this->createOnAdAccount(self::ADS_EDGE, $payload);
    }

    /**
     * `/adimages` has no `file_url` parameter — Base64 bytes, a multipart part or a copy from
     * another ad account are the only inputs — so the signed URL is streamed through this
     * process into the multipart body rather than handed to Meta or staged on disk.
     *
     * @throws MetaAdsClientException
     */
    public function uploadImage(string $filename, string $fetchUrl): string
    {
        try {
            $body = new MultipartStream([[
                'name' => 'file',
                'filename' => $filename,
                'contents' => Utils::streamFor(Utils::tryFopen($fetchUrl, 'rb')),
            ]]);

            $response = $this->post($this->adAccount(self::ADIMAGES_EDGE), [
                // Set by hand because the shared Guzzle config already declares a JSON content
                // type, and Guzzle only fills in the multipart boundary when none is present.
                RequestOptions::HEADERS => ['Content-Type' => 'multipart/form-data; boundary='.$body->getBoundary()],
                RequestOptions::BODY => $body,
            ]);
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        } catch (Throwable $exception) {
            throw MetaAdsClientException::mediaUnreadable(self::ADIMAGES_EDGE, $filename, $exception);
        }

        // Meta keys the inner map by the filename it decided on, which is not always the one sent.
        $hash = collect($response['images'] ?? [])->pluck('hash')->first();

        return is_string($hash)
            ? $hash
            : throw MetaAdsClientException::unexpectedResponse(self::ADIMAGES_EDGE, $response);
    }

    /**
     * `/advideos` does document `file_url`, so the video never travels through this process.
     *
     * @throws MetaAdsClientException
     */
    public function uploadVideo(string $name, string $fileUrl): string
    {
        try {
            $response = $this->post($this->adAccount(self::ADVIDEOS_EDGE), [
                RequestOptions::JSON => ['name' => $name, 'file_url' => $fileUrl],
            ]);
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        }

        return isset($response['id']) && is_scalar($response['id'])
            ? (string) $response['id']
            : throw MetaAdsClientException::unexpectedResponse(self::ADVIDEOS_EDGE, $response);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    public function updateNode(string $nodeId, array $payload): void
    {
        try {
            $this->post($this->node($nodeId), [RequestOptions::JSON => $payload]);
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        }
    }

    /**
     * One row per day, both ends inclusive, in the ad account's timezone.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MetaAdsClientException
     */
    public function dailyInsights(string $nodeId, CarbonImmutable $since, CarbonImmutable $until, string $level): array
    {
        try {
            return array_values((array) ($this->get($this->node($nodeId, self::INSIGHTS_EDGE), [
                'fields' => self::INSIGHTS_FIELDS,
                'time_increment' => 1,
                'time_range' => json_encode([
                    'since' => $since->format(self::DATE_FORMAT),
                    'until' => $until->format(self::DATE_FORMAT),
                ]),
                'level' => $level,
                'limit' => self::INSIGHTS_PAGE_SIZE,
            ])['data'] ?? []));
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        }
    }

    /**
     * Absent — not null — on an ad set that has never delivered, which is why a missing key
     * means "unknown" rather than "finished learning".
     *
     * @throws MetaAdsClientException
     */
    public function learningStage(string $adSetId): ?string
    {
        try {
            $adSet = $this->get($this->node($adSetId), ['fields' => 'id,'.self::LEARNING_STAGE_FIELD]);
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        }

        return is_string($adSet[self::LEARNING_STAGE_FIELD]['status'] ?? null)
            ? $adSet[self::LEARNING_STAGE_FIELD]['status']
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MetaAdsClientException
     */
    private function createOnAdAccount(string $edge, array $payload): string
    {
        try {
            $response = $this->post($this->adAccount($edge), [RequestOptions::JSON => $payload]);
        } catch (ApiCallFailedException $exception) {
            throw MetaAdsClientException::fromApiCallFailedException($exception);
        }

        return isset($response['id']) && is_scalar($response['id'])
            ? (string) $response['id']
            : throw MetaAdsClientException::unexpectedResponse($edge, $response);
    }

    private function adAccount(string $edge): string
    {
        return "{$this->graphVersion}/act_{$this->adAccountId}/{$edge}";
    }

    private function node(string $nodeId, string $edge = ''): string
    {
        return rtrim("{$this->graphVersion}/{$nodeId}/{$edge}", '/');
    }
}

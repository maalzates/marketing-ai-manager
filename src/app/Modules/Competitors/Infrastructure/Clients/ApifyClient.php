<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Clients;

use App\Modules\Competitors\Domain\Exceptions\ApifyCredentialRejectedException;
use App\Modules\Competitors\Domain\Exceptions\ApifyCreditExhaustedException;
use App\Modules\Competitors\Domain\Exceptions\ApifyRateLimitedException;
use App\Modules\Competitors\Domain\Exceptions\ApifyRequestFailedException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Domain\Support\SecretMasker;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Apify's v2 API. Runs are always started asynchronously and long-polled: the sync
 * endpoint caps at 300 s, and when it times out the run keeps burning the account's
 * credit while the HTTP response is already lost.
 */
class ApifyClient extends ApiClientAbstract
{
    /** The server holds a poll open for this long, so the socket timeout has to exceed it. */
    public const int WAIT_FOR_FINISH_SECONDS = 60;

    private const string RUNS_ENDPOINT = 'acts/%s/runs';

    private const string RUN_ENDPOINT = 'actor-runs/%s';

    private const string DATASET_ITEMS_ENDPOINT = 'datasets/%s/items';

    private const string SUCCEEDED_STATUS = 'SUCCEEDED';

    /** Hyphens, not underscores — those belong to the webhook event names. */
    private const array TERMINAL_STATUSES = ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'];

    /** @var array<string, class-string> */
    private const array ERROR_TYPES = [
        'invalid-token' => ApifyCredentialRejectedException::class,
        'missing-api-token' => ApifyCredentialRejectedException::class,
        'user-not-logged-in' => ApifyCredentialRejectedException::class,
        'insufficient-credit' => ApifyCreditExhaustedException::class,
        'not-enough-usage-to-run-paid-actor' => ApifyCreditExhaustedException::class,
        'x402-payment-required' => ApifyCreditExhaustedException::class,
        'rate-limit-exceeded' => ApifyRateLimitedException::class,
    ];

    public function __construct(Client $client, private readonly SecretMasker $masker)
    {
        parent::__construct($client);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the run object
     */
    public function startRun(string $actorId, array $input, int $maxItems, float $maxTotalChargeUsd): array
    {
        try {
            return $this->post(sprintf(self::RUNS_ENDPOINT, $actorId), [
                RequestOptions::QUERY => [
                    'maxItems' => $maxItems,
                    'maxTotalChargeUsd' => $maxTotalChargeUsd,
                ],
                RequestOptions::JSON => $input,
            ])['data'] ?? [];
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, ['actor_id' => $actorId]);
        }
    }

    /**
     * Long-polls instead of looping: the connection is held until the run finishes or the
     * window closes, which is one request per minute rather than one per second.
     *
     * @return array<string, mixed> the run object
     */
    public function awaitRun(string $runId): array
    {
        try {
            return $this->get(sprintf(self::RUN_ENDPOINT, $runId), [
                'waitForFinish' => self::WAIT_FOR_FINISH_SECONDS,
            ])['data'] ?? [];
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, ['run_id' => $runId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function datasetItems(string $datasetId, int $limit): array
    {
        try {
            return array_values($this->get(sprintf(self::DATASET_ITEMS_ENDPOINT, $datasetId), [
                'clean' => 'true',
                'format' => 'json',
                'limit' => $limit,
            ]));
        } catch (ApiCallFailedException $exception) {
            throw $this->translate($exception, ['dataset_id' => $datasetId]);
        }
    }

    /** @param array<string, mixed> $run */
    public static function isTerminal(array $run): bool
    {
        return in_array($run['status'] ?? '', self::TERMINAL_STATUSES, true);
    }

    /** @param array<string, mixed> $run */
    public static function hasSucceeded(array $run): bool
    {
        return ($run['status'] ?? '') === self::SUCCEEDED_STATUS;
    }

    /**
     * `actor-not-found` arrives as a 400 and `insufficient-credit` as a 402, so the status
     * code alone decides nothing — `error.type` is the only stable discriminator.
     *
     * @param  array<string, mixed>  $context
     */
    private function translate(ApiCallFailedException $exception, array $context): ApiException
    {
        $type = self::errorType($exception);

        $masked = $this->masker->mask([
            ...$context,
            'error_type' => $type,
            'http_status_code' => $exception->getContext()['http_status_code'] ?? null,
            'response_body' => $exception->getContext()['response_body'] ?? null,
        ]);

        return array_key_exists($type, self::ERROR_TYPES)
            ? self::ERROR_TYPES[$type]::withContext($masked)
            : ApifyRequestFailedException::withContext($masked, $exception);
    }

    private static function errorType(ApiCallFailedException $exception): string
    {
        return (string) ($exception->getContext()['response_body']['error']['type'] ?? '');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Domain\Contracts\ModelListClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Exceptions\ModelListUnavailableException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use Illuminate\Support\Collection;

/**
 * `GET /models` returns embedding and token-counting models alongside the ones that can hold
 * a conversation, so the list is filtered by `supportedGenerationMethods`. The id arrives
 * prefixed — `models/gemini-3.7-flash` — and the prefix is not part of what a caller sends.
 */
class GeminiModelListClient extends ApiClientAbstract implements ModelListClientInterface
{
    private const string MODELS_ENDPOINT = 'models';

    private const string REQUIRED_METHOD = 'generateContent';

    private const string NAME_PREFIX = 'models/';

    private const int PAGE_SIZE = 100;

    public function list(): Collection
    {
        $ids = collect();
        $token = null;

        try {
            do {
                $page = $this->get(self::MODELS_ENDPOINT, array_filter([
                    'pageSize' => self::PAGE_SIZE,
                    'pageToken' => $token,
                ]));

                $ids->push(...collect($page['models'] ?? [])
                    ->filter(static fn (array $model): bool => in_array(
                        self::REQUIRED_METHOD,
                        $model['supportedGenerationMethods'] ?? [],
                        true,
                    ))
                    ->map(static fn (array $model): string => (string) str($model['name'] ?? '')->after(self::NAME_PREFIX))
                    ->filter()
                    ->all());

                $token = $page['nextPageToken'] ?? null;
            } while ($token !== null && $token !== '');
        } catch (ApiCallFailedException $exception) {
            throw ModelListUnavailableException::forProvider(LlmProvider::Gemini, $exception);
        }

        return $ids->unique()->values();
    }
}

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
 * `GET /v1/models` answers what this organisation is entitled to call, which is not the same
 * list for every account. It pages with `has_more` and `last_id`, so a single page would
 * silently hide the tail.
 */
class AnthropicModelListClient extends ApiClientAbstract implements ModelListClientInterface
{
    private const string MODELS_ENDPOINT = 'v1/models';

    private const int PAGE_SIZE = 100;

    public function list(): Collection
    {
        $ids = collect();
        $after = null;

        try {
            do {
                $page = $this->get(self::MODELS_ENDPOINT, array_filter([
                    'limit' => self::PAGE_SIZE,
                    'after_id' => $after,
                ]));

                $ids->push(...collect($page['data'] ?? [])->pluck('id')->filter()->all());
                $after = $page['last_id'] ?? null;
            } while (($page['has_more'] ?? false) === true && $after !== null);
        } catch (ApiCallFailedException $exception) {
            throw ModelListUnavailableException::forProvider(LlmProvider::Anthropic, $exception);
        }

        return $ids->unique()->values();
    }
}

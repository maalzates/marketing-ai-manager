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
 * `GET /models` answers one unpaginated page listing every model the account can touch —
 * embeddings, speech, images, moderation and the legacy completions engines included. It was
 * 126 entries on the first real run, of which seven could hold a conversation.
 *
 * OpenAI publishes no capability field, so the filter is by family and it **excludes** rather
 * than includes: an unknown new chat model still comes through, while `whisper` and
 * `davinci-002` do not. Anthropic needs none of this and Gemini has `supportedGenerationMethods`.
 */
class OpenAiModelListClient extends ApiClientAbstract implements ModelListClientInterface
{
    private const string MODELS_ENDPOINT = 'models';

    /** @var list<string> */
    private const array NOT_CONVERSATIONAL = [
        'embedding', 'whisper', 'tts', 'dall-e', 'image', 'moderation',
        'instruct', 'davinci', 'babbage', 'transcribe', 'realtime', 'audio',
        'search-preview', 'computer-use', 'codex', 'sora',
    ];

    public function list(): Collection
    {
        try {
            return collect($this->get(self::MODELS_ENDPOINT)['data'] ?? [])
                ->pluck('id')
                ->filter()
                ->reject(static fn (string $id): bool => collect(self::NOT_CONVERSATIONAL)->contains(
                    static fn (string $family): bool => str_contains($id, $family),
                ))
                ->unique()
                ->values();
        } catch (ApiCallFailedException $exception) {
            throw ModelListUnavailableException::forProvider(LlmProvider::OpenAi, $exception);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Domain\Contracts\LlmClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

/**
 * Replays a captured provider body through the real adapter, so a test exercises the same
 * normalisation production runs — a hand-built LlmResponseDTO would pass while the
 * response mapping is broken.
 */
readonly class FakeLlmClient implements LlmClientInterface
{
    private const string FIXTURE_DIRECTORY = 'tests/Fixtures/llm/';

    public function __construct(private LlmClientInterface $client) {}

    public static function replaying(LlmProvider $provider, string $fixture, int $status = 200): self
    {
        return new self(match ($provider) {
            LlmProvider::Anthropic => new AnthropicClient(self::mockedTransport($fixture, $status)),
            LlmProvider::OpenAi => new OpenAiClient(self::mockedTransport($fixture, $status)),
            LlmProvider::Gemini => new GeminiClient(self::mockedTransport($fixture, $status)),
        });
    }

    public function complete(LlmRequestDTO $request): LlmResponseDTO
    {
        return $this->client->complete($request);
    }

    public function provider(): LlmProvider
    {
        return $this->client->provider();
    }

    private static function mockedTransport(string $fixture, int $status): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response($status, ['Content-Type' => 'application/json'], self::fixtureBody($fixture)),
            ])),
        ]);
    }

    private static function fixtureBody(string $fixture): string
    {
        return is_file($path = base_path(self::FIXTURE_DIRECTORY.$fixture))
            ? (string) file_get_contents($path)
            : throw new RuntimeException("Missing LLM fixture: {$path}");
    }
}

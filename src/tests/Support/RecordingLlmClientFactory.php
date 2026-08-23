<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Contracts\LlmClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClient;
use ArrayObject;
use Illuminate\Contracts\Foundation\Application;

/**
 * A replayed provider plus the requests the application actually assembled.
 *
 * The recording is the point for cost-shaped tests: proving that a feature spent nothing is
 * a matter of counting calls, and proving it spent little is a matter of reading what it
 * put in the prompt. The real adapter still parses the real body, so response mapping is
 * exercised exactly as in production.
 */
final class RecordingLlmClientFactory implements LlmClientFactoryInterface
{
    /** @var ArrayObject<int, LlmRequestDTO> */
    private readonly ArrayObject $sent;

    public function __construct(private readonly string $fixture)
    {
        $this->sent = new ArrayObject;
    }

    public static function replaying(string $fixture): self
    {
        return new self($fixture);
    }

    public function install(Application $app): self
    {
        $app->instance(LlmClientFactoryInterface::class, $this);

        return $this;
    }

    public function forAccount(int $accountId, LlmProvider $provider): LlmClientInterface
    {
        return new class(FakeLlmClient::replaying($provider, $this->fixture), $this->sent) implements LlmClientInterface
        {
            /** @param  ArrayObject<int, LlmRequestDTO>  $sent */
            public function __construct(private readonly LlmClientInterface $client, private readonly ArrayObject $sent) {}

            public function complete(LlmRequestDTO $request): LlmResponseDTO
            {
                $this->sent[] = $request;

                return $this->client->complete($request);
            }

            public function provider(): LlmProvider
            {
                return $this->client->provider();
            }
        };
    }

    public function callCount(): int
    {
        return $this->sent->count();
    }

    public function request(int $index = 0): LlmRequestDTO
    {
        return $this->sent[$index];
    }

    /** Everything the prompt carried, flattened, so a test can ask what the model was shown. */
    public function promptText(int $index = 0): string
    {
        return $this->request($index)->systemPrompt."\n".(string) json_encode(
            array_map(static fn (object $message): array => (array) $message, $this->request($index)->messages),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}

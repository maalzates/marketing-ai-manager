<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use ArrayObject;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Foundation\Application;
use Psr\Http\Message\RequestInterface;

/**
 * The provider responses a test replays, plus the requests the application actually sent.
 *
 * `install()` replaces the container's GuzzleClientFactory, so nothing under test knows a
 * test is running: the real factories, the real clients and the real headers are exercised.
 */
final class FakeTransport
{
    private readonly MockHandler $handler;

    /** @var ArrayObject<int, RequestInterface> */
    private readonly ArrayObject $sent;

    public function __construct(Response ...$responses)
    {
        $this->handler = new MockHandler($responses);
        $this->sent = new ArrayObject;
    }

    public static function replaying(Response ...$responses): self
    {
        return new self(...$responses);
    }

    /** Nothing queued: any outbound call fails the test rather than reaching the network. */
    public static function silent(): self
    {
        return new self;
    }

    public static function json(array|string $body, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            is_string($body) ? $body : (string) json_encode($body),
        );
    }

    public static function fixture(string $name, int $status = 200): Response
    {
        return self::json((string) file_get_contents(base_path("tests/Fixtures/llm/{$name}")), $status);
    }

    public function install(Application $app): self
    {
        $app->instance(GuzzleClientFactory::class, new RecordingGuzzleClientFactory($this->handler, $this->sent));

        return $this;
    }

    public function queue(Response ...$responses): self
    {
        foreach ($responses as $response) {
            $this->handler->append($response);
        }

        return $this;
    }

    public function requestCount(): int
    {
        return $this->sent->count();
    }

    public function request(int $index = 0): RequestInterface
    {
        return $this->sent[$index];
    }

    public function header(string $name, int $index = 0): string
    {
        return $this->request($index)->getHeaderLine($name);
    }

    public function path(int $index = 0): string
    {
        return $this->request($index)->getUri()->getPath();
    }

    public function query(int $index = 0): string
    {
        return $this->request($index)->getUri()->getQuery();
    }

    public function body(int $index = 0): string
    {
        return (string) $this->request($index)->getBody();
    }

    /** @return array<string, mixed> */
    public function decodedBody(int $index = 0): array
    {
        return (array) json_decode($this->body($index), true);
    }
}

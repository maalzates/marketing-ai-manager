<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The whole outbound path, entered through a route: controller → client → Guzzle →
 * domain exception → error envelope. This is the shape every real integration
 * (YouTube, Meta, Anthropic) will be tested in.
 */
class ExternalApiClientTest extends TestCase
{
    public function test_a_successful_call_reaches_the_caller_as_decoded_data(): void
    {
        $this->routeCalling(new GuzzleResponse(200, [], '{"items":[{"id":"vid_1"}]}'));

        $this->getJson('/api/testing/provider')
            ->assertOk()
            ->assertJsonPath('result.items.0.id', 'vid_1');
    }

    public function test_an_empty_body_reaches_the_caller_as_an_empty_result(): void
    {
        $this->routeCalling(new GuzzleResponse(204));

        $this->getJson('/api/testing/provider')->assertOk()->assertJsonPath('result', []);
    }

    public function test_a_provider_error_becomes_the_domain_exceptions_status_and_message(): void
    {
        $this->routeCalling(new GuzzleResponse(422, [], '{"error":"caption too long"}'));

        $this->getJson('/api/testing/provider')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'The provider rejected the request.');
    }

    public function test_the_provider_response_is_logged_but_never_returned(): void
    {
        Log::spy();

        $this->routeCalling(new GuzzleResponse(500, [], '{"secret":"do-not-echo"}'));

        $this->getJson('/api/testing/provider')
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertDontSee('do-not-echo');

        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => $context['uri'] === '/things'
                && $context['method'] === 'GET'
                && $context['response_body'] === ['secret' => 'do-not-echo']
        )->once();
    }

    public function test_a_non_json_error_body_leaves_the_response_body_null(): void
    {
        Log::spy();

        $this->routeCalling(new GuzzleResponse(502, [], '<html>bad gateway</html>'));

        $this->getJson('/api/testing/provider')->assertStatus(Response::HTTP_BAD_GATEWAY);

        Log::shouldHaveReceived('log')
            ->withArgs(fn (string $level, string $message, array $context): bool => $context['response_body'] === null)
            ->once();
    }

    private function routeCalling(GuzzleResponse $response): void
    {
        $this->app->singleton(TestProviderClient::class, fn (): TestProviderClient => new TestProviderClient(
            $this->app->make(GuzzleClientFactory::class)->create([
                'handler' => HandlerStack::create(new MockHandler([$response])),
            ])
        ));

        Route::get('/api/testing/provider', fn (TestProviderClient $client) => response()->json([
            'result' => $client->listThings(),
            'errors' => [],
        ]));
    }
}

class TestProviderClient extends ApiClientAbstract
{
    private const string THINGS_ENDPOINT = '/things';

    /**
     * @throws TestProviderException
     */
    public function listThings(): array
    {
        try {
            return $this->get(self::THINGS_ENDPOINT, ['page' => 1]);
        } catch (ApiCallFailedException $exception) {
            throw TestProviderException::fromApiCallFailedException($exception);
        }
    }
}

class TestProviderException extends ClientException
{
    public function __construct(?string $message = null, int $code = Response::HTTP_BAD_REQUEST, ?\Throwable $previous = null)
    {
        parent::__construct('The provider rejected the request.', $code, $previous);
    }
}

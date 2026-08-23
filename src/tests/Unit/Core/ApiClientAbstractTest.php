<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ApiClientAbstractTest extends TestCase
{
    public function test_it_decodes_a_json_body(): void
    {
        $this->assertSame(
            ['items' => [1, 2]],
            $this->clientReturning(new Response(200, [], '{"items":[1,2]}'))->fetch()
        );
    }

    public function test_an_empty_body_becomes_an_empty_array(): void
    {
        $this->assertSame([], $this->clientReturning(new Response(204))->fetch());
    }

    public function test_an_error_response_becomes_an_api_call_failed_exception_carrying_the_body(): void
    {
        try {
            $this->clientReturning(new Response(422, [], '{"error":"caption too long"}'))->fetch();
            $this->fail('Expected ApiCallFailedException.');
        } catch (ApiCallFailedException $exception) {
            $this->assertSame(422, $exception->getHttpStatusCode());
            $this->assertSame(['error' => 'caption too long'], $exception->getContext()['response_body']);
            $this->assertSame('/things', $exception->getContext()['uri']);
        }
    }

    public function test_a_non_json_error_body_leaves_response_body_null(): void
    {
        try {
            $this->clientReturning(new Response(500, [], '<html>oops</html>'))->fetch();
            $this->fail('Expected ApiCallFailedException.');
        } catch (ApiCallFailedException $exception) {
            $this->assertNull($exception->getContext()['response_body']);
        }
    }

    public function test_the_exception_message_never_leaks_the_provider_response(): void
    {
        try {
            $this->clientReturning(new Response(500, [], '{"secret":"do-not-echo"}'))->fetch();
            $this->fail('Expected ApiCallFailedException.');
        } catch (ApiCallFailedException $exception) {
            $this->assertStringNotContainsString('do-not-echo', $exception->getMessage());
        }
    }

    private function clientReturning(Response $response): FakeApiClient
    {
        return new FakeApiClient(
            new Client(['handler' => HandlerStack::create(new MockHandler([$response]))])
        );
    }
}

class FakeApiClient extends ApiClientAbstract
{
    public function fetch(): array
    {
        return $this->get('/things', ['page' => 1]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Base for every external API client. Subclasses expose domain methods and translate
 * ApiCallFailedException into their own domain exception; they never touch Guzzle.
 */
abstract class ApiClientAbstract
{
    public function __construct(protected readonly Client $client) {}

    /**
     * @throws ApiCallFailedException
     */
    protected function get(string $uri, array $params = []): array
    {
        return $this->request(Request::METHOD_GET, $uri, [RequestOptions::QUERY => $params]);
    }

    /**
     * @throws ApiCallFailedException
     */
    protected function post(string $uri, array $options = []): array
    {
        return $this->request(Request::METHOD_POST, $uri, $options);
    }

    /**
     * @throws ApiCallFailedException
     */
    protected function put(string $uri, array $options = []): array
    {
        return $this->request(Request::METHOD_PUT, $uri, $options);
    }

    /**
     * @throws ApiCallFailedException
     */
    protected function patch(string $uri, array $options = []): array
    {
        return $this->request(Request::METHOD_PATCH, $uri, $options);
    }

    /**
     * @throws ApiCallFailedException
     */
    protected function delete(string $uri, array $options = []): array
    {
        return $this->request(Request::METHOD_DELETE, $uri, $options);
    }

    /**
     * @throws ApiCallFailedException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            return $this->parseResponse($this->client->request($method, $uri, $options));
        } catch (RequestException $exception) {
            throw ApiCallFailedException::forParameters(
                $exception,
                $method,
                $uri,
                $options,
                $exception->getResponse()?->getStatusCode() ?? Response::HTTP_INTERNAL_SERVER_ERROR,
                $this->tryDecodeJsonBody($exception->getResponse()),
            );
        } catch (Throwable $exception) {
            throw ApiCallFailedException::forParameters($exception, $method, $uri, $options);
        }
    }

    /**
     * @throws JsonException
     */
    private function parseResponse(ResponseInterface $response): array
    {
        return empty($body = $response->getBody()->getContents()) ? [] : $this->decodeJson($body);
    }

    private function tryDecodeJsonBody(?ResponseInterface $response): ?array
    {
        if ($response === null) {
            return null;
        }

        try {
            return $this->decodeJson((string) $response->getBody());
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @throws JsonException
     */
    private function decodeJson(string $body): array
    {
        return (array) json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Application\DTO\Messages\TextMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolCallMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolResultMessage;
use App\Modules\Ai\Domain\Contracts\LlmClientInterface;
use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Enums\MessageRole;
use App\Modules\Ai\Domain\Exceptions\LlmCallFailedException;
use App\Modules\Ai\Domain\Exceptions\LlmCredentialRejectedException;
use App\Modules\Ai\Domain\Exceptions\UntranslatableMessageException;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

class OpenAiClient extends ApiClientAbstract implements LlmClientInterface
{
    private const string CHAT_COMPLETIONS_ENDPOINT = 'chat/completions';

    private const string STRUCTURED_OUTPUT_NAME = 'structured_output';

    public function provider(): LlmProvider
    {
        return LlmProvider::OpenAi;
    }

    /**
     * @throws LlmCallFailedException
     */
    public function complete(LlmRequestDTO $request): LlmResponseDTO
    {
        try {
            return $this->toResponse(
                $this->post(self::CHAT_COMPLETIONS_ENDPOINT, [RequestOptions::JSON => self::toPayload($request)]),
                $request,
            );
        } catch (ApiCallFailedException $exception) {
            throw self::rejectsCredential($exception)
                ? LlmCredentialRejectedException::forProvider(LlmProvider::OpenAi, $exception)
                : LlmCallFailedException::forProvider($exception, LlmProvider::OpenAi, $request->model);
        }
    }

    private static function toPayload(LlmRequestDTO $request): array
    {
        return array_filter([
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            // Caching is automatic on the prefix, so the system message must be first and
            // byte-identical between calls: ordering is the only lever this provider gives.
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ...self::toMessages($request->messages),
            ],
            'tools' => self::toTools($request->tools),
            'response_format' => $request->jsonSchema === null ? [] : [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => self::STRUCTURED_OUTPUT_NAME,
                    'schema' => $request->jsonSchema,
                    'strict' => self::isStrictable($request->jsonSchema),
                ],
            ],
        ], static fn (mixed $value): bool => $value !== []);
    }

    /** A tool turn expands to several messages here, so this flattens rather than maps. */
    private static function toMessages(array $messages): array
    {
        return collect($messages)
            ->flatMap(static fn (LlmMessageInterface $message): array => match (true) {
                $message instanceof TextMessage => [['role' => $message->role->value, 'content' => $message->text]],
                $message instanceof ToolCallMessage => [[
                    'role' => MessageRole::Assistant->value,
                    'content' => $message->text,
                    'tool_calls' => self::toToolCalls($message->calls),
                ]],
                $message instanceof ToolResultMessage => self::toToolMessages($message->results),
                default => throw UntranslatableMessageException::forProvider(LlmProvider::OpenAi, $message::class),
            })
            ->values()
            ->all();
    }

    private static function toToolCalls(array $calls): array
    {
        return array_map(static fn (array $call): array => [
            'id' => $call['id'],
            'type' => 'function',
            // Arguments travel as a JSON-encoded string on this provider, unlike the others.
            'function' => ['name' => $call['name'], 'arguments' => json_encode($call['input'])],
        ], $calls);
    }

    private static function toToolMessages(array $results): array
    {
        return array_map(static fn (array $result): array => [
            'role' => 'tool',
            'tool_call_id' => $result['id'],
            // There is no is_error field on this provider; the content string is the only
            // channel a failure can travel through, so it is marked there.
            'content' => ($result['is_error'] ?? false) ? 'Error: '.$result['content'] : $result['content'],
        ], $results);
    }

    /**
     * Strict mode rejects the request outright unless every declared property is required
     * and additional ones are banned, so it is opt-in per schema rather than always on.
     */
    private static function isStrictable(array $schema): bool
    {
        return ($schema['additionalProperties'] ?? null) === false
            && array_keys($schema['properties'] ?? []) === ($schema['required'] ?? []);
    }

    private static function toTools(array $tools): array
    {
        return array_map(static fn (array $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['schema'],
            ],
        ], $tools);
    }

    private function toResponse(array $body, LlmRequestDTO $request): LlmResponseDTO
    {
        return new LlmResponseDTO(
            self::text($body),
            self::structured($body, $request),
            self::toolCalls($body),
            (string) ($body['choices'][0]['finish_reason'] ?? ''),
            LlmProvider::OpenAi->uncachedInputTokens(
                (int) ($body['usage']['prompt_tokens'] ?? 0),
                self::cachedTokens($body),
            ),
            // reasoning_tokens is a slice of completion_tokens here, so the visible output
            // is what is left after removing it. The ledger adds the two back together.
            max((int) ($body['usage']['completion_tokens'] ?? 0) - self::reasoningTokens($body), 0),
            self::cachedTokens($body),
            self::reasoningTokens($body),
            (string) ($body['model'] ?? $request->model),
            LlmProvider::OpenAi,
            $body,
        );
    }

    private static function reasoningTokens(array $body): int
    {
        return (int) ($body['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0);
    }

    /** A 401 here carries type `invalid_request_error`, so only error.code identifies it. */
    private static function rejectsCredential(ApiCallFailedException $exception): bool
    {
        return $exception->getContext()['http_status_code'] === Response::HTTP_UNAUTHORIZED
            && ($exception->getContext()['response_body']['error']['code'] ?? null) === 'invalid_api_key';
    }

    private static function cachedTokens(array $body): int
    {
        return (int) ($body['usage']['prompt_tokens_details']['cached_tokens'] ?? 0);
    }

    private static function text(array $body): ?string
    {
        return $body['choices'][0]['message']['content'] ?? null;
    }

    private static function structured(array $body, LlmRequestDTO $request): ?array
    {
        return $request->jsonSchema === null ? null : json_decode((string) self::text($body), true);
    }

    private static function toolCalls(array $body): array
    {
        return collect($body['choices'][0]['message']['tool_calls'] ?? [])
            ->map(static fn (array $call): array => [
                'id' => (string) $call['id'],
                'name' => (string) $call['function']['name'],
                // Unlike the other two providers, arguments arrive as a JSON-encoded string.
                'input' => (array) json_decode((string) ($call['function']['arguments'] ?? '{}'), true),
            ])
            ->values()
            ->all();
    }
}

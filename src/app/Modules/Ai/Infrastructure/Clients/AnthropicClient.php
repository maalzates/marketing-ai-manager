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

class AnthropicClient extends ApiClientAbstract implements LlmClientInterface
{
    private const string MESSAGES_ENDPOINT = 'v1/messages';

    private const string TOOL_USE_BLOCK = 'tool_use';

    private const string TEXT_BLOCK = 'text';

    public function provider(): LlmProvider
    {
        return LlmProvider::Anthropic;
    }

    /**
     * @throws LlmCallFailedException
     */
    public function complete(LlmRequestDTO $request): LlmResponseDTO
    {
        try {
            return $this->toResponse(
                $this->post(self::MESSAGES_ENDPOINT, [RequestOptions::JSON => self::toPayload($request)]),
                $request,
            );
        } catch (ApiCallFailedException $exception) {
            throw self::rejectsCredential($exception)
                ? LlmCredentialRejectedException::forProvider(LlmProvider::Anthropic, $exception)
                : LlmCallFailedException::forProvider($exception, LlmProvider::Anthropic, $request->model);
        }
    }

    private static function toPayload(LlmRequestDTO $request): array
    {
        return array_filter([
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            // The breakpoint goes on the last block of the stable head; everything after it
            // is re-read on every call, which is exactly what the assembler ordered for.
            'system' => [[
                'type' => self::TEXT_BLOCK,
                'text' => $request->systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'tools' => self::toTools($request->tools),
            'output_config' => $request->jsonSchema === null ? [] : [
                'format' => ['type' => 'json_schema', 'schema' => $request->jsonSchema],
            ],
            'messages' => self::toMessages($request->messages),
        ], static fn (mixed $value): bool => $value !== []);
    }

    private static function toMessages(array $messages): array
    {
        return array_map(static fn (LlmMessageInterface $message): array => match (true) {
            $message instanceof TextMessage => ['role' => $message->role->value, 'content' => $message->text],
            $message instanceof ToolCallMessage => [
                'role' => MessageRole::Assistant->value,
                'content' => [...self::textBlock($message->text), ...self::toolUseBlocks($message->calls)],
            ],
            // Every result of a parallel turn goes back in ONE user message: splitting them
            // across messages teaches the model to stop calling tools in parallel.
            $message instanceof ToolResultMessage => [
                'role' => MessageRole::User->value,
                'content' => self::toolResultBlocks($message->results),
            ],
            default => throw UntranslatableMessageException::forProvider(LlmProvider::Anthropic, $message::class),
        }, $messages);
    }

    private static function textBlock(?string $text): array
    {
        return $text === null || $text === '' ? [] : [['type' => self::TEXT_BLOCK, 'text' => $text]];
    }

    private static function toolUseBlocks(array $calls): array
    {
        return array_map(static fn (array $call): array => [
            'type' => self::TOOL_USE_BLOCK,
            'id' => $call['id'],
            'name' => $call['name'],
            'input' => $call['input'],
        ], $calls);
    }

    private static function toolResultBlocks(array $results): array
    {
        return array_map(static fn (array $result): array => array_filter([
            'type' => 'tool_result',
            'tool_use_id' => $result['id'],
            'content' => $result['content'],
            'is_error' => $result['is_error'] ?? false,
        ], static fn (mixed $value): bool => $value !== false), $results);
    }

    private static function toTools(array $tools): array
    {
        return array_map(static fn (array $tool): array => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['schema'],
        ], $tools);
    }

    private function toResponse(array $body, LlmRequestDTO $request): LlmResponseDTO
    {
        return new LlmResponseDTO(
            self::text($body),
            self::structured($body, $request),
            self::toolCalls($body),
            (string) ($body['stop_reason'] ?? ''),
            // A cache write is billed at the full input rate, so it belongs with the uncached
            // count rather than with the reads.
            LlmProvider::Anthropic->uncachedInputTokens(
                (int) ($body['usage']['input_tokens'] ?? 0),
                self::cachedTokens($body),
            ) + (int) ($body['usage']['cache_creation_input_tokens'] ?? 0),
            (int) ($body['usage']['output_tokens'] ?? 0),
            self::cachedTokens($body),
            // This surface reports no separate reasoning counter; thinking is inside
            // output_tokens and cannot be broken out from the response body.
            0,
            (string) ($body['model'] ?? $request->model),
            LlmProvider::Anthropic,
            $body,
        );
    }

    private static function cachedTokens(array $body): int
    {
        return (int) ($body['usage']['cache_read_input_tokens'] ?? 0);
    }

    /** Documented rule: 401 plus error.type — never the status alone, and never the message. */
    private static function rejectsCredential(ApiCallFailedException $exception): bool
    {
        return $exception->getContext()['http_status_code'] === Response::HTTP_UNAUTHORIZED
            && ($exception->getContext()['response_body']['error']['type'] ?? null) === 'authentication_error';
    }

    private static function text(array $body): ?string
    {
        return collect($body['content'] ?? [])
            ->filter(static fn (array $block): bool => ($block['type'] ?? '') === self::TEXT_BLOCK)
            ->pluck(self::TEXT_BLOCK)
            ->implode("\n") ?: null;
    }

    private static function structured(array $body, LlmRequestDTO $request): ?array
    {
        return $request->jsonSchema === null ? null : json_decode((string) self::text($body), true);
    }

    private static function toolCalls(array $body): array
    {
        return collect($body['content'] ?? [])
            ->filter(static fn (array $block): bool => ($block['type'] ?? '') === self::TOOL_USE_BLOCK)
            ->map(static fn (array $block): array => [
                'id' => (string) $block['id'],
                'name' => (string) $block['name'],
                'input' => (array) ($block['input'] ?? []),
            ])
            ->values()
            ->all();
    }
}

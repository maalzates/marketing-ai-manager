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

/**
 * Speaks the legacy `generateContent` surface rather than Interactions: Interactions keeps
 * the transcript server-side behind `previous_interaction_id`, which would make this the
 * only stateful adapter of the three and force the caching and history rules to fork.
 */
class GeminiClient extends ApiClientAbstract implements LlmClientInterface
{
    private const string GENERATE_CONTENT_ENDPOINT = 'models/%s:generateContent';

    private const string MODEL_ROLE = 'model';

    public function provider(): LlmProvider
    {
        return LlmProvider::Gemini;
    }

    /**
     * @throws LlmCallFailedException
     */
    public function complete(LlmRequestDTO $request): LlmResponseDTO
    {
        try {
            return $this->toResponse(
                $this->post(
                    sprintf(self::GENERATE_CONTENT_ENDPOINT, $request->model),
                    [RequestOptions::JSON => self::toPayload($request)],
                ),
                $request,
            );
        } catch (ApiCallFailedException $exception) {
            throw self::rejectsCredential($exception)
                ? LlmCredentialRejectedException::forProvider(LlmProvider::Gemini, $exception)
                : LlmCallFailedException::forProvider($exception, LlmProvider::Gemini, $request->model);
        }
    }

    private static function toPayload(LlmRequestDTO $request): array
    {
        return array_filter([
            'systemInstruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'contents' => self::toContents($request->messages),
            'tools' => $request->tools === [] ? [] : [['functionDeclarations' => self::toFunctionDeclarations($request->tools)]],
            'generationConfig' => array_filter([
                'maxOutputTokens' => $request->maxTokens,
                'temperature' => $request->temperature,
                'responseMimeType' => $request->jsonSchema === null ? null : 'application/json',
                'responseSchema' => $request->jsonSchema,
            ], static fn (mixed $value): bool => $value !== null),
        ], static fn (mixed $value): bool => $value !== []);
    }

    private static function toContents(array $messages): array
    {
        return array_map(static fn (LlmMessageInterface $message): array => match (true) {
            $message instanceof TextMessage => [
                'role' => self::role($message->role),
                'parts' => [['text' => $message->text]],
            ],
            $message instanceof ToolCallMessage => [
                'role' => self::MODEL_ROLE,
                'parts' => [...self::textPart($message->text), ...self::functionCallParts($message->calls)],
            ],
            $message instanceof ToolResultMessage => [
                'role' => 'user',
                'parts' => self::functionResponseParts($message->results),
            ],
            default => throw UntranslatableMessageException::forProvider(LlmProvider::Gemini, $message::class),
        }, $messages);
    }

    private static function role(MessageRole $role): string
    {
        return $role === MessageRole::User ? 'user' : self::MODEL_ROLE;
    }

    private static function textPart(?string $text): array
    {
        return $text === null || $text === '' ? [] : [['text' => $text]];
    }

    private static function functionCallParts(array $calls): array
    {
        return array_map(static fn (array $call): array => [
            'functionCall' => ['name' => $call['name'], 'args' => $call['input']],
        ], $calls);
    }

    /** This provider addresses a result by function name, not by call id. */
    private static function functionResponseParts(array $results): array
    {
        return array_map(static fn (array $result): array => [
            'functionResponse' => [
                'name' => $result['name'],
                'response' => ($result['is_error'] ?? false)
                    ? ['error' => $result['content']]
                    : ['result' => $result['content']],
            ],
        ], $results);
    }

    private static function toFunctionDeclarations(array $tools): array
    {
        return array_map(static fn (array $tool): array => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $tool['schema'],
        ], $tools);
    }

    private function toResponse(array $body, LlmRequestDTO $request): LlmResponseDTO
    {
        return new LlmResponseDTO(
            self::text($body),
            self::structured($body, $request),
            self::toolCalls($body),
            (string) ($body['candidates'][0]['finishReason'] ?? ''),
            LlmProvider::Gemini->uncachedInputTokens(
                (int) ($body['usageMetadata']['promptTokenCount'] ?? 0),
                self::cachedTokens($body),
            ),
            (int) ($body['usageMetadata']['candidatesTokenCount'] ?? 0),
            self::cachedTokens($body),
            // Thought tokens sit outside candidatesTokenCount, so they add rather than divide.
            (int) ($body['usageMetadata']['total_thought_tokens'] ?? 0),
            (string) ($body['modelVersion'] ?? $request->model),
            LlmProvider::Gemini,
            $body,
        );
    }

    /**
     * The trap of this provider: a bad key comes back as 400, not 401, so the status alone
     * says nothing. The 401 branch is the new `AQ.` key format the REST surface refuses.
     */
    private static function rejectsCredential(ApiCallFailedException $exception): bool
    {
        return collect($exception->getContext()['response_body']['error']['details'] ?? [])
            ->contains(static fn (array $detail): bool => ($detail['reason'] ?? null) === 'API_KEY_INVALID')
            || ($exception->getContext()['response_body']['error']['status'] ?? null) === 'ACCESS_TOKEN_TYPE_UNSUPPORTED'
            || $exception->getContext()['http_status_code'] === Response::HTTP_UNAUTHORIZED;
    }

    private static function cachedTokens(array $body): int
    {
        return (int) ($body['usageMetadata']['cachedContentTokenCount'] ?? 0);
    }

    private static function text(array $body): ?string
    {
        return collect($body['candidates'][0]['content']['parts'] ?? [])
            ->pluck('text')
            ->filter()
            ->implode("\n") ?: null;
    }

    private static function structured(array $body, LlmRequestDTO $request): ?array
    {
        return $request->jsonSchema === null ? null : json_decode((string) self::text($body), true);
    }

    /**
     * `generateContent` returns no call identifier, but the tool loop needs a stable handle
     * to match each result to its call, so the position in the part list becomes the id.
     */
    private static function toolCalls(array $body): array
    {
        return collect($body['candidates'][0]['content']['parts'] ?? [])
            ->filter(static fn (array $part): bool => isset($part['functionCall']))
            ->values()
            ->map(static fn (array $part, int $index): array => [
                'id' => 'call_'.$index,
                'name' => (string) $part['functionCall']['name'],
                'input' => (array) ($part['functionCall']['args'] ?? []),
            ])
            ->all();
    }
}

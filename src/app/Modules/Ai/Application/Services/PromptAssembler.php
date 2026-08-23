<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\Messages\TextMessage;
use App\Modules\Ai\Domain\Enums\MessageRole;
use App\Modules\Ai\Domain\Exceptions\EmptyLlmRequestException;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * The single place prompt ordering is decided. Every provider caches on a byte-identical
 * prefix, so the static head — domain knowledge, account context, sorted tool definitions —
 * is built here and the volatile turn always goes last. A stray timestamp near the top
 * costs money on all three providers and reports no error on two of them.
 */
readonly class PromptAssembler
{
    private const string MAX_OUTPUT_TOKENS_KEY = 'ai.limits.max_output_tokens';

    private const string TEMPERATURE_KEY = 'ai.limits.temperature';

    public function __construct(private SettingsService $settings) {}

    /** @param  array<string, mixed>  $context */
    public function assemble(AiRequestDTO $request, string $model, array $context, ?array $jsonSchema): LlmRequestDTO
    {
        return new LlmRequestDTO(
            $request->accountId,
            $request->task,
            $model,
            self::staticHead($context),
            self::messages($request),
            self::deterministicTools($request->tools),
            $jsonSchema,
            $request->maxTokens ?? (int) $this->settings->get(self::MAX_OUTPUT_TOKENS_KEY, $request->accountId),
            $request->temperature ?? (float) $this->settings->get(self::TEMPERATURE_KEY, $request->accountId),
        );
    }

    /**
     * An empty prompt means the history already ends with the turn to send — a tool loop
     * whose last message is a tool_result has nothing to append and must not have a filler
     * user turn invented for it.
     */
    private static function messages(AiRequestDTO $request): array
    {
        $messages = $request->prompt === ''
            ? $request->history
            : [...$request->history, new TextMessage(MessageRole::User, $request->prompt)];

        return $messages === [] ? throw EmptyLlmRequestException::forTask($request->task) : $messages;
    }

    private static function staticHead(array $context): string
    {
        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Unsorted tool definitions reorder between requests and silently break every cache. */
    private static function deterministicTools(array $tools): array
    {
        return collect($tools)->sortBy('name')->values()->all();
    }
}

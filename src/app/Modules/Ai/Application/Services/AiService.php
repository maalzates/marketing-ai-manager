<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Audit\Application\DTO\RecordLlmUsageDTO;
use App\Modules\Audit\Application\Services\UsageService;

/**
 * The only door other modules use to reach a model. Routing, budget, prompt ordering and
 * the consumption ledger are enforced here, so no caller can reach a provider without them.
 */
readonly class AiService
{
    /**
     * Budget has to be checked before the call, when the real token count is still unknown.
     * Four characters per token is the accepted rough ratio across all three tokenisers.
     */
    private const int CHARACTERS_PER_TOKEN = 4;

    public function __construct(
        private LlmClientFactoryInterface $clients,
        private ModelRouter $router,
        private TokenBudgetGuard $budget,
        private PromptAssembler $assembler,
        private PromptContextBuilder $context,
        private StructuredOutputValidator $validator,
        private CostCalculator $cost,
        private UsageService $usage,
    ) {}

    public function complete(AiRequestDTO $request): LlmResponseDTO
    {
        return $this->dispatch($request, null);
    }

    /** @return array<string, mixed> */
    public function structured(AiRequestDTO $request, array $jsonSchema): array
    {
        return $this->validator->validate($this->dispatch($request, $jsonSchema), $jsonSchema);
    }

    private function dispatch(AiRequestDTO $request, ?array $jsonSchema): LlmResponseDTO
    {
        $llmRequest = $this->assembler->assemble(
            $request,
            $this->router->modelFor($request->task, $request->accountId),
            [...$this->context->build($request->accountId, $request->strategyId), ...$request->context],
            $jsonSchema,
        );

        $this->budget->assertWithinBudget($request->accountId, self::estimatedTokens($llmRequest));

        return $this->record($request, $llmRequest->model, $this->clients->forAccount(
            $request->accountId,
            $this->router->providerFor($llmRequest->model, $request->accountId),
        )->complete($llmRequest));
    }

    /**
     * Priced by the model that was *asked for*, not the one the answer names. Providers
     * resolve an alias to a dated snapshot — `gpt-4.1-nano` comes back as
     * `gpt-4.1-nano-2025-04-14` — and that id is not in the catalogue, so pricing by it
     * failed the whole call. The served id is still what the ledger stores: the audit trail
     * has to say which snapshot answered.
     */
    private function record(AiRequestDTO $request, string $requestedModel, LlmResponseDTO $response): LlmResponseDTO
    {
        $this->usage->recordLlmCall(new RecordLlmUsageDTO(
            $request->accountId,
            $request->userId,
            $request->task->value,
            $response->provider->value,
            $response->model,
            $response->inputTokens,
            $response->outputTokens,
            $response->cachedInputTokens,
            $this->cost->estimate(
                $requestedModel,
                $response->inputTokens,
                $response->outputTokens,
                $response->cachedInputTokens,
                $response->reasoningTokens,
            ),
            $response->reasoningTokens,
        ));

        return $response;
    }

    private static function estimatedTokens(LlmRequestDTO $request): int
    {
        return intdiv(
            strlen($request->systemPrompt.json_encode($request->messages).json_encode($request->tools)),
            self::CHARACTERS_PER_TOKEN,
        ) + $request->maxTokens;
    }
}

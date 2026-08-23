<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Exceptions\UnknownAiTaskException;
use App\Modules\Ai\Domain\Exceptions\UnknownLlmModelException;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * Turns "what am I doing" into "which model answers it", per account. Mechanical tasks can
 * ride a cheap model while judgement tasks ride a capable one, which is the whole point of
 * the per-task selector in Settings → Models.
 */
readonly class ModelRouter
{
    private const string SAME_FOR_ALL_KEY = 'ai.models.same_for_all';

    private const string PER_TASK_KEY_PREFIX = 'ai.models.per_task.';

    public function __construct(private SettingsService $settings) {}

    public function modelFor(AiTask $task, int $accountId): string
    {
        $key = self::PER_TASK_KEY_PREFIX.$this->effectiveTask($task, $accountId)->value;
        $model = (string) ($this->settings->get($key, $accountId) ?? throw UnknownAiTaskException::withoutModel($task, $key));

        $this->providerFor($model);

        return $model;
    }

    public function providerFor(string $model): LlmProvider
    {
        return collect(LlmProvider::cases())
            ->first(static fn (LlmProvider $provider): bool => array_key_exists($model, $provider->models()))
            ?? throw UnknownLlmModelException::withModel($model);
    }

    private function effectiveTask(AiTask $task, int $accountId): AiTask
    {
        return $this->settings->get(self::SAME_FOR_ALL_KEY, $accountId) === true ? AiTask::Chat : $task;
    }
}

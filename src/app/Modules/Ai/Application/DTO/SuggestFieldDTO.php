<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO;

use App\Modules\Ai\Domain\Enums\AiTask;

readonly class SuggestFieldDTO
{
    /** @param  array<string, mixed>  $context */
    public function __construct(
        public int $accountId,
        public ?int $userId,
        public AiTask $task,
        public string $target,
        public array $context,
        public ?int $strategyId = null,
    ) {}
}

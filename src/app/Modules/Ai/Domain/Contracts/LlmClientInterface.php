<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use App\Modules\Ai\Application\DTO\LlmRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Domain\Enums\LlmProvider;

/**
 * The seam that keeps provider differences inside Infrastructure: no Service ever
 * branches on which vendor is answering.
 */
interface LlmClientInterface
{
    public function complete(LlmRequestDTO $request): LlmResponseDTO;

    public function provider(): LlmProvider;
}

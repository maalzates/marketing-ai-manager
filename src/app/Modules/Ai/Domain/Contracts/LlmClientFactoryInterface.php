<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use App\Modules\Ai\Domain\Enums\LlmProvider;

interface LlmClientFactoryInterface
{
    public function forAccount(int $accountId, LlmProvider $provider): LlmClientInterface;
}

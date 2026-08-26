<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use App\Modules\Ai\Domain\Enums\LlmProvider;

interface ModelListClientFactoryInterface
{
    public function forAccount(int $accountId, LlmProvider $provider): ModelListClientInterface;
}

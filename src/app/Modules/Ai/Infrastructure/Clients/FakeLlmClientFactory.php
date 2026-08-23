<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Contracts\LlmClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use Closure;

/**
 * Container-level swap for feature tests: binding this over LlmClientFactoryInterface
 * replaces every provider call without a Service knowing a test is running.
 */
readonly class FakeLlmClientFactory implements LlmClientFactoryInterface
{
    /** @param  Closure(int, LlmProvider): LlmClientInterface  $resolver */
    public function __construct(private Closure $resolver) {}

    public function forAccount(int $accountId, LlmProvider $provider): LlmClientInterface
    {
        return ($this->resolver)($accountId, $provider);
    }
}

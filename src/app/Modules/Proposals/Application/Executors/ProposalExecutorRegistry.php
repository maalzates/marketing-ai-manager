<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Executors;

use App\Modules\Proposals\Domain\Contracts\ProposalExecutorInterface;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Domain\Exceptions\ProposalExecutorNotAvailableException;
use Illuminate\Contracts\Container\Container;

/**
 * One executor per proposal type, resolved by type. Adding a type later adds a row to the
 * map in the provider — it never widens what ProposalExecutionService can reach.
 */
readonly class ProposalExecutorRegistry
{
    /**
     * @param  array<string, class-string<ProposalExecutorInterface>>  $executors
     */
    public function __construct(private Container $container, private array $executors) {}

    public function resolve(ProposalType $type): ProposalExecutorInterface
    {
        return $this->container->make(
            $this->executors[$type->value] ?? throw ProposalExecutorNotAvailableException::forType($type),
        );
    }
}

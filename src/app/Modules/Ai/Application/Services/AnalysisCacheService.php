<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Contracts\AnalysisCacheRepositoryInterface;
use Closure;

/**
 * Never re-analyse what was already analysed: the same post, batch or brief hashed to the
 * same input is served from the ledger instead of costing the account another call.
 */
readonly class AnalysisCacheService
{
    public function __construct(private AnalysisCacheRepositoryInterface $repository) {}

    /**
     * @param  Closure(): array  $callback
     * @return array<string, mixed>
     */
    public function remember(int $accountId, string $kind, array $input, Closure $callback): array
    {
        $hash = self::hash($input);

        return ($this->repository->find($accountId, $kind, $hash)
            ?? $this->repository->store($accountId, $kind, $hash, $callback()))->result;
    }

    private static function hash(array $input): string
    {
        return hash('sha256', (string) json_encode(self::canonicalise($input)));
    }

    /** Key order is not meaning: two callers describing the same input must hash alike. */
    private static function canonicalise(array $input): array
    {
        ksort($input);

        return array_map(
            static fn (mixed $value): mixed => is_array($value) ? self::canonicalise($value) : $value,
            $input,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Repositories;

use App\Modules\Ai\Domain\Contracts\AnalysisCacheRepositoryInterface;
use App\Modules\Ai\Domain\Exceptions\AnalysisCacheWriteFailedException;
use App\Modules\Ai\Infrastructure\Persistence\AiAnalysisCacheEntry;
use Throwable;

readonly class AnalysisCacheRepository implements AnalysisCacheRepositoryInterface
{
    public function __construct(private AiAnalysisCacheEntry $model) {}

    public function find(int $accountId, string $kind, string $inputHash): ?AiAnalysisCacheEntry
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('kind', $kind)
            ->where('input_hash', $inputHash)
            ->first();
    }

    public function store(int $accountId, string $kind, string $inputHash, array $result): AiAnalysisCacheEntry
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $accountId,
                'kind' => $kind,
                'input_hash' => $inputHash,
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            throw AnalysisCacheWriteFailedException::wrap($exception, context: [
                'account_id' => $accountId,
                'kind' => $kind,
                'input_hash' => $inputHash,
            ]);
        }
    }
}

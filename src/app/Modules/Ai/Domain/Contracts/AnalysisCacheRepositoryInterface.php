<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use App\Modules\Ai\Infrastructure\Persistence\AiAnalysisCacheEntry;

interface AnalysisCacheRepositoryInterface
{
    public function find(int $accountId, string $kind, string $inputHash): ?AiAnalysisCacheEntry;

    public function store(int $accountId, string $kind, string $inputHash, array $result): AiAnalysisCacheEntry;
}

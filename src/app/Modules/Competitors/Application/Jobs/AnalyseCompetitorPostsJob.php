<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Jobs;

use App\Modules\Competitors\Application\Services\CompetitorPatternService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyseCompetitorPostsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $competitorId) {}

    public function handle(CompetitorPatternService $service): void
    {
        $service->extract($this->accountId, $this->competitorId);
    }
}

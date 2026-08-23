<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Jobs;

use App\Modules\Competitors\Application\Services\CommentMiningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MineCommentIdeasJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $competitorId) {}

    public function handle(CommentMiningService $service): void
    {
        $service->mine($this->accountId, $this->competitorId);
    }
}

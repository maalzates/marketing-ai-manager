<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Jobs;

use App\Modules\Competitors\Application\Services\CommentSentimentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyseCommentSentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $competitorId) {}

    public function handle(CommentSentimentService $service): void
    {
        $service->analyse($this->accountId, $this->competitorId);
    }
}

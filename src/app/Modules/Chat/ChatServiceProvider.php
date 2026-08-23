<?php

declare(strict_types=1);

namespace App\Modules\Chat;

use App\Modules\Chat\Application\Services\KeywordHistorySearch;
use App\Modules\Chat\Domain\Contracts\ChatConversationRepositoryInterface;
use App\Modules\Chat\Domain\Contracts\ChatMessageRepositoryInterface;
use App\Modules\Chat\Domain\Contracts\HistorySearchInterface;
use App\Modules\Chat\Infrastructure\Repositories\ChatConversationRepository;
use App\Modules\Chat\Infrastructure\Repositories\ChatMessageRepository;
use App\Modules\Chat\Presentation\Tools\GetCompetitorInsightsTool;
use App\Modules\Chat\Presentation\Tools\GetExperimentsTool;
use App\Modules\Chat\Presentation\Tools\GetProposalsTool;
use App\Modules\Chat\Presentation\Tools\GetStrategySummaryTool;
use App\Modules\Chat\Presentation\Tools\ProposeBudgetChangeTool;
use App\Modules\Chat\Presentation\Tools\ProposeCampaignTool;
use App\Modules\Chat\Presentation\Tools\ProposePauseTool;
use App\Modules\Chat\Presentation\Tools\SearchHistoryTool;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    /**
     * The catalogue the model sees. Read tools run; mutation tools only ever persist a
     * Proposal — none of them can reach an executing Service.
     *
     * @var list<class-string<ToolAbstract>>
     */
    private const array TOOLS = [
        GetStrategySummaryTool::class,
        GetExperimentsTool::class,
        GetCompetitorInsightsTool::class,
        GetProposalsTool::class,
        SearchHistoryTool::class,
        ProposeCampaignTool::class,
        ProposeBudgetChangeTool::class,
        ProposePauseTool::class,
    ];

    public function register(): void
    {
        $this->app->bind(ChatConversationRepositoryInterface::class, ChatConversationRepository::class);
        $this->app->bind(ChatMessageRepositoryInterface::class, ChatMessageRepository::class);
        // The seam of `core.md` §4 layer 3: an embedding-backed search replaces this binding.
        $this->app->bind(HistorySearchInterface::class, KeywordHistorySearch::class);
    }

    public function boot(ToolRegistry $registry): void
    {
        foreach (self::TOOLS as $tool) {
            $registry->register($tool);
        }
    }
}

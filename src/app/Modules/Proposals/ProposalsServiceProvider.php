<?php

declare(strict_types=1);

namespace App\Modules\Proposals;

use App\Modules\Proposals\Application\Executors\BudgetChangeExecutor;
use App\Modules\Proposals\Application\Executors\CloseExperimentExecutor;
use App\Modules\Proposals\Application\Executors\CreateCampaignExecutor;
use App\Modules\Proposals\Application\Executors\PauseExperimentExecutor;
use App\Modules\Proposals\Application\Executors\ProposalExecutorRegistry;
use App\Modules\Proposals\Application\Executors\ScheduleContentExecutor;
use App\Modules\Proposals\Application\Services\ProposalExecutionService;
use App\Modules\Proposals\Domain\Contracts\ProposalOutcomeRecorderInterface;
use App\Modules\Proposals\Domain\Contracts\ProposalRepositoryInterface;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Domain\Exceptions\ProposalExecutionNotPermittedException;
use App\Modules\Proposals\Infrastructure\Adapters\ProposalOutcomeRecorder;
use App\Modules\Proposals\Infrastructure\Repositories\ProposalRepository;
use App\Modules\Proposals\Presentation\Http\Controllers\Api\AcceptProposalController;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ProposalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProposalRepositoryInterface::class, ProposalRepository::class);
        $this->app->bind(ProposalOutcomeRecorderInterface::class, ProposalOutcomeRecorder::class);

        $this->app->singleton(ProposalExecutorRegistry::class, fn (Application $app): ProposalExecutorRegistry => new ProposalExecutorRegistry($app, [
            ProposalType::CreateCampaign->value => CreateCampaignExecutor::class,
            ProposalType::BudgetChange->value => BudgetChangeExecutor::class,
            ProposalType::PauseExperiment->value => PauseExperimentExecutor::class,
            ProposalType::CloseExperiment->value => CloseExperimentExecutor::class,
            ProposalType::ScheduleContent->value => ScheduleContentExecutor::class,
        ]));

        $this->restrictExecutionToTheApprovalDoor();
    }

    /**
     * Rule 11 of the backend standard, enforced by the container instead of by discipline:
     * asking for a ProposalExecutionService anywhere other than the accept controller
     * throws. A chat tool that type-hints it does not get a crippled instance — it gets an
     * exception, at resolution time, before any mutation can start.
     */
    private function restrictExecutionToTheApprovalDoor(): void
    {
        $this->app->bind(
            ProposalExecutionService::class,
            fn (): never => throw ProposalExecutionNotPermittedException::outsideApprovalDoor(),
        );

        $this->app->when(AcceptProposalController::class)
            ->needs(ProposalExecutionService::class)
            ->give(fn (Application $app): ProposalExecutionService => $app->build(ProposalExecutionService::class));
    }
}

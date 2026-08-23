<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Contracts;

use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Domain\Enums\CampaignStatus;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CampaignRepositoryInterface
{
    /** @return Collection<int, Campaign> */
    public function forAccount(CampaignFilterDTO $filters): Collection;

    /**
     * Across accounts, for the scheduled sweep: every campaign that still has delivery to
     * fetch. Whether the experiment behind it is still running is not decided here.
     *
     * @return Collection<int, Campaign>
     */
    public function stillAccruingMetrics(): Collection;

    public function findForExperiment(int $accountId, int $experimentId): ?Campaign;

    public function startLaunch(LaunchCampaignDTO $dto, bool $sandbox): Campaign;

    /** @param array<string, string|null> $externalIds */
    public function recordExternalIds(Campaign $campaign, array $externalIds): Campaign;

    public function setStatus(Campaign $campaign, CampaignStatus $status): Campaign;

    public function setBudget(Campaign $campaign, BudgetPlan $budget): Campaign;

    public function recordSync(Campaign $campaign, ?LearningStage $stage, CarbonInterface $syncedAt): Campaign;
}

<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Repositories;

use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Domain\Enums\CampaignStatus;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Domain\Exceptions\CampaignPersistenceFailedException;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class CampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(private Campaign $model) {}

    /**
     * @return Collection<int, Campaign>
     */
    public function forAccount(CampaignFilterDTO $filters): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when(
                $filters->experimentId,
                fn (Builder $query, int $experimentId) => $query->where('experiment_id', $experimentId),
            )
            ->when(
                $filters->status,
                fn (Builder $query, CampaignStatus $status) => $query->where('status', $status->value),
            )
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Campaign>
     */
    public function stillAccruingMetrics(): Collection
    {
        return $this->model->newQuery()
            ->whereNotNull('external_campaign_id')
            ->whereIn('status', collect(CampaignStatus::cases())
                ->filter(fn (CampaignStatus $status): bool => $status->keepsAccruingMetrics())
                ->map(fn (CampaignStatus $status): string => $status->value))
            ->get();
    }

    public function findForExperiment(int $accountId, int $experimentId): ?Campaign
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->first();
    }

    /**
     * Written before the first provider call so a launch that dies halfway leaves a row to
     * resume from — Meta deduplicates creatives but not campaigns, ad sets or ads.
     */
    public function startLaunch(LaunchCampaignDTO $dto, bool $sandbox): Campaign
    {
        try {
            return $this->model->newQuery()->updateOrCreate(
                ['account_id' => $dto->accountId, 'experiment_id' => $dto->experimentId],
                [
                    'objective' => $dto->objective,
                    'daily_budget' => $dto->budget->daily,
                    'lifetime_budget' => $dto->budget->lifetime,
                    'targeting' => $dto->targeting,
                    'status' => CampaignStatus::Launching,
                    'advantage_plus_creative' => $dto->advantagePlusCreative,
                    'sandbox' => $sandbox,
                ],
            );
        } catch (Throwable $exception) {
            throw CampaignPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'experiment_id' => $dto->experimentId,
            ]);
        }
    }

    /**
     * @param  array<string, string|null>  $externalIds
     */
    public function recordExternalIds(Campaign $campaign, array $externalIds): Campaign
    {
        return $this->persist($campaign, array_filter($externalIds, is_string(...)));
    }

    public function setStatus(Campaign $campaign, CampaignStatus $status): Campaign
    {
        return $this->persist($campaign, ['status' => $status]);
    }

    public function setBudget(Campaign $campaign, BudgetPlan $budget): Campaign
    {
        return $this->persist($campaign, [
            'daily_budget' => $budget->daily,
            'lifetime_budget' => $budget->lifetime,
        ]);
    }

    public function recordSync(Campaign $campaign, ?LearningStage $stage, CarbonInterface $syncedAt): Campaign
    {
        return $this->persist($campaign, ['learning_stage' => $stage, 'last_synced_at' => $syncedAt]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Campaign $campaign, array $attributes): Campaign
    {
        try {
            $campaign->update($attributes);

            return $campaign->refresh();
        } catch (Throwable $exception) {
            throw CampaignPersistenceFailedException::wrap($exception, context: [
                'campaign_id' => $campaign->id,
                'attributes' => array_keys($attributes),
            ]);
        }
    }
}

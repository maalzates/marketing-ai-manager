<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Executors;

use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Application\Jobs\LaunchCampaignJob;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Proposals\Domain\Contracts\ProposalExecutorInterface;
use App\Modules\Proposals\Domain\Exceptions\ProposalPayloadInvalidException;
use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

/**
 * Validates the launch inside the accept request and hands the platform calls to the queue.
 *
 * The split is not an optimisation: uploading an ad image means streaming it from this
 * application's own signed media route, so running the launch in a web worker has it call
 * its own php-fpm pool. Everything judgeable without Meta — assets ready, budget within
 * caps, not already launched — still fails the click synchronously with a real message.
 */
readonly class CreateCampaignExecutor implements ProposalExecutorInterface
{
    public function __construct(private CampaignService $campaigns) {}

    public function execute(Proposal $proposal): ExecutionOutcome
    {
        $launch = $this->launchDTO($proposal);

        $this->campaigns->launchable($launch);

        LaunchCampaignJob::dispatch($launch);

        return ExecutionOutcome::deferred([
            'status' => 'queued',
            'experiment_id' => $launch->experimentId,
            'asset_ids' => $launch->assetIds,
        ]);
    }

    private function launchDTO(Proposal $proposal): LaunchCampaignDTO
    {
        return new LaunchCampaignDTO(
            (int) $proposal->account_id,
            $this->experimentId($proposal),
            $this->objective($proposal),
            new BudgetPlan(
                $this->optionalFloat($proposal, 'daily_budget'),
                $this->optionalFloat($proposal, 'lifetime_budget'),
            ),
            $this->requiredArray($proposal, 'targeting'),
            $this->requiredArray($proposal, 'asset_ids'),
            $this->requiredString($proposal, 'page_id'),
            $this->optionalString($proposal, 'instagram_user_id'),
            $this->requiredString($proposal, 'message'),
            $this->optionalString($proposal, 'headline'),
            $this->optionalString($proposal, 'link'),
            $this->optionalString($proposal, 'call_to_action'),
            $this->optionalString($proposal, 'conversion_domain'),
            $this->requiredArray($proposal, 'special_ad_categories', []),
            $this->requiredArray($proposal, 'promoted_object', []),
            // core.md §11.5: Advantage+ creative stays off unless the human turned it on.
            (bool) ($proposal->payload['advantage_plus_creative'] ?? false),
            $proposal->decided_by_user_id === null ? null : (int) $proposal->decided_by_user_id,
        );
    }

    private function experimentId(Proposal $proposal): int
    {
        return $proposal->experiment_id === null
            ? throw ProposalPayloadInvalidException::missing($proposal->type, 'experiment_id')
            : (int) $proposal->experiment_id;
    }

    private function objective(Proposal $proposal): CampaignObjective
    {
        return CampaignObjective::tryFrom($this->requiredString($proposal, 'objective'))
            ?? throw ProposalPayloadInvalidException::missing($proposal->type, 'payload.objective');
    }

    private function requiredString(Proposal $proposal, string $key): string
    {
        return is_string($proposal->payload[$key] ?? null) && $proposal->payload[$key] !== ''
            ? $proposal->payload[$key]
            : throw ProposalPayloadInvalidException::missing($proposal->type, "payload.{$key}");
    }

    private function optionalString(Proposal $proposal, string $key): ?string
    {
        return is_string($proposal->payload[$key] ?? null) ? $proposal->payload[$key] : null;
    }

    private function optionalFloat(Proposal $proposal, string $key): ?float
    {
        return is_numeric($proposal->payload[$key] ?? null) ? (float) $proposal->payload[$key] : null;
    }

    /**
     * @param  array<string, mixed>|null  $default
     * @return array<string, mixed>
     */
    private function requiredArray(Proposal $proposal, string $key, ?array $default = null): array
    {
        return is_array($proposal->payload[$key] ?? null)
            ? $proposal->payload[$key]
            : $default ?? throw ProposalPayloadInvalidException::missing($proposal->type, "payload.{$key}");
    }
}

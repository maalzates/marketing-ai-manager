<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Proposals\Application\DTO\ProposeDTO;
use App\Modules\Proposals\Application\Services\ProposalService;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalType;

/**
 * Depends on ProposalService and nothing else. There is no import here that could reach a
 * Service which touches Meta — that absence is the approval invariant, not the prompt.
 */
readonly class ProposeCampaignTool extends ToolAbstract
{
    public function __construct(private ProposalService $proposals) {}

    public static function name(): string
    {
        return 'propose_campaign';
    }

    public static function description(): string
    {
        return 'Propose creating an advertising campaign for an experiment. Nothing is created: '
            .'this returns a proposal the user has to accept before anything reaches the platform.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'experiment_id' => [
                    'type' => 'integer',
                    'description' => 'Experiment the campaign belongs to.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Short headline the user will read on the proposal card.',
                ],
                'rationale' => [
                    'type' => 'string',
                    'description' => 'Why this campaign, argued against the account history.',
                ],
                'objective' => ['type' => 'string'],
                'daily_budget' => ['type' => 'number'],
                'lifetime_budget' => ['type' => 'number'],
                'targeting' => ['type' => 'object'],
            ],
            'required' => ['experiment_id', 'title', 'rationale', 'objective'],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->proposals->propose(new ProposeDTO(
            $context->accountId,
            $context->userId,
            ProposalType::CreateCampaign,
            ProposalOrigin::Chat,
            $input['title'],
            $input['rationale'],
            [
                'objective' => $input['objective'],
                'daily_budget' => $input['daily_budget'] ?? null,
                'lifetime_budget' => $input['lifetime_budget'] ?? null,
                'targeting' => $input['targeting'] ?? [],
            ],
            experimentId: (int) $input['experiment_id'],
        ))->toArray();
    }
}

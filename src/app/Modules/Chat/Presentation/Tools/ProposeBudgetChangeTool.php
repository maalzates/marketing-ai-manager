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
readonly class ProposeBudgetChangeTool extends ToolAbstract
{
    public function __construct(private ProposalService $proposals) {}

    public static function name(): string
    {
        return 'propose_budget_change';
    }

    public static function description(): string
    {
        return 'Propose changing the budget of a running experiment. Nothing changes: this '
            .'returns a proposal the user has to accept before the platform is touched.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'experiment_id' => ['type' => 'integer'],
                'title' => [
                    'type' => 'string',
                    'description' => 'Short headline the user will read on the proposal card.',
                ],
                'rationale' => [
                    'type' => 'string',
                    'description' => 'Why the change, argued against the experiment metrics.',
                ],
                'new_daily_budget' => ['type' => 'number'],
            ],
            'required' => ['experiment_id', 'title', 'rationale', 'new_daily_budget'],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->proposals->propose(new ProposeDTO(
            $context->accountId,
            $context->userId,
            ProposalType::BudgetChange,
            ProposalOrigin::Chat,
            $input['title'],
            $input['rationale'],
            ['new_daily_budget' => (float) $input['new_daily_budget']],
            experimentId: (int) $input['experiment_id'],
        ))->toArray();
    }
}

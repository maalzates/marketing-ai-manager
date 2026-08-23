<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Application\Services\ProposalService;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;

readonly class GetProposalsTool extends ToolAbstract
{
    public function __construct(private ProposalService $proposals) {}

    public static function name(): string
    {
        return 'get_proposals';
    }

    public static function description(): string
    {
        return 'List the proposals of the account and their decision status. Reading a proposal '
            .'never changes it: only the human approval endpoint can act on one.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => array_column(ProposalStatus::cases(), 'value'),
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => array_column(ProposalType::cases(), 'value'),
                ],
                'strategy_id' => ['type' => 'integer'],
                'experiment_id' => ['type' => 'integer'],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->proposals->list(new ProposalFilterDTO(
            $context->accountId,
            isset($input['status']) ? ProposalStatus::from($input['status']) : null,
            isset($input['type']) ? ProposalType::from($input['type']) : null,
            null,
            isset($input['strategy_id']) ? (int) $input['strategy_id'] : null,
            isset($input['experiment_id']) ? (int) $input['experiment_id'] : null,
            0,
            1,
        ))->toArray();
    }
}

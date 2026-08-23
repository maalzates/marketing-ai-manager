<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;

readonly class GetExperimentsTool extends ToolAbstract
{
    public function __construct(private ExperimentService $experiments) {}

    public static function name(): string
    {
        return 'get_experiments';
    }

    public static function description(): string
    {
        return 'List the experiments of the account, optionally narrowed by strategy, status, '
            .'type or verdict. Use it to check what has already been tried before proposing.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'strategy_id' => [
                    'type' => 'integer',
                    'description' => 'Restrict to one strategy. Omit for every strategy of the account.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => array_column(ExperimentStatus::cases(), 'value'),
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => array_column(ExperimentType::cases(), 'value'),
                ],
                'verdict' => [
                    'type' => 'string',
                    'enum' => array_column(Verdict::cases(), 'value'),
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->experiments->forStrategy(new ExperimentFilterDTO(
            $context->accountId,
            isset($input['strategy_id']) ? (int) $input['strategy_id'] : null,
            isset($input['status']) ? ExperimentStatus::from($input['status']) : null,
            isset($input['type']) ? ExperimentType::from($input['type']) : null,
            isset($input['verdict']) ? Verdict::from($input['verdict']) : null,
            0,
            1,
        ))->toArray();
    }
}

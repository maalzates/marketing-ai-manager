<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Strategies\Application\Services\StrategyService;

readonly class GetStrategySummaryTool extends ToolAbstract
{
    public function __construct(private StrategyService $strategies) {}

    public static function name(): string
    {
        return 'get_strategy_summary';
    }

    public static function description(): string
    {
        return 'Read the compact summary of one strategy: objective, north star metric, '
            .'monthly budget, constraints and status.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'strategy_id' => [
                    'type' => 'integer',
                    'description' => 'Identifier of the strategy to summarise.',
                ],
            ],
            'required' => ['strategy_id'],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->strategies->summary((int) $input['strategy_id'], $context->accountId);
    }
}

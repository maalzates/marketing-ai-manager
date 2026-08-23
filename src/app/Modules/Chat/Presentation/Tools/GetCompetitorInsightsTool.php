<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Application\Services\InsightService;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;

readonly class GetCompetitorInsightsTool extends ToolAbstract
{
    public function __construct(private InsightService $insights) {}

    public static function name(): string
    {
        return 'get_competitor_insights';
    }

    public static function description(): string
    {
        return 'List the insights extracted from competitor analysis and comment mining, '
            .'optionally narrowed by kind, status or strategy.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'kind' => [
                    'type' => 'string',
                    'enum' => array_column(InsightKind::cases(), 'value'),
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => array_column(InsightStatus::cases(), 'value'),
                ],
                'strategy_id' => [
                    'type' => 'integer',
                    'description' => 'Restrict to one strategy. Omit for every strategy of the account.',
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->insights->forAccount(new InsightFilterDTO(
            $context->accountId,
            isset($input['kind']) ? InsightKind::from($input['kind']) : null,
            isset($input['status']) ? InsightStatus::from($input['status']) : null,
            isset($input['strategy_id']) ? (int) $input['strategy_id'] : null,
        ))->toArray();
    }
}

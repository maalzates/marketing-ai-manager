<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Tools;

use App\Modules\Chat\Application\DTO\HistorySearchDTO;
use App\Modules\Chat\Domain\Contracts\HistorySearchInterface;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolAbstract;

readonly class SearchHistoryTool extends ToolAbstract
{
    private const int DEFAULT_LIMIT = 10;

    public function __construct(private HistorySearchInterface $history) {}

    public static function name(): string
    {
        return 'search_history';
    }

    public static function description(): string
    {
        return 'Search the account\'s past experiments and their verdicts by topic, to check '
            .'whether a hypothesis has already been tested and what the outcome was.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'What to look for, in the words the experiments would use.',
                ],
                'strategy_id' => [
                    'type' => 'integer',
                    'description' => 'Restrict to one strategy. Omit to search the whole account.',
                ],
                'limit' => ['type' => 'integer'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return $this->history->search(new HistorySearchDTO(
            $context->accountId,
            $input['query'],
            isset($input['strategy_id']) ? (int) $input['strategy_id'] : null,
            (int) ($input['limit'] ?? self::DEFAULT_LIMIT),
        ))->all();
    }
}

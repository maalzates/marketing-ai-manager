<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\DTO\SuggestFieldDTO;

/**
 * The one "Ask AI" behind every field in the product. It is a single prompt with a single
 * schema on purpose: a suggestion endpoint per form would drift into a prompt per form.
 */
readonly class FieldSuggestionService
{
    private const array SUGGESTION_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'value' => ['type' => 'string'],
            'rationale' => ['type' => 'string'],
            'alternatives' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['value', 'rationale'],
        'additionalProperties' => false,
    ];

    private const string PROMPT = 'Suggest a value for the field "%s" of this marketing workspace. '
        .'Ground the suggestion in the context provided above and justify it against that history. '
        .'Return the value the user can paste into the field, a short rationale, and up to three alternatives. '
        .'When the context declares an "options" list, the value must be exactly one of those options. '
        .'Write the value and the rationale in Spanish: the product and its users are Spanish-speaking.';

    public function __construct(private AiService $ai) {}

    /** @return array<string, mixed> */
    public function suggest(SuggestFieldDTO $dto): array
    {
        return $this->ai->structured(
            new AiRequestDTO(
                $dto->accountId,
                $dto->task,
                sprintf(self::PROMPT, $dto->target),
                $dto->context,
                userId: $dto->userId,
                strategyId: $dto->strategyId,
            ),
            self::SUGGESTION_SCHEMA,
        );
    }
}

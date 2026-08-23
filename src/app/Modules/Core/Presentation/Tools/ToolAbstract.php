<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Tools;

use App\Modules\Core\Application\Context\AccountContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Base chat-assistant tool: a driving adapter that turns LLM-provided input into a call on
 * a Service. It knows nothing about HTTP — the same tool is reachable from the chat loop,
 * a job or a future MCP adapter.
 */
abstract readonly class ToolAbstract
{
    /** @var array<string, string> */
    private const array SCHEMA_TYPE_RULES = [
        'string' => 'string',
        'integer' => 'integer',
        'number' => 'numeric',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'array',
    ];

    abstract public static function name(): string;

    abstract public static function description(): string;

    /** JSON Schema object describing the tool input, as sent to the model. */
    abstract public static function schema(): array;

    abstract public function handle(array $input, AccountContext $context): array;

    public function validate(array $input): array
    {
        return Validator::make($input, self::rulesFromSchema(static::schema()))->validate();
    }

    private static function rulesFromSchema(array $schema): array
    {
        return collect($schema['properties'] ?? [])
            ->mapWithKeys(fn (array $property, string $field): array => [
                $field => self::rulesForProperty($property, in_array($field, $schema['required'] ?? [], true)),
            ])
            ->all();
    }

    private static function rulesForProperty(array $property, bool $isRequired): array
    {
        return array_values(array_filter([
            $isRequired ? 'required' : 'nullable',
            self::SCHEMA_TYPE_RULES[$property['type'] ?? 'string'] ?? 'string',
            isset($property['enum']) ? Rule::in($property['enum']) : null,
        ]));
    }
}

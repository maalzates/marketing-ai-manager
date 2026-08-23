<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Domain\Exceptions\StructuredOutputMismatchException;

/**
 * A model that answers with the wrong shape is a failed call, not a partial result: a
 * half-filled suggestion silently written to a brand profile is worse than an error.
 */
readonly class StructuredOutputValidator
{
    /** @var array<string, string> */
    private const array TYPE_CHECKS = [
        'string' => 'is_string',
        'integer' => 'is_int',
        'number' => 'is_numeric',
        'boolean' => 'is_bool',
        'array' => 'is_array',
        'object' => 'is_array',
    ];

    /** @return array<string, mixed> */
    public function validate(LlmResponseDTO $response, array $schema): array
    {
        $violations = self::violations($response->structured, $schema);

        return $violations === []
            ? $response->structured
            : throw StructuredOutputMismatchException::withViolations($response->model, $violations, $response->structured);
    }

    /** @return list<string> */
    private static function violations(?array $structured, array $schema): array
    {
        return $structured === null
            ? ['The response body was not decodable JSON.']
            : [...self::missingFields($structured, $schema), ...self::mistypedFields($structured, $schema)];
    }

    /** @return list<string> */
    private static function missingFields(array $structured, array $schema): array
    {
        return collect($schema['required'] ?? [])
            ->reject(static fn (string $field): bool => array_key_exists($field, $structured))
            ->map(static fn (string $field): string => "Missing required field: {$field}.")
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function mistypedFields(array $structured, array $schema): array
    {
        return collect($schema['properties'] ?? [])
            ->filter(static fn (array $property, string $field): bool => array_key_exists($field, $structured)
                && ! self::matchesType($structured[$field], $property['type'] ?? null))
            ->map(static fn (array $property, string $field): string => sprintf(
                'Field %s should be %s.',
                $field,
                $property['type'],
            ))
            ->values()
            ->all();
    }

    private static function matchesType(mixed $value, ?string $type): bool
    {
        return $type === null || ! array_key_exists($type, self::TYPE_CHECKS)
            ? true
            : self::TYPE_CHECKS[$type]($value);
    }
}

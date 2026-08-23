<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Requests;

use BackedEnum;

/**
 * Typed accessors over validated input, so a FormRequest's toDTO() stays free of casts.
 */
trait RequestHelperTrait
{
    private function getIntegerValue(string $key, ?int $default = null): ?int
    {
        return $this->validated($key) !== null ? (int) $this->validated($key) : $default;
    }

    private function getFloatValue(string $key, ?float $default = null): ?float
    {
        return $this->validated($key) !== null ? (float) $this->validated($key) : $default;
    }

    private function getStringValue(string $key, ?string $default = null): ?string
    {
        return $this->validated($key) !== null ? (string) $this->validated($key) : $default;
    }

    private function getBooleanValue(string $key, ?bool $default = null): ?bool
    {
        return $this->validated($key) !== null ? (bool) $this->validated($key) : $default;
    }

    private function getArrayValue(string $key, array $default = []): array
    {
        return empty($this->validated($key)) ? $default : (array) $this->validated($key);
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @param  T|null  $default
     * @return T|null
     */
    private function getEnumValue(string $key, string $enumClass, mixed $default = null): mixed
    {
        return empty($this->validated($key)) ? $default : $enumClass::from($this->validated($key));
    }

    /**
     * Laravel's `string` rule rejects JSON numbers, and identifiers reach the API as
     * either strings or integers depending on the caller.
     *
     * @param  list<string>  $keys
     */
    private function mergeStringified(array $keys): void
    {
        $this->merge(
            collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => $this->input($key)])
                ->filter(fn (mixed $value): bool => is_scalar($value))
                ->map(fn (mixed $value): string => (string) $value)
                ->all()
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Support;

use Illuminate\Support\Str;

/**
 * Strips credentials out of any payload before it is persisted to the audit tables or
 * attached to an exception context.
 */
readonly class SecretMasker
{
    /** @var list<string> */
    private const array SENSITIVE_KEY_FRAGMENTS = ['key', 'token', 'secret', 'password', 'credential'];

    private const int MINIMUM_LENGTH_TO_REVEAL_SUFFIX = 8;

    public function mask(array $payload): array
    {
        return collect($payload)
            ->map(fn (mixed $value, int|string $key): mixed => match (true) {
                self::isSensitive($key) => self::maskValue($value),
                is_array($value) => $this->mask($value),
                default => $value,
            })
            ->all();
    }

    private static function isSensitive(int|string $key): bool
    {
        return Str::contains(Str::lower((string) $key), self::SENSITIVE_KEY_FRAGMENTS);
    }

    private static function maskValue(mixed $value): string
    {
        return is_string($value) && strlen($value) >= self::MINIMUM_LENGTH_TO_REVEAL_SUFFIX
            ? '****'.substr($value, -4)
            : '****';
    }
}

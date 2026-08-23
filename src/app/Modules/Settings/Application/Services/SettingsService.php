<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\Services;

use App\Modules\Settings\Application\DTO\SettingScopeFilterDTO;
use App\Modules\Settings\Application\DTO\WriteSettingsDTO;
use App\Modules\Settings\Domain\Contracts\SettingRepositoryInterface;
use App\Modules\Settings\Domain\Contracts\SettingScopeOwnershipInterface;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Domain\Exceptions\InvalidSettingValueException;
use App\Modules\Settings\Domain\Exceptions\SettingScopeNotFoundException;
use App\Modules\Settings\Domain\Exceptions\UnknownSettingKeyException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

readonly class SettingsService
{
    private const string CACHE_PREFIX = 'settings:map:';

    private const string DEFAULT_SCOPE = 'default';

    public function __construct(
        private SettingRepositoryInterface $repository,
        private SettingScopeOwnershipInterface $ownership,
        private Cache $cache,
    ) {}

    public function get(string $key, ?int $accountId = null, ?int $strategyId = null): mixed
    {
        $this->assertDeclared($key);

        return $this->resolve($key, $this->storedMaps($accountId, $strategyId))['value'];
    }

    /**
     * @return Collection<string, array{value: mixed, scope: string}>
     */
    public function all(?int $accountId = null, ?int $strategyId = null): Collection
    {
        $maps = $this->storedMaps($accountId, $strategyId);

        return collect(self::declaredDefaults())
            ->map(fn (mixed $default, string $key): array => $this->resolve($key, $maps));
    }

    /**
     * @return Collection<string, array{value: mixed, scope: string}>
     */
    public function effective(SettingScopeFilterDTO $filters): Collection
    {
        $this->assertScopeBelongsToAccount($filters->strategyId, $filters->accountId);

        return $this->all($filters->accountId, $filters->strategyId);
    }

    public function set(SettingScope $scope, ?int $scopeId, string $key, mixed $value): void
    {
        $this->assertDeclared($key);

        $this->repository->upsert($scope, $scopeId, $key, $this->normalised($key, $value));

        $this->cache->forget(self::cacheKey($scope, $scopeId));
    }

    public function forget(SettingScope $scope, ?int $scopeId, string $key): void
    {
        $this->repository->forget($scope, $scopeId, $key);

        $this->cache->forget(self::cacheKey($scope, $scopeId));
    }

    /**
     * @return Collection<string, array{value: mixed, scope: string}>
     */
    public function update(WriteSettingsDTO $dto): Collection
    {
        $this->assertScopeBelongsToAccount($dto->strategyId, $dto->accountId);

        foreach ($dto->values as $key => $value) {
            $this->set(
                $dto->strategyId === null ? SettingScope::ACCOUNT : SettingScope::STRATEGY,
                $dto->strategyId ?? $dto->accountId,
                (string) $key,
                $value,
            );
        }

        return $this->all($dto->accountId, $dto->strategyId);
    }

    /**
     * The strategy id reaches update() and effective() straight from a route parameter, so
     * it is a claim the caller makes rather than a fact. Every other method here is handed
     * ids the calling module already established.
     */
    private function assertScopeBelongsToAccount(?int $strategyId, int $accountId): void
    {
        if ($strategyId !== null && ! $this->ownership->ownsScope(SettingScope::STRATEGY, $strategyId, $accountId)) {
            throw SettingScopeNotFoundException::forScope(SettingScope::STRATEGY, $strategyId);
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $maps
     * @return array{value: mixed, scope: string}
     */
    private function resolve(string $key, Collection $maps): array
    {
        foreach ($maps as $scope => $map) {
            if (array_key_exists($key, $map)) {
                return ['value' => $this->typed($key, $map[$key]), 'scope' => $scope];
            }
        }

        return ['value' => config('settings.'.$key), 'scope' => self::DEFAULT_SCOPE];
    }

    /**
     * Most specific scope first: the first map holding the key wins.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function storedMaps(?int $accountId, ?int $strategyId): Collection
    {
        return collect([
            SettingScope::STRATEGY->value => $strategyId === null
                ? []
                : $this->cachedMap(SettingScope::STRATEGY, $strategyId),
            SettingScope::ACCOUNT->value => $accountId === null
                ? []
                : $this->cachedMap(SettingScope::ACCOUNT, $accountId),
            SettingScope::GLOBAL->value => $this->cachedMap(SettingScope::GLOBAL, null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedMap(SettingScope $scope, ?int $scopeId): array
    {
        return $this->cache->rememberForever(
            self::cacheKey($scope, $scopeId),
            fn (): array => $this->repository->mapForScope($scope, $scopeId)->all(),
        );
    }

    private function normalised(string $key, mixed $value): mixed
    {
        $declared = config('settings.'.$key);
        $coerced = $this->typed($key, $value);

        return get_debug_type($declared) === get_debug_type($coerced)
            ? $coerced
            : throw InvalidSettingValueException::forKey($key, get_debug_type($declared), get_debug_type($value));
    }

    /**
     * JSON draws no line between a whole float and an int, so a key declared as float travels
     * through the payload and through storage as int. The registry still owes its declared type.
     */
    private function typed(string $key, mixed $value): mixed
    {
        return is_float(config('settings.'.$key)) && is_int($value) ? (float) $value : $value;
    }

    private function assertDeclared(string $key): void
    {
        if (! array_key_exists($key, self::declaredDefaults())) {
            throw UnknownSettingKeyException::forKey($key);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function declaredDefaults(): array
    {
        return Arr::dot(config('settings', []));
    }

    private static function cacheKey(SettingScope $scope, ?int $scopeId): string
    {
        return self::CACHE_PREFIX.$scope->value.':'.($scopeId ?? 0);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settings\Infrastructure\Repositories;

use App\Modules\Settings\Domain\Contracts\SettingRepositoryInterface;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Domain\Exceptions\SettingWriteFailedException;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(private Setting $model) {}

    public function mapForScope(SettingScope $scope, ?int $scopeId): Collection
    {
        return $this->scoped($scope, $scopeId)->pluck('value', 'key');
    }

    public function upsert(SettingScope $scope, ?int $scopeId, string $key, mixed $value): Setting
    {
        try {
            return $this->model->newQuery()->updateOrCreate(
                ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
                ['value' => $value],
            );
        } catch (Throwable $exception) {
            throw SettingWriteFailedException::wrap($exception, context: [
                'scope' => $scope->value,
                'scope_id' => $scopeId,
                'key' => $key,
            ]);
        }
    }

    public function forget(SettingScope $scope, ?int $scopeId, string $key): bool
    {
        return $this->scoped($scope, $scopeId)->where('key', $key)->delete() > 0;
    }

    private function scoped(SettingScope $scope, ?int $scopeId): Builder
    {
        return $this->model->newQuery()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId);
    }
}

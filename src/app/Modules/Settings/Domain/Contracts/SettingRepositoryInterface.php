<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Contracts;

use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    /**
     * @return Collection<string, mixed> stored key => value for one scope, defaults excluded
     */
    public function mapForScope(SettingScope $scope, ?int $scopeId): Collection;

    public function upsert(SettingScope $scope, ?int $scopeId, string $key, mixed $value): Setting;

    public function forget(SettingScope $scope, ?int $scopeId, string $key): bool;
}

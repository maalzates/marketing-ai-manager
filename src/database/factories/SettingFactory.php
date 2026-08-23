<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'scope' => SettingScope::GLOBAL,
            'scope_id' => null,
            'key' => 'features.chat',
            'value' => true,
        ];
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => SettingScope::ACCOUNT,
            'scope_id' => $accountId,
        ]);
    }

    public function forStrategy(int $strategyId): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => SettingScope::STRATEGY,
            'scope_id' => $strategyId,
        ]);
    }
}

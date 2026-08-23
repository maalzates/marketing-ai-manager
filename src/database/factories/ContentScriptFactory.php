<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentScript>
 */
class ContentScriptFactory extends Factory
{
    protected $model = ContentScript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // The strategy has to belong to the same account, or every script built by this
            // factory would already violate the tenancy rule it is used to test.
            'strategy_id' => fn (array $attributes): int => Strategy::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'experiment_id' => null,
            'title' => fake()->sentence(4),
            'hook' => fake()->sentence(),
            'structure' => [
                ['beat' => 'hook', 'detail' => fake()->sentence()],
                ['beat' => 'body', 'detail' => fake()->sentence()],
                ['beat' => 'cta', 'detail' => fake()->sentence()],
            ],
            'cta' => fake()->sentence(3),
            'format' => ContentFormat::Reel,
            'required_assets' => [
                ['type' => 'reel', 'aspect_ratio' => '9:16', 'duration_seconds' => 30, 'quantity' => 1],
            ],
            'source_insight_ids' => [],
            'status' => ScriptStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScriptStatus::Approved,
            'experiment_id' => fn (array $attributes): int => Experiment::factory()
                ->create([
                    'account_id' => $attributes['account_id'],
                    'strategy_id' => $attributes['strategy_id'],
                ])
                ->id,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ScriptStatus::Rejected]);
    }
}

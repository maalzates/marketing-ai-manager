<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insight>
 */
class InsightFactory extends Factory
{
    protected $model = Insight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'strategy_id' => null,
            'competitor_id' => null,
            'kind' => InsightKind::Pattern,
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'evidence' => [],
            'score' => fake()->randomFloat(3, 0, 1),
            'source' => InsightSource::CompetitorAnalysis,
            'status' => InsightStatus::New,
        ];
    }

    public function contentIdea(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => InsightKind::ContentIdea,
            'source' => InsightSource::CommentMining,
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => InsightStatus::Discarded]);
    }
}

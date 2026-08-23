<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitorComment>
 */
class CompetitorCommentFactory extends Factory
{
    protected $model = CompetitorComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competitor_post_id' => CompetitorPost::factory(),
            'account_id' => fn (array $attributes): int => CompetitorPost::query()
                ->findOrFail($attributes['competitor_post_id'])
                ->account_id,
            'external_id' => (string) fake()->unique()->randomNumber(9),
            'author' => fake()->userName(),
            'text' => fake()->sentence(10),
            'likes' => fake()->numberBetween(0, 200),
            'posted_at' => fake()->dateTimeBetween('-60 days'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Competitors\Domain\Enums\Sentiment;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitorPost>
 */
class CompetitorPostFactory extends Factory
{
    protected $model = CompetitorPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The post has to belong to the same account as its competitor, or every row
            // this factory builds already breaks the tenancy rule it is used to test.
            'competitor_id' => Competitor::factory(),
            'account_id' => fn (array $attributes): int => Competitor::query()
                ->findOrFail($attributes['competitor_id'])
                ->account_id,
            'external_id' => (string) fake()->unique()->randomNumber(9),
            'url' => 'https://www.instagram.com/p/'.fake()->unique()->lexify('???????????').'/',
            'type' => fake()->randomElement(['image', 'video', 'reel', 'sidecar']),
            'caption' => fake()->sentence(12),
            'posted_at' => fake()->dateTimeBetween('-90 days'),
            'likes' => fake()->numberBetween(0, 50000),
            'comments_count' => fake()->numberBetween(0, 500),
            'views' => fake()->numberBetween(0, 200000),
            'engagement_rate' => null,
            'sentiment' => null,
            'sentiment_summary' => null,
            'raw' => [],
        ];
    }

    /** Instagram reports -1 when the profile hides likes; the column stores that as null. */
    public function withHiddenLikes(): static
    {
        return $this->state(fn (array $attributes): array => ['likes' => null]);
    }

    public function analysed(Sentiment $sentiment = Sentiment::Positive): static
    {
        return $this->state(fn (array $attributes): array => [
            'sentiment' => $sentiment,
            'sentiment_summary' => ['dominant_topics' => ['pricing'], 'positive' => 8, 'negative' => 1, 'neutral' => 1],
        ]);
    }
}

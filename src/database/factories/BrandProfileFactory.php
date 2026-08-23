<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Domain\Enums\BrandKind;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandProfile>
 */
class BrandProfileFactory extends Factory
{
    protected $model = BrandProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->company(),
            'kind' => BrandKind::PersonalBrand,
            'description' => fake()->paragraph(),
            'niche' => fake()->words(2, true),
            'value_proposition' => fake()->sentence(),
            'tone_of_voice' => fake()->sentence(),
            'values' => fake()->words(3),
            'banned_topics' => fake()->words(2),
            'buyer_personas' => [
                ['name' => fake()->name(), 'pain' => fake()->sentence()],
            ],
            'reference_competitors' => fake()->words(2),
            'brand_colors' => ['#111111', '#f5f5f5'],
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => BrandKind::Company]);
    }

    public function project(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => BrandKind::Project]);
    }
}

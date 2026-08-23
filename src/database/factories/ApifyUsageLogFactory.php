<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\ApifyUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApifyUsageLog>
 */
class ApifyUsageLogFactory extends Factory
{
    protected $model = ApifyUsageLog::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'actor_id' => fake()->randomElement(['apify~instagram-scraper', 'apify~instagram-comment-scraper']),
            'run_id' => fake()->uuid(),
            'results_count' => fake()->numberBetween(0, 200),
            'estimated_cost_usd' => fake()->randomFloat(6, 0.001, 2.5),
        ];
    }
}

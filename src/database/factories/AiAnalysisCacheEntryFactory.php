<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Infrastructure\Persistence\AiAnalysisCacheEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAnalysisCacheEntry>
 */
class AiAnalysisCacheEntryFactory extends Factory
{
    protected $model = AiAnalysisCacheEntry::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'kind' => 'competitor_posts',
            'input_hash' => hash('sha256', $this->faker->unique()->uuid()),
            'result' => ['summary' => $this->faker->sentence()],
        ];
    }
}

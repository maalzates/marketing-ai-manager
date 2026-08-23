<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionLog>
 */
class ActionLogFactory extends Factory
{
    protected $model = ActionLog::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['strategy.created', 'proposal.accepted', 'settings.updated']),
            'entity_type' => null,
            'entity_id' => null,
            'payload' => [],
            'origin' => fake()->randomElement(ActionOrigin::cases()),
            'ip' => fake()->ipv4(),
        ];
    }
}

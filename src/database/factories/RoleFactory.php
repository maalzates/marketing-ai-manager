<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(1),
            'label' => fake()->words(2, true),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['name' => 'admin', 'label' => 'Administrador']);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    private const array ROLES = [
        ['name' => 'admin', 'label' => 'Administrador'],
        ['name' => 'user', 'label' => 'Usuario'],
    ];

    public function run(): void
    {
        collect(self::ROLES)->each(
            fn (array $role) => Role::query()->firstOrCreate(['name' => $role['name']], ['label' => $role['label']]),
        );
    }
}

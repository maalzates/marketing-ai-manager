<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_the_roles_creates_the_two_platform_roles(): void
    {
        $this->artisan('db:seed', ['--class' => RoleSeeder::class])->assertExitCode(0);

        $this->assertSame(['admin', 'user'], Role::query()->orderBy('name')->pluck('name')->all());
    }

    public function test_seeding_the_roles_twice_leaves_exactly_two_roles(): void
    {
        $this->artisan('db:seed', ['--class' => RoleSeeder::class])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => RoleSeeder::class])->assertExitCode(0);

        $this->assertSame(2, Role::query()->count());
    }

    public function test_reseeding_does_not_overwrite_a_relabelled_role(): void
    {
        $this->artisan('db:seed', ['--class' => RoleSeeder::class])->assertExitCode(0);
        Role::query()->where('name', 'admin')->update(['label' => 'Owner']);

        $this->artisan('db:seed', ['--class' => RoleSeeder::class])->assertExitCode(0);

        $this->assertSame('Owner', Role::query()->where('name', 'admin')->value('label'));
    }
}

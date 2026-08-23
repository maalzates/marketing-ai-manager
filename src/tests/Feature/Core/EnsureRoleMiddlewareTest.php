<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Asserted through /api/v1/admin/knowledge, the only route group behind `role:admin`.
 */
class EnsureRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ROUTE = '/api/v1/admin/knowledge';

    public function test_rejects_a_user_who_does_not_hold_the_required_role(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::ADMIN_ROUTE)
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('errors.message', 'You are not allowed to perform this action.');
    }

    public function test_rejects_a_user_holding_a_different_role(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        Sanctum::actingAs($user);

        $this->getJson(self::ADMIN_ROUTE)->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_lets_an_admin_through(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->admin()->create());

        Sanctum::actingAs($user);

        $this->getJson(self::ADMIN_ROUTE)->assertOk();
    }
}

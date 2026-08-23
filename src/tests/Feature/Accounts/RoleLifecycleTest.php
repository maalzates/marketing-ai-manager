<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Modules\Accounts\Application\DTO\CreateRoleDTO;
use App\Modules\Accounts\Application\Services\RoleService;
use App\Modules\Accounts\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Role administration has no HTTP door yet. The delete route registered here is a stand-in
 * for the one the admin module will expose, so the refusal is asserted through the real
 * error envelope rather than on a caught exception.
 */
class RoleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::delete(
            '/api/testing/roles/{id}',
            fn (string $id, RoleService $service): array => ['deleted' => $service->delete((int) $id)],
        );
    }

    public function test_a_role_still_assigned_to_a_user_cannot_be_deleted(): void
    {
        $role = Role::factory()->create();
        $role->users()->attach(User::factory()->create());

        $this->deleteJson("/api/testing/roles/{$role->id}")
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('errors.message', 'This role is still assigned to at least one user.');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_an_unassigned_role_is_deleted(): void
    {
        $role = Role::factory()->create();

        $this->deleteJson("/api/testing/roles/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_that_does_not_exist_is_a_not_found(): void
    {
        $this->deleteJson('/api/testing/roles/9999')->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_assigning_a_role_twice_leaves_one_membership(): void
    {
        $user = User::factory()->create();
        Role::factory()->admin()->create();

        app(RoleService::class)->assignToUser('admin', (int) $user->id);
        app(RoleService::class)->assignToUser('admin', (int) $user->id);

        $this->assertSame(1, $user->roles()->count());
    }

    public function test_creating_a_role_stores_its_name_and_label(): void
    {
        app(RoleService::class)->create(new CreateRoleDTO('analyst', 'Analista'));

        $this->assertDatabaseHas('roles', ['name' => 'analyst', 'label' => 'Analista']);
    }

    public function test_assigning_a_role_that_does_not_exist_is_refused(): void
    {
        $this->expectException(RoleNotFoundException::class);

        app(RoleService::class)->assignToUser('ghost', (int) User::factory()->create()->id);
    }
}

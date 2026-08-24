<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every admin route, enumerated from the router rather than listed by hand: a route added
 * to the group later is covered the day it ships, and one added outside the group fails
 * this test instead of shipping open.
 */
class AdminRouteAuthorisationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->accounts()->attach($account);
        $this->user->roles()->attach(Role::factory()->create(['name' => 'user']));

        Sanctum::actingAs($this->user);
    }

    public function test_every_admin_route_refuses_a_user_without_the_admin_role(): void
    {
        foreach ($this->adminRoutes() as [$method, $uri]) {
            $this->json($method, $this->withPlaceholders($uri))
                ->assertStatus(403, "{$method} {$uri} did not refuse a non-admin");
        }
    }

    public function test_every_admin_route_is_behind_the_admin_role_middleware(): void
    {
        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/admin'))
            ->reject(fn ($route): bool => in_array('role:admin', $route->gatherMiddleware(), true))
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unguarded);
    }

    public function test_an_unauthenticated_caller_gets_401_rather_than_403(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/users', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
    }

    /** @return list<array{0: string, 1: string}> */
    private function adminRoutes(): array
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/admin'))
            ->map(fn ($route): array => [$route->methods()[0], $route->uri()])
            ->values()
            ->all();

        $this->assertNotSame([], $routes, 'No admin routes were found to check.');

        return $routes;
    }

    private function withPlaceholders(string $uri): string
    {
        return '/'.(string) preg_replace('#\{[^}]+\}#', '1', $uri);
    }
}

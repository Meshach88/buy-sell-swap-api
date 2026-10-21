<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolesAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->get('/api/_test/admin-only', fn () => ['ok' => true]);
        Route::middleware(['auth:sanctum', 'role:seller'])->get('/api/_test/seller-only', fn () => ['ok' => true]);
    }

    public function test_all_roles_are_seeded(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role->value]);
        }
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_the_user_and_roles(): void
    {
        $user = User::factory()->seller()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles.0', 'seller')
            ->assertJsonPath('data.phone_verified', true);
    }

    public function test_role_middleware_blocks_the_wrong_role(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(Role::Customer->value));

        $this->getJson('/api/_test/admin-only')->assertForbidden();
        $this->getJson('/api/_test/seller-only')->assertForbidden();
    }

    public function test_role_middleware_lets_the_right_role_through(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/_test/admin-only')->assertOk();

        Sanctum::actingAs(User::factory()->seller()->create());
        $this->getJson('/api/_test/seller-only')->assertOk();
    }
}

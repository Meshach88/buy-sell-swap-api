<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_verified_user_can_log_in(): void
    {
        $user = User::factory()->create(['phone' => '08011112222', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['phone' => '08011112222', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['phone' => '08011112222', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['phone' => '08011112222', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_unknown_phone_is_rejected(): void
    {
        $this->postJson('/api/auth/login', ['phone' => '08000000000', 'password' => 'password123'])
            ->assertUnprocessable();
    }

    public function test_an_unverified_phone_is_sent_a_fresh_code_instead_of_a_token(): void
    {
        User::factory()->unverifiedPhone()->create(['phone' => '08011112222', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['phone' => '08011112222', 'password' => 'password123'])
            ->assertForbidden()
            ->assertJsonPath('code', 'phone_not_verified')
            ->assertJsonStructure(['debug_code']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['phone' => '08011112222', 'password' => 'password123']);
        $token = $user->createToken('bss')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        // The auth guard caches the resolved user for the life of the test process;
        // a real second request would be a fresh process, so force it to re-check here.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}

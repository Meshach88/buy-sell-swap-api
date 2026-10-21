<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAndVerifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_buyer_can_register_and_receives_a_debug_code(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Amina Bello',
            'phone' => '08011112222',
            'password' => 'password123',
            'role' => 'customer',
            'area' => 'Barnawa',
        ]);

        $response->assertCreated()->assertJsonStructure(['message', 'phone', 'debug_code']);

        $user = User::where('phone', '08011112222')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->hasRole('seller'));
        $this->assertNull($user->phone_verified_at);
    }

    public function test_a_seller_registration_creates_a_seller_profile_and_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Musa Ibrahim',
            'phone' => '08033334444',
            'password' => 'password123',
            'role' => 'seller',
            'shop_name' => 'Musa Gadgets',
        ]);

        $response->assertCreated();

        $user = User::where('phone', '08033334444')->first();
        $this->assertTrue($user->hasRole('seller'));
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame('Musa Gadgets', $user->sellerProfile->shop_name);
        $this->assertNotEmpty($user->sellerProfile->slug);
    }

    public function test_seller_role_requires_a_shop_name(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'No Shop',
            'phone' => '08055556666',
            'password' => 'password123',
            'role' => 'seller',
        ])->assertUnprocessable()->assertJsonValidationErrors('shop_name');
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => '08099998888']);

        $this->postJson('/api/auth/register', [
            'name' => 'Dupe',
            'phone' => '08099998888',
            'password' => 'password123',
            'role' => 'customer',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_verifying_the_correct_code_activates_the_account_and_returns_a_token(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Amina Bello',
            'phone' => '08011112222',
            'password' => 'password123',
            'role' => 'customer',
        ]);
        $code = $register->json('debug_code');

        $response = $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => $code]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);

        $user = User::where('phone', '08011112222')->first();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame('active', $user->status->value);
    }

    public function test_an_incorrect_code_is_rejected_and_counted_as_an_attempt(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Amina Bello',
            'phone' => '08011112222',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(1, OtpCode::where('phone', '08011112222')->first()->attempts);
    }

    public function test_too_many_wrong_attempts_locks_the_code(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Amina Bello',
            'phone' => '08011112222',
            'password' => 'password123',
            'role' => 'customer',
        ]);
        $code = $register->json('debug_code');
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => $wrong]);
        }

        $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonFragment(['code' => ['Too many attempts. Request a new code.']]);
    }

    public function test_resend_issues_a_fresh_code_and_invalidates_the_old_one(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Amina Bello',
            'phone' => '08011112222',
            'password' => 'password123',
            'role' => 'customer',
        ]);
        $oldCode = $register->json('debug_code');

        $resend = $this->postJson('/api/auth/otp/resend', ['phone' => '08011112222']);
        $newCode = $resend->json('debug_code');

        $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => $oldCode])
            ->assertUnprocessable();

        $this->postJson('/api/auth/otp/verify', ['phone' => '08011112222', 'code' => $newCode])
            ->assertOk();
    }
}

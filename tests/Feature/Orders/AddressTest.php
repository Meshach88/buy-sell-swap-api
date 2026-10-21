<?php

namespace Tests\Feature\Orders;

use App\Models\City;
use App\Models\DeliveryZone;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_creating_an_address_resolves_its_delivery_zone_from_the_area(): void
    {
        $city = City::factory()->create();
        $zone = DeliveryZone::factory()->create(['city_id' => $city->id, 'areas' => ['Barnawa', 'Kakuri']]);
        Sanctum::actingAs(User::factory()->create(['city_id' => $city->id]));

        $response = $this->postJson('/api/addresses', [
            'recipient_name' => 'Amina Bello', 'phone' => '08011112222',
            'street' => '4 Test Close', 'area' => 'Barnawa',
        ]);

        $response->assertCreated()->assertJsonPath('data.delivery_zone.id', $zone->id);
    }

    public function test_an_area_with_no_matching_zone_still_saves_the_address_without_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/addresses', [
            'recipient_name' => 'Amina Bello', 'phone' => '08011112222',
            'street' => '4 Test Close', 'area' => 'Nowhere',
        ]);

        $response->assertCreated()->assertJsonPath('data.delivery_zone', null);
    }

    public function test_the_first_address_becomes_the_default_automatically(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/addresses', [
            'recipient_name' => 'Amina Bello', 'phone' => '08011112222', 'street' => '4 Test Close', 'area' => 'Barnawa',
        ])->assertCreated()->assertJsonPath('data.is_default', true);
    }

    public function test_marking_a_new_address_default_unsets_the_previous_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $first = $this->postJson('/api/addresses', [
            'recipient_name' => 'A', 'phone' => '08011112222', 'street' => '1 Close', 'area' => 'Barnawa', 'is_default' => true,
        ])->json('data.id');

        $this->postJson('/api/addresses', [
            'recipient_name' => 'B', 'phone' => '08033334444', 'street' => '2 Close', 'area' => 'Kawo', 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', ['id' => $first, 'is_default' => false]);
    }

    public function test_a_user_only_sees_their_own_addresses(): void
    {
        $owner = User::factory()->create();
        $owner->addresses()->create(['recipient_name' => 'A', 'phone' => '08011112222', 'street' => '1', 'area' => 'Barnawa']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(0, 'data');
    }
}

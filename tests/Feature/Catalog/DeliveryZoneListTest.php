<?php

namespace Tests\Feature\Catalog;

use App\Models\City;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZoneListTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_zones_for_the_default_city_cheapest_first(): void
    {
        config(['bss.default_city' => 'kaduna']);
        $kaduna = City::factory()->create(['slug' => 'kaduna']);
        $otherCity = City::factory()->create();

        DeliveryZone::factory()->create(['city_id' => $kaduna->id, 'name' => 'Far zone', 'fee_kobo' => 250_000, 'is_active' => true]);
        DeliveryZone::factory()->create(['city_id' => $kaduna->id, 'name' => 'Near zone', 'fee_kobo' => 150_000, 'is_active' => true]);
        DeliveryZone::factory()->create(['city_id' => $kaduna->id, 'name' => 'Inactive', 'fee_kobo' => 50_000, 'is_active' => false]);
        DeliveryZone::factory()->create(['city_id' => $otherCity->id, 'name' => 'Elsewhere', 'fee_kobo' => 50_000, 'is_active' => true]);

        $response = $this->getJson('/api/delivery-zones')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame(['Near zone', 'Far zone'], $names->all());
    }
}

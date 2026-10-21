<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'name' => fake()->city(),
            'areas' => [fake()->streetName()],
            'fee_kobo' => fake()->numberBetween(100_000, 300_000),
            'eta_label' => 'Same day',
            'is_active' => true,
        ];
    }
}

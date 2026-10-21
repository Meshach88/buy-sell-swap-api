<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SellerProfile>
 */
class SellerProfileFactory extends Factory
{
    public function definition(): array
    {
        $shopName = fake()->company();

        return [
            'user_id' => User::factory()->seller(),
            'city_id' => City::factory(),
            'shop_name' => $shopName,
            'slug' => Str::slug($shopName).'-'.fake()->unique()->numberBetween(1, 999999),
            'area' => fake()->randomElement(['Barnawa', 'Malali', 'Unguwan Rimi', 'Kawo']),
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now(), 'kyc_status' => 'approved']);
    }
}

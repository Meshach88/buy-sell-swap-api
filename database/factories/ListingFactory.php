<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Listing;
use App\Models\ProductModel;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $productModel = ProductModel::factory();

        return [
            'seller_profile_id' => SellerProfile::factory(),
            'product_model_id' => $productModel,
            'city_id' => City::factory(),
            'title' => fake()->words(3, true),
            'condition' => fake()->randomElement(['brand_new', 'uk_used', 'ng_used']),
            'price_kobo' => fake()->numberBetween(5_000_00, 800_000_00),
            'quantity' => fake()->numberBetween(1, 10),
            'status' => 'draft',
            'verification_status' => 'not_required',
        ];
    }

    public function live(): static
    {
        return $this->state(['status' => 'live', 'published_at' => now()]);
    }
}

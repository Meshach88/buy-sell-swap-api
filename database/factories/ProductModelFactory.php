<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    public function definition(): array
    {
        $brand = fake()->randomElement(['Apple', 'Samsung', 'Infinix', 'Tecno', 'HP']);
        $name = trim(fake()->bothify('?# ####'));

        return [
            'category_id' => Category::factory(),
            'brand' => $brand,
            'name' => $name,
            'slug' => Str::slug("{$brand} {$name}").'-'.fake()->unique()->numberBetween(1, 999999),
            'status' => 'active',
        ];
    }
}

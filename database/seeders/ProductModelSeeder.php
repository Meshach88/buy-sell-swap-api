<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            ['phones', 'Apple', 'iPhone 11', ['storage' => ['64GB', '128GB'], 'colour' => ['Black', 'White', 'Green']]],
            ['phones', 'Apple', 'iPhone 12', ['storage' => ['64GB', '128GB'], 'colour' => ['Black', 'Blue', 'Graphite']]],
            ['phones', 'Apple', 'iPhone 13', ['storage' => ['128GB', '256GB'], 'colour' => ['Midnight', 'Starlight']]],
            ['phones', 'Samsung', 'Galaxy A14', ['storage' => ['64GB', '128GB'], 'colour' => ['Black', 'Silver']]],
            ['phones', 'Samsung', 'Galaxy A54', ['storage' => ['128GB', '256GB'], 'colour' => ['Awesome Graphite']]],
            ['phones', 'Tecno', 'Camon 20', ['storage' => ['128GB', '256GB'], 'colour' => ['Predawn Black']]],
            ['phones', 'Infinix', 'Note 30', ['storage' => ['128GB', '256GB'], 'colour' => ['Volcanic Ash']]],
            ['laptops', 'Apple', 'MacBook Air M1', ['storage' => ['256GB', '512GB'], 'ram' => ['8GB']]],
            ['laptops', 'HP', 'ProBook 450 G8', ['storage' => ['256GB', '512GB'], 'ram' => ['8GB', '16GB']]],
            ['consoles', 'Sony', 'PlayStation 5', ['storage' => ['825GB'], 'edition' => ['Disc', 'Digital']]],
            ['audio', 'Apple', 'AirPods Pro (2nd gen)', []],
        ];

        foreach ($models as [$categorySlug, $brand, $name, $variantOptions]) {
            $category = Category::where('slug', $categorySlug)->first();

            if (! $category) {
                continue;
            }

            $fullName = "{$brand} {$name}";

            ProductModel::updateOrCreate(
                ['slug' => Str::slug($fullName)],
                [
                    'category_id' => $category->id,
                    'brand' => $brand,
                    'name' => $name,
                    'variant_options' => $variantOptions ?: null,
                    'status' => 'active',
                ]
            );
        }
    }
}

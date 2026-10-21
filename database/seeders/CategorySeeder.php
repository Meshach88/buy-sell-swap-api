<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Phones', 'phones', 'smartphone'], ['Laptops', 'laptops', 'laptop'], ['Consoles', 'consoles', 'gamepad-2'],
            ['Audio', 'audio', 'headphones'], ['Tablets', 'tablets', 'tablet'], ['Accessories', 'accessories', 'cable'],
        ];

        foreach ($categories as $i => [$name, $slug, $icon]) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'sort_order' => $i]);
        }
    }
}

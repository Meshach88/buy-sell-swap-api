<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Kaduna is the launch market. The others exist so expansion is a switch, not a migration.
        foreach ([['Kaduna', 'kaduna', true], ['Abuja', 'abuja', false], ['Lagos', 'lagos', false]] as [$name, $slug, $active]) {
            City::updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => $active]);
        }
    }
}

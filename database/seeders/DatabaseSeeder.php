<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CitySeeder::class,
            CommissionTierSeeder::class,
            DeliveryZoneSeeder::class,
            CategorySeeder::class,
            ProductModelSeeder::class,
            DemoListingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}

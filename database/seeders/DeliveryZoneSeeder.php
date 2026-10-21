<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $kaduna = City::where('slug', 'kaduna')->firstOrFail();

        // Sample fees (kobo) until the client confirms dispatch pricing.
        $zones = [
            ['Kaduna North', ['Unguwan Rimi', 'Kawo', 'Malali', 'Ungwan Sarki'], 150_000, 'Same day'],
            ['Kaduna South', ['Barnawa', 'Sabon Tasha', 'Kakuri'], 150_000, 'Same day'],
            ['Outer Kaduna', ['Rigasa', 'Narayi', 'Tudun Wada'], 250_000, 'Next day'],
        ];

        foreach ($zones as [$name, $areas, $fee, $eta]) {
            DeliveryZone::updateOrCreate(
                ['city_id' => $kaduna->id, 'name' => $name],
                ['areas' => $areas, 'fee_kobo' => $fee, 'eta_label' => $eta, 'is_active' => true],
            );
        }
    }
}

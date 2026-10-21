<?php

namespace Database\Seeders;

use App\Models\CommissionTier;
use Illuminate\Database\Seeder;

class CommissionTierSeeder extends Seeder
{
    public function run(): void
    {
        // Sample rates until the client confirms the commission schedule.
        foreach ([['Tier 1 · New sellers', 1000, true], ['Tier 2 · Trusted', 800, false], ['Tier 3 · Top rated', 600, false]] as [$name, $bps, $default]) {
            CommissionTier::updateOrCreate(['name' => $name], ['rate_bps' => $bps, 'is_default' => $default]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\City;
use App\Models\Listing;
use App\Models\ProductModel;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample Kaduna listings so the marketplace home isn't empty in local/dev.
 * Skipped in production, and only runs once (it does nothing if listings
 * already exist, so re-seeding never duplicates them). Every model here must
 * already exist in ProductModelSeeder.
 *
 * Listing photos are the design canvas's own stock images (design/bss-canvas/img),
 * matched by device category — they're generic stock photos, not photos of the
 * literal device in each listing, same as the design mockups themselves reuse a
 * handful of photos across many cards. Skipped if that folder isn't present
 * (e.g. on a deployed server where the design canvas isn't shipped).
 */
class DemoListingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || Listing::count() > 0) {
            return;
        }

        $kaduna = City::where('slug', 'kaduna')->first();

        if (! $kaduna) {
            return;
        }

        $sellers = collect([
            ['Musa Ibrahim', '08031110001', 'Musa Gadgets', 'Barnawa', true],
            ['Amina Bello', '08031110002', 'TechHub Kaduna', 'Unguwan Rimi', true],
            ['Chidi Okafor', '08031110003', 'CO Electronics', 'Malali', false],
        ])->map(function ($row) use ($kaduna) {
            [$name, $phone, $shop, $area, $verified] = $row;

            $user = User::updateOrCreate(['phone' => $phone], [
                'name' => $name, 'password' => 'password123', 'phone_verified_at' => now(),
                'status' => 'active', 'city_id' => $kaduna->id, 'area' => $area,
            ]);
            $user->syncRoles([Role::Customer->value, Role::Seller->value]);

            return SellerProfile::updateOrCreate(['user_id' => $user->id], [
                'city_id' => $kaduna->id,
                'shop_name' => $shop,
                'slug' => Str::slug($shop),
                'area' => $area,
                'verified_at' => $verified ? now() : null,
                'rating_avg' => $verified ? 4.7 : 4.2,
                'rating_count' => $verified ? 38 : 9,
            ]);
        });

        // [brand, model name, variant, condition, price kobo, was-price kobo, area, open to swap, battery %, photo]
        $listings = [
            ['Apple', 'iPhone 13', ['storage' => '256GB', 'colour' => 'Sierra Blue'], 'uk_used', 415_000_00, 470_000_00, 'Barnawa', false, 89, 'phone-in-hand-blue.jpg'],
            ['Infinix', 'Note 30', ['storage' => '256GB'], 'brand_new', 289_000_00, null, 'Unguwan Rimi', true, null, 'phone-homescreen.jpg'],
            ['Apple', 'MacBook Air M1', ['storage' => '512GB'], 'uk_used', 1_150_000_00, 1_290_000_00, 'Malali', false, null, 'laptop-white-room.jpg'],
            ['HP', 'ProBook 450 G8', ['ram' => '16GB'], 'ng_used', 465_000_00, null, 'Kawo', true, null, 'laptop-typing-blank.jpg'],
            ['Samsung', 'Galaxy A54', ['colour' => 'Awesome Graphite'], 'uk_used', 520_000_00, null, 'Sabon Tasha', false, 92, 'phones-trio-coral.jpg'],
            ['Apple', 'iPhone 11', ['storage' => '128GB', 'colour' => 'Black'], 'ng_used', 235_000_00, null, 'Narayi', false, 84, 'phones-trio-lemon.jpg'],
            ['Sony', 'PlayStation 5', ['edition' => 'Disc'], 'brand_new', 520_000_00, null, 'Barnawa', false, null, 'laptop-and-phone-desk.jpg'],
            ['Apple', 'AirPods Pro (2nd gen)', [], 'uk_used', 145_000_00, 165_000_00, 'Kawo', true, null, 'laptop-minimal-white.jpg'],
            // More phones, so the "Phones" pill (the default category) fills a full 8-card grid on its own.
            ['Apple', 'iPhone 12', ['storage' => '128GB', 'colour' => 'Black'], 'uk_used', 340_000_00, null, 'Barnawa', false, 91, 'phone-in-hand-blue.jpg'],
            ['Samsung', 'Galaxy A14', ['storage' => '64GB'], 'brand_new', 155_000_00, null, 'Unguwan Rimi', false, null, 'phone-homescreen.jpg'],
            ['Tecno', 'Camon 20', ['storage' => '256GB'], 'brand_new', 210_000_00, null, 'Malali', true, null, 'phones-trio-coral.jpg'],
            ['Apple', 'iPhone 13', ['storage' => '128GB', 'colour' => 'Midnight'], 'ng_used', 380_000_00, null, 'Kawo', false, 86, 'phones-trio-lemon.jpg'],
        ];

        foreach ($listings as $i => [$brand, $name, $variant, $condition, $price, $wasPrice, $area, $swap, $battery, $photo]) {
            $productModel = ProductModel::where('brand', $brand)->where('name', $name)->first();

            if (! $productModel) {
                continue;
            }

            $seller = $sellers[$i % $sellers->count()];
            $title = trim($brand.' '.$name.' '.implode(' ', $variant));

            $listing = Listing::updateOrCreate(
                ['seller_profile_id' => $seller->id, 'product_model_id' => $productModel->id, 'title' => $title],
                [
                    'city_id' => $kaduna->id,
                    'variant' => $variant ?: null,
                    'condition' => $condition,
                    'battery_health' => $battery,
                    'price_kobo' => $price,
                    'was_price_kobo' => $wasPrice,
                    'quantity' => 1,
                    'open_to_swap' => $swap,
                    'status' => 'live',
                    'verification_status' => 'not_required',
                    'published_at' => now()->subHours($i * 6 + 1),
                ]
            );

            $this->attachImage($listing, $photo);
        }
    }

    private function attachImage(Listing $listing, string $filename): void
    {
        if ($listing->images()->exists()) {
            return;
        }

        $source = dirname(base_path()).'/design/bss-canvas/img/'.$filename;

        if (! is_file($source)) {
            return;
        }

        $destination = "listings/{$listing->id}/{$filename}";
        Storage::disk('public')->put($destination, file_get_contents($source));

        $listing->images()->create(['path' => $destination, 'sort_order' => 0]);
    }
}

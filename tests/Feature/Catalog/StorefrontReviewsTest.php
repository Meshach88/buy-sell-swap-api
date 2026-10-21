<?php

namespace Tests\Feature\Catalog;

use App\Models\Listing;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The storefront's rating/review count must come from the real `reviews` table,
 * never from seller_profiles.rating_avg/rating_count — those columns are only
 * a cache for a review-creation flow that doesn't exist yet (Phase 2), so right
 * now they're just seed placeholders with no real reviews behind them.
 */
class StorefrontReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_seller_with_no_real_reviews_shows_zero_despite_seeded_rating_columns(): void
    {
        $seller = SellerProfile::factory()->create(['rating_avg' => 4.7, 'rating_count' => 38]);

        $response = $this->getJson("/api/sellers/{$seller->slug}")->assertOk();

        $response->assertJsonPath('seller.rating_count', 0);
        $response->assertJsonPath('seller.rating_avg', 0);
        $response->assertJsonPath('recent_review', null);
    }

    public function test_a_real_review_is_reflected_in_the_storefronts_stats_and_recent_review(): void
    {
        $seller = SellerProfile::factory()->create(['rating_avg' => 4.7, 'rating_count' => 38]);
        $buyer = User::factory()->create(['name' => 'Hauwa Bello']);
        $listing = Listing::factory()->live()->create(['seller_profile_id' => $seller->id]);

        $orderId = DB::table('orders')->insertGetId([
            'number' => 'BSS-TEST-1', 'buyer_id' => $buyer->id,
            'delivery_name' => $buyer->name, 'delivery_phone' => '08011112222',
            'subtotal_kobo' => $listing->price_kobo, 'total_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId, 'seller_profile_id' => $seller->id, 'subtotal_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderItemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'seller_order_id' => $sellerOrderId, 'listing_id' => $listing->id,
            'seller_profile_id' => $seller->id, 'title' => $listing->title,
            'unit_price_kobo' => $listing->price_kobo, 'quantity' => 1, 'total_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Review::create([
            'order_item_id' => $orderItemId, 'buyer_id' => $buyer->id, 'seller_profile_id' => $seller->id,
            'listing_id' => $listing->id, 'rating' => 5,
            'body' => 'Phone was exactly as described and delivery was same day.',
        ]);

        $response = $this->getJson("/api/sellers/{$seller->slug}")->assertOk();

        $response->assertJsonPath('seller.rating_count', 1);
        $response->assertJsonPath('seller.rating_avg', 5);
        $response->assertJsonPath('recent_review.buyer_name', 'Hauwa B.');
        $response->assertJsonPath('recent_review.rating', 5);
    }

    public function test_hidden_reviews_are_excluded(): void
    {
        $seller = SellerProfile::factory()->create();
        $buyer = User::factory()->create();
        $listing = Listing::factory()->live()->create(['seller_profile_id' => $seller->id]);

        $orderId = DB::table('orders')->insertGetId([
            'number' => 'BSS-TEST-2', 'buyer_id' => $buyer->id,
            'delivery_name' => $buyer->name, 'delivery_phone' => '08011112222',
            'subtotal_kobo' => $listing->price_kobo, 'total_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId, 'seller_profile_id' => $seller->id, 'subtotal_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderItemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'seller_order_id' => $sellerOrderId, 'listing_id' => $listing->id,
            'seller_profile_id' => $seller->id, 'title' => $listing->title,
            'unit_price_kobo' => $listing->price_kobo, 'quantity' => 1, 'total_kobo' => $listing->price_kobo,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Review::create([
            'order_item_id' => $orderItemId, 'buyer_id' => $buyer->id, 'seller_profile_id' => $seller->id,
            'listing_id' => $listing->id, 'rating' => 1, 'body' => 'Hidden by moderation.', 'is_visible' => false,
        ]);

        $response = $this->getJson("/api/sellers/{$seller->slug}")->assertOk();

        $response->assertJsonPath('seller.rating_count', 0);
        $response->assertJsonPath('recent_review', null);
    }
}

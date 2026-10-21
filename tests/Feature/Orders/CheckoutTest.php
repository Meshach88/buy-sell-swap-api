<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\City;
use App\Models\CommissionTier;
use App\Models\DeliveryZone;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->city = City::factory()->create(['slug' => 'kaduna']);
        $this->zone = DeliveryZone::factory()->create([
            'city_id' => $this->city->id, 'areas' => ['Barnawa'], 'fee_kobo' => 150_000,
        ]);
        CommissionTier::create(['name' => 'Default', 'rate_bps' => 1000, 'is_default' => true]);
    }

    private function buyerWithAddress(): User
    {
        $user = User::factory()->create(['city_id' => $this->city->id]);
        $user->addresses()->create([
            'recipient_name' => $user->name, 'phone' => '08011112222',
            'street' => '1 Test Close', 'area' => 'Barnawa', 'delivery_zone_id' => $this->zone->id, 'is_default' => true,
        ]);

        return $user->refresh();
    }

    public function test_checkout_creates_one_seller_order_per_seller_and_clears_the_cart(): void
    {
        $buyer = $this->buyerWithAddress();
        Sanctum::actingAs($buyer);

        $sellerA = SellerProfile::factory()->create();
        $sellerB = SellerProfile::factory()->create();
        $listingA = Listing::factory()->live()->create(['seller_profile_id' => $sellerA->id, 'price_kobo' => 100_000, 'quantity' => 5]);
        $listingB = Listing::factory()->live()->create(['seller_profile_id' => $sellerB->id, 'price_kobo' => 200_000, 'quantity' => 5]);

        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listingA->id, 'quantity' => 2]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listingB->id, 'quantity' => 1]);

        $response = $this->postJson('/api/checkout', [
            'address_id' => $buyer->addresses->first()->id,
            'delivery_method' => 'delivery',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.subtotal_kobo', 400_000)
            ->assertJsonPath('data.delivery_fee_kobo', 150_000)
            ->assertJsonPath('data.total_kobo', 550_000)
            ->assertJsonCount(2, 'data.seller_orders');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('seller_orders', ['seller_profile_id' => $sellerA->id, 'subtotal_kobo' => 200_000]);
        $this->assertDatabaseHas('seller_orders', ['seller_profile_id' => $sellerB->id, 'subtotal_kobo' => 200_000]);
        $this->assertDatabaseHas('order_items', ['listing_id' => $listingA->id, 'quantity' => 2, 'total_kobo' => 200_000]);
    }

    public function test_commission_is_snapshotted_from_the_sellers_tier_at_10_percent_default(): void
    {
        $buyer = $this->buyerWithAddress();
        Sanctum::actingAs($buyer);
        $seller = SellerProfile::factory()->create();
        $listing = Listing::factory()->live()->create(['seller_profile_id' => $seller->id, 'price_kobo' => 100_000, 'quantity' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address_id' => $buyer->addresses->first()->id, 'delivery_method' => 'delivery'])
            ->assertCreated();

        $this->assertDatabaseHas('commissions', ['seller_profile_id' => $seller->id, 'rate_bps' => 1000, 'amount_kobo' => 10_000]);
        $this->assertDatabaseHas('seller_orders', ['seller_profile_id' => $seller->id, 'commission_kobo' => 10_000, 'net_kobo' => 90_000]);
    }

    public function test_pickup_does_not_charge_a_delivery_fee(): void
    {
        $buyer = $this->buyerWithAddress();
        Sanctum::actingAs($buyer);
        $listing = Listing::factory()->live()->create(['price_kobo' => 100_000, 'quantity' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address_id' => $buyer->addresses->first()->id, 'delivery_method' => 'pickup'])
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee_kobo', 0)
            ->assertJsonPath('data.total_kobo', 100_000);
    }

    public function test_checkout_fails_with_an_empty_cart(): void
    {
        $buyer = $this->buyerWithAddress();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/checkout', ['address_id' => $buyer->addresses->first()->id, 'delivery_method' => 'delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_fails_if_a_cart_item_sold_out_since_it_was_added(): void
    {
        $buyer = $this->buyerWithAddress();
        Sanctum::actingAs($buyer);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'quantity' => 1]);
        $listing->update(['quantity' => 0]);

        $this->postJson('/api/checkout', ['address_id' => $buyer->addresses->first()->id, 'delivery_method' => 'delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_fails_for_an_address_whose_area_has_no_delivery_zone(): void
    {
        $buyer = User::factory()->create(['city_id' => $this->city->id]);
        Sanctum::actingAs($buyer);
        $address = $buyer->addresses()->create([
            'recipient_name' => $buyer->name, 'phone' => '08011112222',
            'street' => '1 Test Close', 'area' => 'Nowhere', 'is_default' => true,
        ]);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address_id' => $address->id, 'delivery_method' => 'delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_id');
    }

    public function test_a_buyer_cannot_checkout_with_another_buyers_address(): void
    {
        $other = $this->buyerWithAddress();
        $buyer = User::factory()->create(['city_id' => $this->city->id]);
        Sanctum::actingAs($buyer);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address_id' => $other->addresses->first()->id, 'delivery_method' => 'delivery'])
            ->assertNotFound();
    }
}

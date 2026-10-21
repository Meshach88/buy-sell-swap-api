<?php

namespace Tests\Feature\Orders;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function seller(): User
    {
        $user = User::factory()->seller()->create();
        SellerProfile::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function sellerOrderFor(SellerProfile $seller, string $orderStatus = 'paid'): SellerOrder
    {
        $order = Order::factory()->create(['status' => $orderStatus]);
        $listing = Listing::factory()->live()->create(['seller_profile_id' => $seller->id]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_profile_id' => $seller->id, 'subtotal_kobo' => 100_000]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $seller->id, 'title' => $listing->title,
            'unit_price_kobo' => 100_000, 'quantity' => 1, 'total_kobo' => 100_000,
        ]);

        return $sellerOrder;
    }

    public function test_the_seller_order_queue_only_shows_paid_orders(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs($seller);
        $this->sellerOrderFor($seller->sellerProfile, 'paid');
        $this->sellerOrderFor($seller->sellerProfile, 'pending_payment');

        $this->getJson('/api/seller/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_seller_moves_an_order_through_the_fulfilment_pipeline_in_order(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs($seller);
        $sellerOrder = $this->sellerOrderFor($seller->sellerProfile);

        $this->postJson("/api/seller/orders/{$sellerOrder->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/seller/orders/{$sellerOrder->id}/pack")->assertOk()->assertJsonPath('data.status', 'packed');

        $handOff = $this->postJson("/api/seller/orders/{$sellerOrder->id}/hand-to-rider")->assertOk();
        $handOff->assertJsonPath('data.status', 'with_rider');
        $this->assertNotEmpty($handOff->json('data.handover_code'));

        $this->postJson("/api/seller/orders/{$sellerOrder->id}/mark-delivered")->assertOk()->assertJsonPath('data.status', 'delivered');
    }

    public function test_a_seller_cannot_skip_a_step(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs($seller);
        $sellerOrder = $this->sellerOrderFor($seller->sellerProfile);

        $this->postJson("/api/seller/orders/{$sellerOrder->id}/pack")->assertUnprocessable();
    }

    public function test_a_seller_cannot_manage_another_sellers_order(): void
    {
        $owner = $this->seller();
        $sellerOrder = $this->sellerOrderFor($owner->sellerProfile);
        Sanctum::actingAs($this->seller());

        $this->postJson("/api/seller/orders/{$sellerOrder->id}/accept")->assertForbidden();
    }

    public function test_a_customer_without_a_seller_profile_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('customer'));

        $this->getJson('/api/seller/orders')->assertForbidden();
    }

    public function test_the_seller_sees_a_privacy_safe_buyer_name_never_the_phone_or_street(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs($seller);
        $order = Order::factory()->create([
            'status' => 'paid', 'delivery_name' => 'Hauwa Bello', 'delivery_phone' => '08011112222',
            'delivery_street' => '12 Secret Close', 'delivery_area' => 'Barnawa',
        ]);
        $listing = Listing::factory()->live()->create(['seller_profile_id' => $seller->sellerProfile->id]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_profile_id' => $seller->sellerProfile->id, 'subtotal_kobo' => 100_000]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $seller->sellerProfile->id, 'title' => $listing->title,
            'unit_price_kobo' => 100_000, 'quantity' => 1, 'total_kobo' => 100_000,
        ]);

        $response = $this->getJson("/api/seller/orders/{$sellerOrder->id}")->assertOk();

        $response->assertJsonPath('data.buyer_name', 'Hauwa B.');
        $response->assertJsonPath('data.delivery_area', 'Barnawa');
        $this->assertStringNotContainsString('08011112222', $response->getContent());
        $this->assertStringNotContainsString('Secret Close', $response->getContent());
    }
}

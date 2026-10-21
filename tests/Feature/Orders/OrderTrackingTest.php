<?php

namespace Tests\Feature\Orders;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function sellerOrder(Order $order, ?string $status = 'new', ?string $escrow = 'held'): SellerOrder
    {
        $listing = Listing::factory()->live()->create();
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_profile_id' => $listing->seller_profile_id,
            'subtotal_kobo' => 100_000, 'status' => $status, 'escrow_status' => $escrow,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $listing->seller_profile_id, 'title' => $listing->title,
            'unit_price_kobo' => 100_000, 'quantity' => 1, 'total_kobo' => 100_000,
        ]);

        return $sellerOrder;
    }

    public function test_a_buyer_can_list_and_view_their_own_orders(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);
        $this->sellerOrder($order);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_a_buyer_cannot_view_someone_elses_order(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_cannot_confirm_delivery_before_the_seller_marks_it_delivered(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);
        $sellerOrder = $this->sellerOrder($order, 'packed');

        $this->postJson("/api/orders/{$order->id}/seller-orders/{$sellerOrder->id}/confirm-delivery")
            ->assertUnprocessable();
    }

    public function test_confirming_delivery_releases_escrow_and_completes_a_single_seller_order(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);
        $sellerOrder = $this->sellerOrder($order, 'delivered', 'held');

        $response = $this->postJson("/api/orders/{$order->id}/seller-orders/{$sellerOrder->id}/confirm-delivery");

        $response->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'escrow_status' => 'released']);
        $this->assertNotNull($sellerOrder->fresh()->buyer_confirmed_at);
    }

    public function test_order_only_completes_once_every_seller_order_is_confirmed(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);
        $first = $this->sellerOrder($order, 'delivered', 'held');
        $second = $this->sellerOrder($order, 'delivered', 'held');

        $this->postJson("/api/orders/{$order->id}/seller-orders/{$first->id}/confirm-delivery")
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->postJson("/api/orders/{$order->id}/seller-orders/{$second->id}/confirm-delivery")
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_double_confirm_an_already_released_seller_order(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);
        $sellerOrder = $this->sellerOrder($order, 'delivered', 'released');

        $this->postJson("/api/orders/{$order->id}/seller-orders/{$sellerOrder->id}/confirm-delivery")
            ->assertUnprocessable();
    }
}

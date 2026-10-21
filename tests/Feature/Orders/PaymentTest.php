<?php

namespace Tests\Feature\Orders;

use App\Models\CommissionTier;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        CommissionTier::create(['name' => 'Default', 'rate_bps' => 1000, 'is_default' => true]);
    }

    public function test_pay_initializes_a_paystack_transaction_and_records_a_pending_payment(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'total_kobo' => 150_000]);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'ref'],
        ])]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk()->assertJsonStructure(['authorization_url', 'reference', 'public_key', 'payment']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending', 'amount_kobo' => 150_000]);
    }

    public function test_cannot_pay_for_someone_elses_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->create();

        $this->postJson("/api/orders/{$order->id}/pay")->assertForbidden();
    }

    public function test_cannot_pay_for_an_order_thats_already_paid(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);

        $this->postJson("/api/orders/{$order->id}/pay")->assertUnprocessable();
    }

    private function orderWithItem(User $buyer, int $unitPriceKobo = 100_000, int $qty = 2): Order
    {
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'total_kobo' => $unitPriceKobo * $qty]);
        $listing = Listing::factory()->live()->create(['price_kobo' => $unitPriceKobo, 'quantity' => 5]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_profile_id' => $listing->seller_profile_id, 'subtotal_kobo' => $unitPriceKobo * $qty]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $listing->seller_profile_id, 'title' => $listing->title,
            'unit_price_kobo' => $unitPriceKobo, 'quantity' => $qty, 'total_kobo' => $unitPriceKobo * $qty,
        ]);

        return $order->fresh();
    }

    public function test_verify_marks_the_order_paid_and_decrements_stock_on_a_successful_charge(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = $this->orderWithItem($buyer, 100_000, 2);
        $listing = $order->items->first()->listing;
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'ref-1', 'amount_kobo' => 200_000, 'status' => 'pending']);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 200_000, 'channel' => 'card', 'id' => 999],
        ])]);

        $response = $this->getJson('/api/payments/ref-1/verify');

        $response->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.order.status', 'paid');
        $this->assertDatabaseHas('payments', ['reference' => 'ref-1', 'status' => 'success', 'method' => 'card']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertSame(3, $listing->fresh()->quantity); // 5 - 2
    }

    public function test_verify_does_not_mark_paid_on_an_amount_mismatch(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = $this->orderWithItem($buyer, 100_000, 2);
        Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'ref-2', 'amount_kobo' => 200_000, 'status' => 'pending']);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 50_000, 'channel' => 'card'],
        ])]);

        $this->getJson('/api/payments/ref-2/verify')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment']);
    }

    public function test_verify_is_idempotent_and_never_double_decrements_stock(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = $this->orderWithItem($buyer, 100_000, 2);
        $listing = $order->items->first()->listing;
        Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'ref-3', 'amount_kobo' => 200_000, 'status' => 'pending']);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 200_000, 'channel' => 'card'],
        ])]);

        $this->getJson('/api/payments/ref-3/verify')->assertOk();
        $this->getJson('/api/payments/ref-3/verify')->assertOk();

        $this->assertSame(3, $listing->fresh()->quantity);
    }

    public function test_cannot_verify_someone_elses_payment(): void
    {
        $order = Order::factory()->create();
        Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'ref-4', 'amount_kobo' => 1000, 'status' => 'pending']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payments/ref-4/verify')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Orders;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['paystack.secret_key' => 'test_secret']);
    }

    /** Signs the exact raw body Paystack would send, so the controller's
     * HMAC check sees real bytes rather than something Laravel re-encoded. */
    private function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha512', $raw, 'test_secret');

        return $this->call('POST', '/api/webhooks/paystack', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'X-Paystack-Signature' => $signature,
        ]), $raw);
    }

    public function test_rejects_a_webhook_with_a_bad_signature(): void
    {
        $this->postWebhook(['event' => 'charge.success', 'data' => ['reference' => 'ref']], 'wrong-signature')
            ->assertUnauthorized();

        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_a_charge_success_event_confirms_the_payment(): void
    {
        $order = Order::factory()->create(['total_kobo' => 100_000]);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_profile_id' => $listing->seller_profile_id, 'subtotal_kobo' => 100_000]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $listing->seller_profile_id, 'title' => $listing->title,
            'unit_price_kobo' => 100_000, 'quantity' => 1, 'total_kobo' => 100_000,
        ]);
        Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'wh-ref-1', 'amount_kobo' => 100_000, 'status' => 'pending']);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true, 'data' => ['status' => 'success', 'amount' => 100_000, 'channel' => 'card', 'id' => 1],
        ])]);

        $this->postWebhook(['event' => 'charge.success', 'data' => ['reference' => 'wh-ref-1', 'id' => 555]])
            ->assertNoContent();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payment_events', ['provider' => 'paystack', 'event' => 'charge.success', 'reference' => 'wh-ref-1']);
    }

    public function test_a_retried_webhook_delivery_is_a_harmless_no_op(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'wh-ref-2', 'id' => 777]];

        $this->postWebhook($payload)->assertNoContent();
        $this->postWebhook($payload)->assertNoContent();

        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_unknown_events_are_stored_but_otherwise_ignored(): void
    {
        $this->postWebhook(['event' => 'transfer.success', 'data' => ['reference' => 'wh-ref-3']])
            ->assertNoContent();

        $this->assertDatabaseHas('payment_events', ['event' => 'transfer.success']);
    }
}

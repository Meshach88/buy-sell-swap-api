<?php

namespace Tests\Feature\Orders;

use App\Models\Commission;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Order/Payment are financial records — ->delete() must hide them, never destroy them
 * or cascade-destroy what's attached (SellerOrder/OrderItem/Commission/Payment). */
class OrderSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_deleting_an_order_soft_deletes_it_and_preserves_its_children(): void
    {
        $order = Order::factory()->create();
        $listing = Listing::factory()->live()->create();
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_profile_id' => $listing->seller_profile_id, 'subtotal_kobo' => 100_000]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id,
            'seller_profile_id' => $listing->seller_profile_id, 'title' => $listing->title,
            'unit_price_kobo' => 100_000, 'quantity' => 1, 'total_kobo' => 100_000,
        ]);
        $commission = Commission::create(['order_item_id' => $orderItem->id, 'seller_profile_id' => $listing->seller_profile_id, 'rate_bps' => 1000, 'amount_kobo' => 10_000]);
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'paystack', 'reference' => 'ref-soft', 'amount_kobo' => 100_000, 'status' => 'success']);

        $order->delete();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertNull(Order::find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));

        // None of the children were destroyed by a real cascade, because ->delete() never issued a real SQL DELETE.
        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id]);
        $this->assertDatabaseHas('order_items', ['id' => $orderItem->id]);
        $this->assertDatabaseHas('commissions', ['id' => $commission->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_deleting_a_payment_soft_deletes_it(): void
    {
        $payment = Payment::create(['order_id' => Order::factory()->create()->id, 'provider' => 'paystack', 'reference' => 'ref-soft-2', 'amount_kobo' => 50_000, 'status' => 'success']);

        $payment->delete();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertNull(Payment::find($payment->id));
    }
}

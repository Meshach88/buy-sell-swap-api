<?php

namespace App\Services;

use App\Enums\EscrowStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Commission;
use App\Models\CommissionTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a buyer's cart into an Order: one Order + one SellerOrder per distinct
 * seller + one OrderItem per cart line, with price/commission snapshotted at
 * this moment so later listing edits never rewrite an already-placed order.
 * Stock is deliberately NOT touched here — it's decremented only once payment
 * is confirmed (see PaymentConfirmationService), so an abandoned/failed
 * checkout never leaves a listing's quantity wrongly reduced.
 */
class CheckoutService
{
    public function createOrderFromCart(User $user, Address $address, string $deliveryMethod): Order
    {
        $cartItems = CartItem::where('user_id', $user->id)
            ->with(['listing.seller.commissionTier'])
            ->get();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        foreach ($cartItems as $item) {
            $listing = $item->listing;

            if ($listing->status !== ListingStatus::Live || $listing->quantity <= 0) {
                throw ValidationException::withMessages(['cart' => "\"{$listing->title}\" is no longer available."]);
            }

            if ($item->quantity > $listing->quantity) {
                throw ValidationException::withMessages(['cart' => "Only {$listing->quantity} of \"{$listing->title}\" left in stock."]);
            }
        }

        $deliveryFee = 0;

        if ($deliveryMethod === 'delivery') {
            if (! $address->delivery_zone_id) {
                throw ValidationException::withMessages(['address_id' => "We don't deliver to this area yet."]);
            }

            $deliveryFee = $address->deliveryZone->fee_kobo;
        }

        $subtotal = $cartItems->sum(fn (CartItem $item) => $item->listing->price_kobo * $item->quantity);

        return DB::transaction(function () use ($cartItems, $user, $address, $deliveryMethod, $subtotal, $deliveryFee) {
            $order = Order::create([
                'number' => Order::nextNumber(),
                'buyer_id' => $user->id,
                'city_id' => $user->city_id,
                'delivery_zone_id' => $address->delivery_zone_id,
                'delivery_method' => $deliveryMethod,
                'delivery_name' => $address->recipient_name,
                'delivery_phone' => $address->phone,
                'delivery_street' => $address->street,
                'delivery_area' => $address->area,
                'subtotal_kobo' => $subtotal,
                'delivery_fee_kobo' => $deliveryFee,
                'total_kobo' => $subtotal + $deliveryFee,
                'status' => OrderStatus::PendingPayment->value,
                'placed_at' => now(),
            ]);

            $defaultRateBps = fn () => CommissionTier::where('is_default', true)->value('rate_bps') ?? 0;

            foreach ($cartItems->groupBy(fn (CartItem $item) => $item->listing->seller_profile_id) as $sellerItems) {
                $seller = $sellerItems->first()->listing->seller;
                $sellerSubtotal = $sellerItems->sum(fn (CartItem $item) => $item->listing->price_kobo * $item->quantity);
                $rateBps = $seller->commissionTier?->rate_bps ?? $defaultRateBps();

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_profile_id' => $seller->id,
                    'status' => SellerOrderStatus::New->value,
                    'escrow_status' => EscrowStatus::Held->value,
                    'subtotal_kobo' => $sellerSubtotal,
                ]);

                $sellerCommissionTotal = 0;

                foreach ($sellerItems as $item) {
                    $lineTotal = $item->listing->price_kobo * $item->quantity;
                    $commissionAmount = Money::commission($lineTotal, $rateBps);
                    $sellerCommissionTotal += $commissionAmount;

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'seller_order_id' => $sellerOrder->id,
                        'listing_id' => $item->listing_id,
                        'seller_profile_id' => $seller->id,
                        'title' => $item->listing->title,
                        'unit_price_kobo' => $item->listing->price_kobo,
                        'quantity' => $item->quantity,
                        'total_kobo' => $lineTotal,
                        'commission_rate_bps' => $rateBps,
                    ]);

                    Commission::create([
                        'order_item_id' => $orderItem->id,
                        'seller_profile_id' => $seller->id,
                        'rate_bps' => $rateBps,
                        'amount_kobo' => $commissionAmount,
                    ]);
                }

                $sellerOrder->update([
                    'commission_kobo' => $sellerCommissionTotal,
                    'net_kobo' => $sellerOrder->subtotal_kobo - $sellerCommissionTotal,
                ]);
            }

            CartItem::where('user_id', $user->id)->delete();

            return $order->load(['sellerOrders.seller', 'sellerOrders.items.listing.images', 'items.listing.images']);
        });
    }
}

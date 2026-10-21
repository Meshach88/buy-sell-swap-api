<?php

namespace App\Http\Controllers\Api;

use App\Enums\EscrowStatus;
use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['items.listing.images', 'sellerOrders.seller', 'sellerOrders.items.listing.images', 'payments'])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        return new OrderResource($order->load([
            'items.listing.images', 'sellerOrders.seller', 'sellerOrders.items.listing.images', 'payments',
        ]));
    }

    /** The buyer confirms one seller's portion was received, which is what actually
     * releases that seller's share of the held payment — delivered_at (set by the
     * seller) and buyer_confirmed_at are deliberately separate events. */
    public function confirmDelivery(Request $request, Order $order, SellerOrder $sellerOrder): OrderResource
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($sellerOrder->order_id === $order->id, 404);

        if ($sellerOrder->status !== SellerOrderStatus::Delivered) {
            throw ValidationException::withMessages(['status' => 'This order hasn\'t been marked delivered by the seller yet.']);
        }

        if ($sellerOrder->escrow_status !== EscrowStatus::Held) {
            throw ValidationException::withMessages(['status' => 'This order has already been confirmed.']);
        }

        $sellerOrder->update([
            'buyer_confirmed_at' => now(),
            'escrow_status' => EscrowStatus::Released->value,
            'released_at' => now(),
        ]);

        if ($order->sellerOrders()->where('escrow_status', '!=', EscrowStatus::Released->value)->doesntExist()) {
            $order->update(['status' => OrderStatus::Completed->value, 'completed_at' => now()]);
        }

        return new OrderResource($order->fresh(['items.listing.images', 'sellerOrders.seller', 'sellerOrders.items.listing.images', 'payments']));
    }
}

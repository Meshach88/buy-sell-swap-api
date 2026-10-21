<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\StoreCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    public function store(StoreCheckoutRequest $request): OrderResource
    {
        $data = $request->validated();

        // findOrFail on the user's own relation doubles as the ownership check.
        $address = $request->user()->addresses()->with('deliveryZone')->findOrFail($data['address_id']);

        $order = $this->checkout->createOrderFromCart($request->user(), $address, $data['delivery_method']);

        return new OrderResource($order);
    }
}

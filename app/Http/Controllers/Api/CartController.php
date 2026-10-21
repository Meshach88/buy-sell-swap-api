<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\AddCartItemRequest;
use App\Http\Requests\Checkout\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Listing;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondWithCart($request->user()->id);
    }

    /** Adding a listing already in the cart replaces its quantity rather than adding to it —
     * the cart page's quantity stepper is what increments/decrements from there. */
    public function store(AddCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $listing = Listing::findOrFail($data['listing_id']);
        $quantity = $data['quantity'] ?? 1;

        $this->assertAvailable($listing, $quantity);

        CartItem::updateOrCreate(
            ['user_id' => $request->user()->id, 'listing_id' => $listing->id],
            ['quantity' => $quantity],
        );

        return $this->respondWithCart($request->user()->id, 201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $this->assertAvailable($cartItem->listing, $request->integer('quantity'));

        $cartItem->update(['quantity' => $request->integer('quantity')]);

        return $this->respondWithCart($request->user()->id);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $cartItem->delete();

        return $this->respondWithCart($request->user()->id);
    }

    private function assertAvailable(Listing $listing, int $quantity): void
    {
        if ($listing->status !== ListingStatus::Live || $listing->quantity <= 0) {
            throw ValidationException::withMessages(['listing_id' => 'This listing is no longer available.']);
        }

        if ($quantity > $listing->quantity) {
            throw ValidationException::withMessages(['quantity' => "Only {$listing->quantity} left in stock."]);
        }
    }

    private function respondWithCart(int $userId, int $status = 200): JsonResponse
    {
        $items = CartItem::where('user_id', $userId)
            ->with(['listing.images', 'listing.productModel', 'listing.seller'])
            ->latest()
            ->get();

        $subtotal = $items->sum(fn (CartItem $item) => $item->listing->price_kobo * $item->quantity);

        return response()->json([
            'items' => CartItemResource::collection($items),
            'subtotal_kobo' => $subtotal,
            'subtotal_label' => Money::format($subtotal),
        ], $status);
    }
}

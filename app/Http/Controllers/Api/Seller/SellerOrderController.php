<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerOrderResource;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerOrderController extends Controller
{
    /** Only ever paid orders — anything still pending_payment isn't the seller's business yet. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $seller = $this->sellerProfile($request);

        $sellerOrders = $seller->sellerOrders()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::PendingPayment->value))
            ->with(['items.listing.images', 'order'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return SellerOrderResource::collection($sellerOrders);
    }

    public function show(Request $request, SellerOrder $sellerOrder): SellerOrderResource
    {
        $this->authorizeOwnership($request, $sellerOrder);

        return new SellerOrderResource($sellerOrder->load(['items.listing.images', 'order']));
    }

    public function accept(Request $request, SellerOrder $sellerOrder): SellerOrderResource
    {
        return $this->advance($request, $sellerOrder, SellerOrderStatus::Accepted, ['accepted_at' => now()]);
    }

    // The handover code is generated here, not in handToRider() — the seller needs to already
    // know it before the rider arrives, so they can read it out at collection time.
    public function pack(Request $request, SellerOrder $sellerOrder): SellerOrderResource
    {
        return $this->advance($request, $sellerOrder, SellerOrderStatus::Packed, [
            'packed_at' => now(),
            'handover_code' => $sellerOrder->handover_code ?? strtoupper(Str::random(6)),
        ]);
    }

    public function handToRider(Request $request, SellerOrder $sellerOrder): SellerOrderResource
    {
        return $this->advance($request, $sellerOrder, SellerOrderStatus::WithRider, ['handed_over_at' => now()]);
    }

    public function markDelivered(Request $request, SellerOrder $sellerOrder): SellerOrderResource
    {
        return $this->advance($request, $sellerOrder, SellerOrderStatus::Delivered, ['delivered_at' => now()]);
    }

    private function advance(Request $request, SellerOrder $sellerOrder, SellerOrderStatus $to, array $extra): SellerOrderResource
    {
        $this->authorizeOwnership($request, $sellerOrder);

        if ($sellerOrder->status->next() !== $to) {
            throw ValidationException::withMessages([
                'status' => "Can't move from \"{$sellerOrder->status->label()}\" to \"{$to->label()}\" directly.",
            ]);
        }

        $sellerOrder->update(['status' => $to->value, ...$extra]);

        return new SellerOrderResource($sellerOrder->fresh(['items.listing.images', 'order']));
    }

    private function authorizeOwnership(Request $request, SellerOrder $sellerOrder): void
    {
        abort_unless($sellerOrder->seller_profile_id === $this->sellerProfile($request)->id, 403);
    }

    private function sellerProfile(Request $request)
    {
        return $request->user()->sellerProfile ?: abort(403, 'No seller profile on this account.');
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'condition' => $this->condition->value,
            'condition_label' => $this->condition->label(),
            'grade' => $this->grade,
            'variant' => $this->variant,
            'battery_health' => $this->battery_health,
            'price_kobo' => $this->price_kobo,
            'price_label' => Money::format($this->price_kobo),
            'was_price_kobo' => $this->was_price_kobo,
            'was_price_label' => $this->was_price_kobo ? Money::format($this->was_price_kobo) : null,
            'quantity' => $this->quantity,
            'warranty_months' => $this->warranty_months,
            'warranty_note' => $this->warranty_note,
            'description' => $this->description,
            'open_to_swap' => $this->open_to_swap,
            'status' => $this->status->value,
            'verification_status' => $this->verification_status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'primary_image_url' => $this->whenLoaded('images', fn () => $this->images->first() ? asset('storage/'.$this->images->first()->path) : null),
            'images' => $this->whenLoaded('images', fn () => ListingImageResource::collection($this->images)),
            'product_model' => $this->whenLoaded('productModel', fn () => new ProductModelResource($this->productModel)),
            'seller' => $this->whenLoaded('seller', fn () => new SellerSummaryResource($this->seller)),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public-safe storefront header. No KYC/PII fields ever go here. */
/** @mixin SellerProfile */
class StorefrontResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name,
            'slug' => $this->slug,
            'area' => $this->area,
            'description' => $this->description,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'verified' => $this->verified_at !== null,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'deals_count' => $this->deals_count,
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

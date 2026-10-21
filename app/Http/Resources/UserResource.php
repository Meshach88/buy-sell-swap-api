<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'phone_verified' => $this->phone_verified_at !== null,
            'area' => $this->area,
            'roles' => $this->getRoleNames()->values(),
            'seller' => $this->whenLoaded('sellerProfile', fn () => $this->sellerProfile ? [
                'id' => $this->sellerProfile->id,
                'shop_name' => $this->sellerProfile->shop_name,
                'kyc_status' => $this->sellerProfile->kyc_status->value,
            ] : null),
        ];
    }
}

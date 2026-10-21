<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Address */
class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'street' => $this->street,
            'area' => $this->area,
            'is_default' => $this->is_default,
            'delivery_zone' => $this->whenLoaded('deliveryZone', fn () => $this->deliveryZone ? [
                'id' => $this->deliveryZone->id,
                'name' => $this->deliveryZone->name,
                'fee_kobo' => $this->deliveryZone->fee_kobo,
                'eta_label' => $this->deliveryZone->eta_label,
            ] : null),
        ];
    }
}

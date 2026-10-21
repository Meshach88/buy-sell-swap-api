<?php

namespace App\Http\Resources;

use App\Models\ListingImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingImage */
class ListingImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => asset('storage/'.$this->path),
            'kind' => $this->kind,
            'sort_order' => $this->sort_order,
        ];
    }
}

<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the seller: middleware in routes/api.php
    }

    public function rules(): array
    {
        return [
            'product_model_id' => ['required', 'exists:product_models,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'inventory_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'variant' => ['nullable', 'array'],
            'condition' => ['required', 'in:brand_new,uk_used,ng_used,for_parts'],
            'battery_health' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_kobo' => ['required', 'integer', 'min:100'],
            'was_price_kobo' => ['nullable', 'integer', 'gt:price_kobo'],
            'quantity' => ['required', 'integer', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'warranty_note' => ['nullable', 'string', 'max:255'],
            'open_to_swap' => ['sometimes', 'boolean'],
        ];
    }
}

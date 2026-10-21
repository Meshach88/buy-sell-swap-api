<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    public function rules(): array
    {
        return [
            'product_model_id' => ['sometimes', 'exists:product_models,id'],
            'city_id' => ['sometimes', 'exists:cities,id'],
            'inventory_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'variant' => ['nullable', 'array'],
            'condition' => ['sometimes', 'in:brand_new,uk_used,ng_used,for_parts'],
            'battery_health' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_kobo' => ['sometimes', 'integer', 'min:100'],
            'was_price_kobo' => ['nullable', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'warranty_note' => ['nullable', 'string', 'max:255'],
            'open_to_swap' => ['sometimes', 'boolean'],
        ];
    }
}

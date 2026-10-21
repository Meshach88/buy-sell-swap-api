<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership of address_id is checked in the controller
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'delivery_method' => ['required', 'in:delivery,pickup'],
        ];
    }
}

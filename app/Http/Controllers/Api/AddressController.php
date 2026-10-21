<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $addresses = request()->user()->addresses()
            ->with('deliveryZone')
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $zone = DeliveryZone::matching($user->city_id, $data['area']);

        if ($data['is_default'] ?? false) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            ...$data,
            'delivery_zone_id' => $zone?->id,
            'is_default' => $data['is_default'] ?? ($user->addresses()->count() === 0),
        ]);

        return (new AddressResource($address->load('deliveryZone')))
            ->response()
            ->setStatusCode(201);
    }
}

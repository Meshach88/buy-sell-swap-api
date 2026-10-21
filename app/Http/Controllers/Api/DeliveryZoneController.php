<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\City;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryZoneController extends Controller
{
    /** Active delivery zones for a city (defaults to the launch city), cheapest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $cityId = $request->filled('city_id')
            ? $request->integer('city_id')
            : City::where('slug', config('bss.default_city'))->value('id');

        $zones = DeliveryZone::where('city_id', $cityId)
            ->where('is_active', true)
            ->orderBy('fee_kobo')
            ->get();

        return DeliveryZoneResource::collection($zones);
    }
}

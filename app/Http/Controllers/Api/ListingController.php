<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingController extends Controller
{
    /** Search/browse: only live, in-stock listings are ever visible here. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->with(['images', 'productModel.category', 'seller'])
            ->where('status', ListingStatus::Live->value)
            ->where('quantity', '>', 0)
            // Matches the listing's own text, plus its model's brand and category — so
            // typing a category word ("phones") or brand ("apple") finds listings too,
            // not just the sidebar/pill filters that target category_id directly.
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('productModel', fn ($q) => $q->where('brand', 'like', "%{$term}%"))
                        ->orWhereHas('productModel.category', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas(
                'productModel', fn ($q) => $q->where('category_id', $request->integer('category_id'))
            ))
            ->when($request->filled('product_model_id'), fn ($q) => $q->where('product_model_id', $request->integer('product_model_id')))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', $request->integer('city_id')))
            // Comma-separated so the search page's multi-select condition filter works (e.g. "uk_used,ng_used").
            ->when($request->filled('condition'), fn ($q) => $q->whereIn('condition', explode(',', $request->string('condition'))))
            ->when($request->filled('seller_profile_id'), fn ($q) => $q->where('seller_profile_id', $request->integer('seller_profile_id')))
            ->when($request->filled('area'), fn ($q) => $q->whereHas('seller', fn ($q) => $q->where('area', $request->string('area'))))
            ->when($request->boolean('open_to_swap'), fn ($q) => $q->where('open_to_swap', true))
            ->when($request->boolean('verified'), fn ($q) => $q->whereHas('seller', fn ($q) => $q->whereNotNull('verified_at')))
            ->when($request->filled('min_price_kobo'), fn ($q) => $q->where('price_kobo', '>=', $request->integer('min_price_kobo')))
            ->when($request->filled('max_price_kobo'), fn ($q) => $q->where('price_kobo', '<=', $request->integer('max_price_kobo')))
            ->orderBy(...$this->sort($request))
            ->paginate(20)
            ->withQueryString();

        return ListingResource::collection($listings);
    }

    public function show(Listing $listing): ListingResource
    {
        abort_unless($listing->status === ListingStatus::Live, 404);

        return new ListingResource($listing->load(['images', 'productModel.category', 'seller']));
    }

    private function sort(Request $request): array
    {
        return match ($request->string('sort')->toString()) {
            'price_asc' => ['price_kobo', 'asc'],
            'price_desc' => ['price_kobo', 'desc'],
            default => ['published_at', 'desc'],
        };
    }
}

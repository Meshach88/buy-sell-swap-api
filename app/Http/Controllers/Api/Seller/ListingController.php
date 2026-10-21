<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\ListingStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\StoreListingRequest;
use App\Http\Requests\Listing\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\City;
use App\Models\Listing;
use App\Models\ProductModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    /** The signed-in seller's own listings, any status. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $seller = $this->sellerProfile($request);

        return ListingResource::collection(
            $seller->listings()->with(['images', 'productModel'])->latest()->paginate(20)
        );
    }

    public function store(StoreListingRequest $request): ListingResource
    {
        $seller = $this->sellerProfile($request);
        $data = $request->validated();
        $productModel = ProductModel::findOrFail($data['product_model_id']);

        $listing = Listing::create([
            ...$data,
            'seller_profile_id' => $seller->id,
            'city_id' => $data['city_id'] ?? $seller->city_id ?? City::where('slug', config('bss.default_city'))->value('id'),
            'title' => $this->buildTitle($productModel, $data['variant'] ?? null),
            'status' => ListingStatus::Draft->value,
            'verification_status' => VerificationStatus::NotRequired->value,
        ]);

        return new ListingResource($listing->load(['images', 'productModel']));
    }

    public function update(UpdateListingRequest $request, Listing $listing): ListingResource
    {
        $data = $request->validated();

        if (isset($data['product_model_id']) || array_key_exists('variant', $data)) {
            $productModel = ProductModel::findOrFail($data['product_model_id'] ?? $listing->product_model_id);
            $data['title'] = $this->buildTitle($productModel, $data['variant'] ?? $listing->variant);
        }

        $listing->update($data);

        return new ListingResource($listing->load(['images', 'productModel']));
    }

    public function publish(Request $request, Listing $listing): ListingResource
    {
        $this->authorize('publish', $listing);

        $listing->update([
            'status' => $listing->quantity > 0 ? ListingStatus::Live->value : ListingStatus::OutOfStock->value,
            'published_at' => $listing->published_at ?? now(),
        ]);

        return new ListingResource($listing->load(['images', 'productModel']));
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('delete', $listing);

        if ($listing->status !== ListingStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only a draft listing can be deleted. Remove a live one instead.']);
        }

        $listing->delete();

        return response()->json(status: 204);
    }

    public function storeImages(Request $request, Listing $listing): ListingResource
    {
        $this->authorize('update', $listing);

        $request->validate([
            'images' => ['required', 'array', 'max:8'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $nextSort = $listing->images()->max('sort_order') + 1;

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store("listings/{$listing->id}", 'public');
            $listing->images()->create(['path' => $path, 'sort_order' => $nextSort + $i]);
        }

        return new ListingResource($listing->load('images'));
    }

    private function sellerProfile(Request $request)
    {
        return $request->user()->sellerProfile ?: abort(403, 'No seller profile on this account.');
    }

    private function buildTitle(ProductModel $productModel, ?array $variant): string
    {
        $bits = array_filter([$productModel->brand, $productModel->name, ...array_values($variant ?? [])]);

        return implode(' ', $bits);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\StorefrontResource;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerStorefrontController extends Controller
{
    public function show(Request $request, SellerProfile $sellerProfile): JsonResponse
    {
        abort_if($sellerProfile->suspended_at !== null, 404);

        $listings = $sellerProfile->listings()
            // 'seller' too: ListingResource/ListingCard expect it for the area shown on each card.
            ->with(['images', 'productModel', 'seller'])
            ->where('status', ListingStatus::Live->value)
            ->where('quantity', '>', 0)
            ->latest('published_at')
            ->paginate(20)
            ->withQueryString();

        $visibleReviews = $sellerProfile->reviews()->where('is_visible', true);
        $reviewsCount = (clone $visibleReviews)->count();
        $recentReview = (clone $visibleReviews)->with('buyer')->latest()->first();

        // seller_profiles.rating_avg/rating_count are a denormalised cache meant to
        // be kept in sync by whatever eventually creates reviews (Phase 2/3) — no
        // such flow exists yet, so those columns are still just seed placeholders.
        // Override them here with the real count/average so the page never shows a
        // rating or a review count that the (currently empty) reviews table can't back up.
        $sellerProfile->setAttribute('rating_count', $reviewsCount);
        $sellerProfile->setAttribute('rating_avg', $reviewsCount ? round((clone $visibleReviews)->avg('rating'), 1) : 0);

        // Route through the resource collection's own response() so pagination's
        // data/links/meta shape survives being nested under "listings" here.
        return response()->json([
            'seller' => new StorefrontResource($sellerProfile),
            'listings' => ListingResource::collection($listings)->response($request)->getData(true),
            'recent_review' => $recentReview ? new ReviewResource($recentReview) : null,
        ]);
    }
}

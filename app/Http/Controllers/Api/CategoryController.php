<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /** Top-level categories, each carrying its live listing count for the home page's category pills. */
    public function index(): AnonymousResourceCollection
    {
        $counts = Listing::query()
            ->join('product_models', 'listings.product_model_id', '=', 'product_models.id')
            ->where('listings.status', ListingStatus::Live->value)
            ->where('listings.quantity', '>', 0)
            ->selectRaw('product_models.category_id as category_id, count(*) as total')
            ->groupBy('product_models.category_id')
            ->pluck('total', 'category_id');

        $categories = Category::whereNull('parent_id')->orderBy('sort_order')->get()
            ->each(fn (Category $category) => $category->listings_count = $counts[$category->id] ?? 0);

        return CategoryResource::collection($categories);
    }
}

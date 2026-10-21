<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductModelResource;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductModelController extends Controller
{
    /** Used by the seller listing form to find the standard model to list against. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $models = ProductModel::query()
            ->where('status', 'active')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('brand', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
            })
            ->orderBy('brand')->orderBy('name')
            ->paginate(20);

        return ProductModelResource::collection($models);
    }

    public function show(ProductModel $productModel): ProductModelResource
    {
        return new ProductModelResource($productModel->load('category'));
    }
}

<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\ProductModelController;
use App\Http\Controllers\Api\Seller\ListingController as SellerListingController;
use App\Http\Controllers\Api\Seller\SellerOrderController;
use App\Http\Controllers\Api\SellerStorefrontController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('otp/resend', [AuthController::class, 'resendOtp'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Catalogue and listings: browsing is public, city/condition/price-filtered search included.
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/product-models', [ProductModelController::class, 'index']);
Route::get('/product-models/{productModel:slug}', [ProductModelController::class, 'show']);
Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/{listing}', [ListingController::class, 'show']);
Route::get('/sellers/{sellerProfile:slug}', [SellerStorefrontController::class, 'show']);
Route::get('/delivery-zones', [DeliveryZoneController::class, 'index']);

// Paystack calls this directly — no Sanctum auth, trust comes from the HMAC signature instead.
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $request) => new UserResource($request->user()->load('sellerProfile')));

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);
    Route::get('/payments/{reference}/verify', [PaymentController::class, 'verify']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/seller-orders/{sellerOrder}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

    Route::middleware('role:seller')->prefix('seller')->group(function () {
        Route::get('/listings', [SellerListingController::class, 'index']);
        Route::post('/listings', [SellerListingController::class, 'store']);
        Route::patch('/listings/{listing}', [SellerListingController::class, 'update']);
        Route::post('/listings/{listing}/publish', [SellerListingController::class, 'publish']);
        Route::post('/listings/{listing}/images', [SellerListingController::class, 'storeImages']);
        Route::delete('/listings/{listing}', [SellerListingController::class, 'destroy']);

        Route::get('/orders', [SellerOrderController::class, 'index']);
        Route::get('/orders/{sellerOrder}', [SellerOrderController::class, 'show']);
        Route::post('/orders/{sellerOrder}/accept', [SellerOrderController::class, 'accept']);
        Route::post('/orders/{sellerOrder}/pack', [SellerOrderController::class, 'pack']);
        Route::post('/orders/{sellerOrder}/hand-to-rider', [SellerOrderController::class, 'handToRider']);
        Route::post('/orders/{sellerOrder}/mark-delivered', [SellerOrderController::class, 'markDelivered']);
    });
});

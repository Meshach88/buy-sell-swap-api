<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sellers (KYC + payout account), fulfilment locations, the standardized
 * product-model catalogue, seller listings against a model, and the buyer's
 * addresses, saved items and cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_name');
            $table->string('slug')->unique();
            $table->string('area')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_platform')->default(false); // BuySellSwap's own stock

            // KYC
            $table->string('kyc_status', 24)->default('draft')->index();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->text('kyc_note')->nullable();
            $table->string('id_type', 8)->nullable(); // nin | bvn
            $table->text('id_number')->nullable();    // encrypted by the model cast
            $table->string('id_document_path')->nullable();
            $table->string('selfie_path')->nullable();

            // Payout account
            $table->string('bank_name')->nullable();
            $table->text('bank_account_number')->nullable(); // encrypted by the model cast
            $table->string('bank_account_name')->nullable();
            $table->timestamp('bank_verified_at')->nullable();

            // Trust and performance (denormalised for fast listing pages)
            $table->timestamp('verified_at')->nullable(); // Verified Seller badge
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('deals_count')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('area')->nullable();
            $table->boolean('is_pickup')->default(true);
            $table->timestamps();
        });

        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('brand');
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('specs')->nullable();           // base specification
            $table->json('variant_options')->nullable(); // e.g. {"storage":["128GB"],"colour":["Graphite"]}
            $table->string('status', 16)->default('active')->index(); // active | requested | retired
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['brand', 'name']);
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_model_id')->constrained();
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained();
            $table->string('title'); // generated: model + variant
            $table->json('variant')->nullable();
            $table->string('condition', 16); // brand_new | uk_used | ng_used | for_parts
            $table->string('grade', 1)->nullable(); // A | B | C once inspected
            $table->unsignedTinyInteger('battery_health')->nullable();
            $table->text('imei')->nullable();        // encrypted by the model cast
            $table->string('imei_hash', 64)->nullable()->index(); // duplicate detection
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_kobo');
            $table->unsignedBigInteger('was_price_kobo')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedTinyInteger('warranty_months')->nullable();
            $table->string('warranty_note')->nullable();
            $table->boolean('open_to_swap')->default(false); // flag only in v1
            $table->string('status', 24)->default('draft'); // draft | pending_review | live | out_of_stock | rejected | removed
            $table->string('verification_status', 16)->default('not_required');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index(['city_id', 'status']);
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 16)->default('photo'); // photo | imei_screen | box
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('street');
            $table->string('area');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('saved_listings', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'listing_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('saved_listings');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('product_models');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('seller_profiles');
    }
};

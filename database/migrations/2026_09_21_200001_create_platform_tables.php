<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Platform reference data: cities (multi-city from day one), seller commission
 * tiers, delivery zones and the product category tree.
 * All money columns are whole kobo (₦1 = 100 kobo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('rate_bps'); // basis points: 800 = 8%
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('areas');
            $table->unsignedInteger('fee_kobo');
            $table->string('eta_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // Lucide icon name
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('area')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
        Schema::dropIfExists('categories');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('commission_tiers');
        Schema::dropIfExists('cities');
    }
};

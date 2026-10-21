<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Order and Payment are financial records: unlike User/SellerProfile/Review
 * (which already have their own status/suspended_at/is_visible flag for
 * "hide without destroying"), nothing protects these two from an accidental
 * hard delete — which would cascade and wipe every SellerOrder, OrderItem,
 * Commission and Payment under an order. Soft deletes make ->delete() safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One checkout = one `orders` row paid in one `payments` row. Each seller's
 * share is a `seller_orders` row whose money sits in escrow (`escrow_status`)
 * until the buyer confirms delivery. Released money is grouped into weekly
 * `settlements`, one `settlement_lines` row per seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('type', 16); // percent | fixed | free_delivery
            $table->unsignedBigInteger('value')->default(0); // percent, or kobo for fixed
            $table->unsignedBigInteger('min_order_kobo')->default(0);
            $table->json('applies_to')->nullable(); // category ids, seller ids
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status', 16)->default('draft')->index(); // draft | approved | paid
            $table->date('period_start');
            $table->date('period_end');
            $table->date('scheduled_for')->nullable();
            $table->unsignedBigInteger('total_gross_kobo')->default(0);
            $table->unsignedBigInteger('total_commission_kobo')->default(0);
            $table->unsignedBigInteger('total_net_kobo')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained();
            $table->unsignedBigInteger('gross_kobo');
            $table->unsignedBigInteger('commission_kobo');
            $table->unsignedBigInteger('net_kobo');
            $table->string('status', 16)->default('pending'); // pending | paid | failed
            $table->string('transfer_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['settlement_id', 'seller_profile_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // BSS-10432
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_method', 12)->default('delivery'); // delivery | pickup
            // Address snapshot: later edits to the address book never rewrite history
            $table->string('delivery_name');
            $table->string('delivery_phone', 20);
            $table->string('delivery_street')->nullable();
            $table->string('delivery_area')->nullable();
            $table->unsignedBigInteger('subtotal_kobo');
            $table->unsignedBigInteger('delivery_fee_kobo')->default(0);
            $table->unsignedBigInteger('discount_kobo')->default(0);
            $table->unsignedBigInteger('total_kobo');
            $table->string('status', 24)->default('pending_payment')->index();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'status']);
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained();
            $table->foreignId('settlement_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('new')->index(); // new | accepted | packed | with_rider | delivered | cancelled
            $table->string('escrow_status', 16)->default('held')->index(); // held | released | refunded | disputed
            $table->unsignedBigInteger('subtotal_kobo');
            $table->unsignedBigInteger('delivery_fee_kobo')->default(0);
            $table->unsignedBigInteger('commission_kobo')->default(0);
            $table->unsignedBigInteger('net_kobo')->default(0);
            $table->string('handover_code', 6)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['seller_profile_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained();
            $table->foreignId('seller_profile_id')->constrained();
            $table->string('title'); // snapshot
            $table->unsignedBigInteger('unit_price_kobo'); // snapshot
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total_kobo');
            $table->unsignedSmallInteger('commission_rate_bps')->default(0); // snapshot of the seller's rate
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained();
            $table->unsignedSmallInteger('rate_bps');
            $table->unsignedBigInteger('amount_kobo');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16)->default('paystack');
            $table->string('method', 16)->nullable(); // transfer | card | ussd
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedBigInteger('fee_kobo')->default(0);
            $table->string('status', 16)->default('pending')->index(); // pending | success | failed | refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // Every webhook is stored first, then processed by a queued job (idempotent on event_id).
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 16);
            $table->string('event');
            $table->string('event_id')->nullable();
            $table->string('reference')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('courier')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('to_assign')->index(); // to_assign | assigned | in_transit | delivered | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('failed_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('riders');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('promotions');
    }
};

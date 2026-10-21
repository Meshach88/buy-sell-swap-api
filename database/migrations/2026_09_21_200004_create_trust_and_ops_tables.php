<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Trust (verification, reviews, returns, disputes) and operations
 * (audit trail, phone OTP codes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_records', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // seller_profile or listing
            $table->string('kind', 16); // seller_kyc | device
            $table->string('status', 16)->default('requested')->index(); // requested | in_review | verified | rejected | needs_info
            $table->string('grade', 1)->nullable();
            $table->string('imei_status', 16)->nullable(); // clean | blacklisted | unchecked
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_profile_id')->constrained();
            $table->foreignId('listing_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->json('tags')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index('seller_profile_id');
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users');
            $table->string('reason', 32);
            $table->text('description')->nullable();
            $table->json('photos')->nullable();
            $table->string('requested_resolution', 16)->default('refund'); // refund | replacement
            $table->string('status', 24)->default('requested')->index();
            $table->unsignedBigInteger('amount_kobo')->default(0);
            $table->text('seller_note')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->constrained('users');
            $table->string('status', 16)->default('open')->index(); // open | reviewing | resolved
            $table->boolean('is_urgent')->default(false);
            $table->string('resolution', 24)->nullable(); // refund_buyer | release_to_seller | partial_refund
            $table->unsignedBigInteger('amount_kobo')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->string('purpose', 16); // register | login | reset
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('verification_records');
    }
};

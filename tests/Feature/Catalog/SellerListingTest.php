<?php

namespace Tests\Feature\Catalog;

use App\Models\Listing;
use App\Models\ProductModel;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function seller(): User
    {
        $user = User::factory()->seller()->create();
        SellerProfile::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_a_seller_can_create_a_draft_listing(): void
    {
        Sanctum::actingAs($seller = $this->seller());
        $productModel = ProductModel::factory()->create();

        $response = $this->postJson('/api/seller/listings', [
            'product_model_id' => $productModel->id,
            'condition' => 'ng_used',
            'price_kobo' => 150_000_00,
            'quantity' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('listings', [
            'seller_profile_id' => $seller->sellerProfile->id,
            'product_model_id' => $productModel->id,
        ]);
    }

    public function test_a_customer_cannot_create_a_listing(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('customer'));

        $this->postJson('/api/seller/listings', [
            'product_model_id' => ProductModel::factory()->create()->id,
            'condition' => 'ng_used',
            'price_kobo' => 150_000_00,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_a_seller_can_publish_their_own_listing_and_out_of_stock_when_qty_zero(): void
    {
        Sanctum::actingAs($seller = $this->seller());
        $listing = Listing::factory()->create(['seller_profile_id' => $seller->sellerProfile->id, 'quantity' => 3]);

        $this->postJson("/api/seller/listings/{$listing->id}/publish")
            ->assertOk()->assertJsonPath('data.status', 'live');

        $listing->update(['quantity' => 0]);
        $listing->refresh()->forceFill(['published_at' => null])->save();

        $this->postJson("/api/seller/listings/{$listing->id}/publish")
            ->assertOk()->assertJsonPath('data.status', 'out_of_stock');
    }

    public function test_a_seller_cannot_update_another_sellers_listing(): void
    {
        $other = Listing::factory()->create();
        Sanctum::actingAs($this->seller());

        $this->patchJson("/api/seller/listings/{$other->id}", ['price_kobo' => 200_000_00])
            ->assertForbidden();
    }

    public function test_a_seller_can_upload_images_for_their_listing(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($seller = $this->seller());
        $listing = Listing::factory()->create(['seller_profile_id' => $seller->sellerProfile->id]);

        $response = $this->postJson("/api/seller/listings/{$listing->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg')],
        ]);

        $response->assertOk();
        $this->assertCount(1, $listing->images);
    }

    public function test_only_a_draft_listing_can_be_deleted(): void
    {
        Sanctum::actingAs($seller = $this->seller());
        $live = Listing::factory()->live()->create(['seller_profile_id' => $seller->sellerProfile->id]);

        $this->deleteJson("/api/seller/listings/{$live->id}")->assertUnprocessable();

        $draft = Listing::factory()->create(['seller_profile_id' => $seller->sellerProfile->id, 'status' => 'draft']);
        $this->deleteJson("/api/seller/listings/{$draft->id}")->assertNoContent();
    }
}

<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_buyer_can_add_a_live_listing_to_their_cart(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $listing = Listing::factory()->live()->create(['quantity' => 5, 'price_kobo' => 100_000]);

        $response = $this->postJson('/api/cart/items', ['listing_id' => $listing->id, 'quantity' => 2]);

        $response->assertCreated();
        $response->assertJsonPath('subtotal_kobo', 200_000);
        $this->assertDatabaseHas('cart_items', ['listing_id' => $listing->id, 'quantity' => 2]);
    }

    public function test_adding_the_same_listing_again_replaces_the_quantity_not_adds_to_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);

        $this->postJson('/api/cart/items', ['listing_id' => $listing->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/cart/items', ['listing_id' => $listing->id, 'quantity' => 3])->assertCreated();

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['listing_id' => $listing->id, 'quantity' => 3]);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $listing = Listing::factory()->live()->create(['quantity' => 2]);

        $this->postJson('/api/cart/items', ['listing_id' => $listing->id, 'quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_cannot_add_a_draft_listing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $listing = Listing::factory()->create(['status' => 'draft']);

        $this->postJson('/api/cart/items', ['listing_id' => $listing->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');
    }

    public function test_a_buyer_can_update_quantity_and_remove_an_item(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        $this->postJson('/api/cart/items', ['listing_id' => $listing->id, 'quantity' => 1]);
        $cartItemId = CartItem::first()->id;

        $this->patchJson("/api/cart/items/{$cartItemId}", ['quantity' => 4])
            ->assertOk()->assertJsonPath('items.0.quantity', 4);

        $this->deleteJson("/api/cart/items/{$cartItemId}")->assertOk()->assertJsonPath('items', []);
    }

    public function test_a_buyer_cannot_modify_another_buyers_cart_item(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->live()->create(['quantity' => 5]);
        $cartItem = CartItem::create(['user_id' => $owner->id, 'listing_id' => $listing->id, 'quantity' => 1]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/cart/items/{$cartItem->id}", ['quantity' => 2])->assertForbidden();
        $this->deleteJson("/api/cart/items/{$cartItem->id}")->assertForbidden();
    }
}

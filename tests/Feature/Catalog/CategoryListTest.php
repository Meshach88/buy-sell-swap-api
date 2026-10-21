<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ProductModel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class); // Listing::factory() chains through a seller user
    }

    public function test_categories_carry_their_live_listing_count(): void
    {
        $phones = Category::factory()->create();
        $laptops = Category::factory()->create();
        $phoneModel = ProductModel::factory()->create(['category_id' => $phones->id]);
        ProductModel::factory()->create(['category_id' => $laptops->id]);

        Listing::factory()->live()->count(2)->create(['product_model_id' => $phoneModel->id]);
        Listing::factory()->create(['product_model_id' => $phoneModel->id, 'status' => 'draft']); // not counted

        $response = $this->getJson('/api/categories')->assertOk();

        $body = collect($response->json('data'))->keyBy('id');
        $this->assertSame(2, $body[$phones->id]['listings_count']);
        $this->assertSame(0, $body[$laptops->id]['listings_count']);
    }
}

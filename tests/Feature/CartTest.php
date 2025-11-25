<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_unauthenticated_user_cannot_add_item_to_cart(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_add_item_to_cart(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product = Product::factory()->create();

        $response = $this->postJson(
            '/api/cart',
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(201);
    }

    public function test_cannot_add_inactive_product_to_cart(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->postJson(
            '/api/cart',
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(404);
    }

    public function test_cannot_add_invalid_product_to_cart(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson(
            '/api/cart',
            [
                'product_id' => 99999,
                'quantity' => 1,
            ],
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_can_get_cart_contents(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product1 = Product::factory()->create(['price' => 10.00]);
        $product2 = Product::factory()->create(['price' => 20.00]);

        // Add items to cart
        $this->postJson(
            '/api/cart',
            ['product_id' => $product1->id, 'quantity' => 2],
            $this->getAuthHeaders($auth['token'])
        );
        $this->postJson(
            '/api/cart',
            ['product_id' => $product2->id, 'quantity' => 1],
            $this->getAuthHeaders($auth['token'])
        );

        $response = $this->getJson('/api/cart', $this->getAuthHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'total',
                'formatted_total',
                'item_count',
            ]);

        $this->assertCount(2, $response->json('items'));
        $this->assertEquals(40.00, $response->json('total')); // (10 * 2) + (20 * 1)
    }

    public function test_can_update_cart_item_quantity(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product = Product::factory()->create();

        // Add item
        $this->postJson(
            '/api/cart',
            ['product_id' => $product->id, 'quantity' => 1],
            $this->getAuthHeaders($auth['token'])
        );

        // Update quantity
        $response = $this->putJson(
            "/api/cart/{$product->id}",
            ['quantity' => 5],
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(200);

        // Verify cart
        $cartResponse = $this->getJson('/api/cart', $this->getAuthHeaders($auth['token']));
        $this->assertEquals(5, $cartResponse->json('items.0.quantity'));
    }

    public function test_can_remove_item_from_cart(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // Add items
        $this->postJson(
            '/api/cart',
            ['product_id' => $product1->id, 'quantity' => 1],
            $this->getAuthHeaders($auth['token'])
        );
        $this->postJson(
            '/api/cart',
            ['product_id' => $product2->id, 'quantity' => 1],
            $this->getAuthHeaders($auth['token'])
        );

        // Remove one item
        $response = $this->deleteJson(
            "/api/cart/{$product1->id}",
            [],
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(200);

        // Verify cart
        $cartResponse = $this->getJson('/api/cart', $this->getAuthHeaders($auth['token']));
        $this->assertCount(1, $cartResponse->json('items'));
    }

    public function test_can_clear_cart(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product = Product::factory()->create();

        // Add item
        $this->postJson(
            '/api/cart',
            ['product_id' => $product->id, 'quantity' => 1],
            $this->getAuthHeaders($auth['token'])
        );

        // Clear cart
        $response = $this->deleteJson('/api/cart', [], $this->getAuthHeaders($auth['token']));

        $response->assertStatus(200);

        // Verify cart is empty
        $cartResponse = $this->getJson('/api/cart', $this->getAuthHeaders($auth['token']));
        $this->assertCount(0, $cartResponse->json('items'));
        $this->assertEquals(0, $cartResponse->json('total'));
    }

    public function test_cart_quantity_cannot_exceed_maximum(): void
    {
        $auth = $this->createAuthenticatedUser();
        $product = Product::factory()->create();

        $response = $this->postJson(
            '/api/cart',
            ['product_id' => $product->id, 'quantity' => 15], // Max is 10
            $this->getAuthHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_unauthenticated_user_cannot_access_cart(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertStatus(401);
    }
}


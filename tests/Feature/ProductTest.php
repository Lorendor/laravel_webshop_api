<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'price', 'formatted_price', 'file_type', 'category'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_only_active_products_are_listed(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_search_products(): void
    {
        Product::factory()->create(['name' => 'Beautiful Design']);
        Product::factory()->create(['name' => 'Amazing Template']);
        Product::factory()->create(['name' => 'Cool Graphics']);

        $response = $this->getJson('/api/products?search=Beautiful');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('Beautiful', $response->json('data.0.name'));
    }

    public function test_can_filter_products_by_category(): void
    {
        Product::factory()->create(['category' => 'Graphics']);
        Product::factory()->create(['category' => 'Icons']);
        Product::factory()->create(['category' => 'Graphics']);

        $response = $this->getJson('/api/products?category=Graphics');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $product) {
            $this->assertEquals('Graphics', $product['category']);
        }
    }

    public function test_can_filter_products_by_file_type(): void
    {
        Product::factory()->create(['file_type' => 'PSD']);
        Product::factory()->create(['file_type' => 'AI']);
        Product::factory()->create(['file_type' => 'PSD']);

        $response = $this->getJson('/api/products?file_type=PSD');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $product) {
            $this->assertEquals('PSD', $product['file_type']);
        }
    }

    public function test_can_filter_products_by_price_range(): void
    {
        Product::factory()->create(['price' => 10.00]);
        Product::factory()->create(['price' => 50.00]);
        Product::factory()->create(['price' => 100.00]);

        $response = $this->getJson('/api/products?min_price=20&max_price=80');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(50.00, $response->json('data.0.price'));
    }

    public function test_can_sort_products(): void
    {
        Product::factory()->create(['price' => 100.00]);
        Product::factory()->create(['price' => 10.00]);
        Product::factory()->create(['price' => 50.00]);

        $response = $this->getJson('/api/products?sort_by=price&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(10.00, $data[0]['price']);
        $this->assertEquals(50.00, $data[1]['price']);
        $this->assertEquals(100.00, $data[2]['price']);
    }

    public function test_can_get_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                ],
            ]);
    }

    public function test_cannot_get_inactive_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(404);
    }

    public function test_products_are_paginated(): void
    {
        Product::factory()->count(20)->create();

        $response = $this->getJson('/api/products?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
    }
}


<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Cola',
            'stock' => 50,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Cola',
                'stock' => 50,
            ]);

        $this->assertDatabaseHas('products', ['name' => 'Cola', 'stock' => 50]);
    }

    public function test_create_product_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => '',
            'stock' => -5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'stock']);
    }

    public function test_can_update_product_stock(): void
    {
        $product = Product::create(['name' => 'Cola', 'stock' => 10]);

        $response = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => 25,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['stock' => 25]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 25]);
    }

    public function test_can_delete_product(): void
    {
        $product = Product::create(['name' => 'Cola', 'stock' => 10]);

        $this->deleteJson("/api/products/{$product->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}

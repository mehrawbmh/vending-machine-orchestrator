<?php

namespace Tests\Feature;

use App\Enums\VendingMachineStatus;
use App\Models\Product;
use App\Models\VendingMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrchestratorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_work_selects_idle_machine(): void
    {
        VendingMachine::create(['name' => 'Machine A', 'usage_count' => 3]);
        VendingMachine::create(['name' => 'Machine B', 'usage_count' => 1]);

        $response = $this->postJson('/api/orchestrator/start-work');

        $response->assertStatus(200)
            ->assertJsonPath('machine.name', 'Machine B')
            ->assertJsonPath('machine.status', 'choose_product');
    }

    public function test_start_work_returns_409_when_no_idle_machine(): void
    {
        VendingMachine::create([
            'name' => 'Busy',
            'status' => VendingMachineStatus::Processing,
        ]);

        $response = $this->postJson('/api/orchestrator/start-work');

        $response->assertStatus(409)
            ->assertJsonPath('error', 'No idle vending machine available.');
    }

    public function test_choose_product_success(): void
    {
        Queue::fake();

        $machine = VendingMachine::create([
            'name' => 'Machine A',
            'status' => VendingMachineStatus::ChooseProduct,
        ]);
        $product = Product::create(['name' => 'Cola', 'stock' => 20]);

        $response = $this->postJson('/api/orchestrator/choose-product', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'count' => 5,
            'coins' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('machine.status', 'processing')
            ->assertJsonPath('product.stock', 15);
    }

    public function test_choose_product_fails_when_coins_mismatch(): void
    {
        $machine = VendingMachine::create([
            'name' => 'Machine A',
            'status' => VendingMachineStatus::ChooseProduct,
        ]);
        $product = Product::create(['name' => 'Cola', 'stock' => 20]);

        $response = $this->postJson('/api/orchestrator/choose-product', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'count' => 5,
            'coins' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Coins must equal the number of products (1 coin per item).');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 20]);
    }
}

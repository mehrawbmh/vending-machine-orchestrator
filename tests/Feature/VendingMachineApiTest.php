<?php

namespace Tests\Feature;

use App\Models\VendingMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendingMachineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_vending_machine(): void
    {
        $response = $this->postJson('/api/vending-machines', [
            'name' => 'Machine A',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Machine A']);

        $this->assertDatabaseHas('vending_machines', [
            'name' => 'Machine A',
            'status' => 'idle',
            'usage_count' => 0,
        ]);
    }

    public function test_create_vending_machine_fails_without_name(): void
    {
        $response = $this->postJson('/api/vending-machines', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_list_and_show_vending_machines(): void
    {
        $machine = VendingMachine::create(['name' => 'Machine A']);

        $this->getJson('/api/vending-machines')
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->getJson("/api/vending-machines/{$machine->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Machine A']);
    }

    public function test_can_update_and_delete_vending_machine(): void
    {
        $machine = VendingMachine::create(['name' => 'Old Name']);

        $this->putJson("/api/vending-machines/{$machine->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);

        $this->deleteJson("/api/vending-machines/{$machine->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('vending_machines', ['id' => $machine->id]);
    }
}

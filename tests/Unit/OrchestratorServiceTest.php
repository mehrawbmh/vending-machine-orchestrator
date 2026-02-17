<?php

namespace Tests\Unit;

use App\Enums\VendingMachineStatus;
use App\Jobs\ProcessVendingMachineJob;
use App\Models\Product;
use App\Models\VendingMachine;
use App\Services\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrchestratorService $service;

    public function test_start_work_selects_least_used_idle_machine(): void
    {
        VendingMachine::create(['name' => 'Machine A', 'usage_count' => 5]);
        VendingMachine::create(['name' => 'Machine B', 'usage_count' => 2]);
        VendingMachine::create(['name' => 'Machine C', 'usage_count' => 8]);

        $machine = $this->service->startWork();

        $this->assertEquals('Machine B', $machine->name);
        $this->assertEquals(VendingMachineStatus::ChooseProduct, $machine->status);
    }

    public function test_start_work_throws_when_no_idle_machine(): void
    {
        VendingMachine::create(['name' => 'Busy', 'status' => VendingMachineStatus::Processing]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No idle vending machine available.');

        $this->service->startWork();
    }

    public function test_choose_product_decrements_stock_and_dispatches_job(): void
    {
        Queue::fake();

        $machine = VendingMachine::create([
            'name' => 'Machine A',
            'status' => VendingMachineStatus::ChooseProduct,
        ]);
        $product = Product::create(['name' => 'Cola', 'stock' => 10]);

        $result = $this->service->chooseProduct($machine->id, $product->id, 3, 3);

        $this->assertEquals(VendingMachineStatus::Processing, $result['machine']->status);
        $this->assertEquals(7, $result['product']->stock);

        Queue::assertPushed(ProcessVendingMachineJob::class);
    }

    public function test_choose_product_fails_with_insufficient_stock(): void
    {
        $machine = VendingMachine::create([
            'name' => 'Machine A',
            'status' => VendingMachineStatus::ChooseProduct,
        ]);
        $product = Product::create(['name' => 'Cola', 'stock' => 2]);

        $this->expectException(RuntimeException::class);

        $this->service->chooseProduct($machine->id, $product->id, 5, 5);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrchestratorService();
    }
}

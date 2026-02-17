<?php

namespace App\Services;

use App\Enums\VendingMachineStatus;
use App\Jobs\ProcessVendingMachineJob;
use App\Models\Product;
use App\Models\VendingMachine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrchestratorService
{
    /**
     * Select the least-used idle vending machine and move it to choose_product state.
     *
     * @return VendingMachine
     *
     * @throws RuntimeException when no idle machine is available
     */
    public function startWork(): VendingMachine
    {
        return DB::transaction(function () {
            $machine = VendingMachine::where('status', VendingMachineStatus::Idle)
                ->orderBy('usage_count')
                ->lockForUpdate()
                ->first();

            if (!$machine) {
                throw new RuntimeException('No idle vending machine available.');
            }

            $machine->update(['status' => VendingMachineStatus::ChooseProduct]);

            return $machine;
        });
    }

    /**
     * Process a product purchase through a vending machine.
     *
     * Validates the machine state, checks coin sufficiency, locks inventory
     * for concurrency safety, decrements stock, and dispatches a background
     * job to simulate product delivery.
     *
     * @param int $machineId
     * @param int $productId
     * @param int $count
     * @param int $coins
     * @return array{machine: VendingMachine, product: Product}
     *
     * @throws RuntimeException on business rule violations
     */
    public function chooseProduct(int $machineId, int $productId, int $count, int $coins): array
    {
        if ($coins !== $count) {
            throw new RuntimeException('Coins must equal the number of products (1 coin per item).');
        }

        return DB::transaction(function () use ($machineId, $productId, $count) {
            $machine = VendingMachine::lockForUpdate()->findOrFail($machineId);

            if ($machine->status !== VendingMachineStatus::ChooseProduct) {
                throw new RuntimeException('Machine is not in choose_product state.');
            }

            $product = Product::lockForUpdate()->findOrFail($productId);
            if ($product->stock < $count) {
                throw new RuntimeException('Insufficient stock. Available count: ' . $product->stock);
            }

            $product->decrement('stock', $count);
            $machine->update(['status' => VendingMachineStatus::Processing]);

            ProcessVendingMachineJob::dispatch($machine->id);

            return [
                'machine' => $machine->fresh(),
                'product' => $product->fresh(),
            ];
        });
    }
}

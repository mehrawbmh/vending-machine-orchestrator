<?php

namespace App\Jobs;

use App\Enums\VendingMachineStatus;
use App\Models\VendingMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVendingMachineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $vendingMachineId,
    )
    {
    }

    public function handle(): void
    {
        // Simulate the time it takes to find and deliver the product
        sleep(5);

        $machine = VendingMachine::findOrFail($this->vendingMachineId);
        $machine->update([
            'status' => VendingMachineStatus::Idle,
            'usage_count' => $machine->usage_count + 1,
        ]);
    }
}

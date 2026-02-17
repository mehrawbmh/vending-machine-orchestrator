<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VendingMachineStatus',
    type: 'string',
    enum: ['idle', 'choose_product', 'processing'],
    description: 'The operational state of a vending machine.',
)]
enum VendingMachineStatus: string
{
    case Idle = 'idle';
    case ChooseProduct = 'choose_product';
    case Processing = 'processing';
}

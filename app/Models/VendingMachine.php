<?php

namespace App\Models;

use App\Enums\VendingMachineStatus;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VendingMachine',
    required: ['id', 'name', 'status', 'usage_count', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Machine A'),
        new OA\Property(property: 'status', ref: '#/components/schemas/VendingMachineStatus'),
        new OA\Property(property: 'usage_count', type: 'integer', example: 0),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
class VendingMachine extends Model
{
    protected $fillable = [
        'name',
        'status',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendingMachineStatus::class,
            'usage_count' => 'integer',
        ];
    }
}

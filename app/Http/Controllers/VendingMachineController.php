<?php

namespace App\Http\Controllers;

use App\Enums\VendingMachineStatus;
use App\Http\Requests\StoreVendingMachineRequest;
use App\Http\Requests\UpdateVendingMachineRequest;
use App\Models\VendingMachine;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class VendingMachineController extends Controller
{
    #[OA\Get(
        path: '/api/vending-machines',
        summary: 'List all vending machines',
        tags: ['Vending Machines'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of vending machines',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/VendingMachine')),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json(VendingMachine::all());
    }

    #[OA\Post(
        path: '/api/vending-machines',
        summary: 'Create a new vending machine',
        tags: ['Vending Machines'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Machine A'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Machine created',
                content: new OA\JsonContent(ref: '#/components/schemas/VendingMachine'),
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreVendingMachineRequest $request): JsonResponse
    {
        $machine = VendingMachine::create($request->validated());

        return response()->json($machine, 201);
    }

    #[OA\Get(
        path: '/api/vending-machines/{id}',
        summary: 'Get a vending machine by ID',
        tags: ['Vending Machines'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Vending machine details',
                content: new OA\JsonContent(ref: '#/components/schemas/VendingMachine'),
            ),
            new OA\Response(response: 404, description: 'Machine not found'),
        ],
    )]
    public function show(VendingMachine $vendingMachine): JsonResponse
    {
        return response()->json($vendingMachine);
    }

    #[OA\Post(
        path: '/api/vending-machines/{id}/reset',
        summary: 'Reset a vending machine to idle state',
        description: 'Forces a vending machine back to idle state regardless of its current state. Useful for recovering stuck machines.',
        tags: ['Vending Machines'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Machine reset to idle',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Machine has been reset to idle state.'),
                        new OA\Property(property: 'machine', ref: '#/components/schemas/VendingMachine'),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'Machine not found'),
            new OA\Response(
                response: 409,
                description: 'Machine is already idle',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Machine is already idle.'),
                    ],
                ),
            ),
        ],
    )]
    public function reset(VendingMachine $vendingMachine): JsonResponse
    {
        if ($vendingMachine->status === VendingMachineStatus::Idle) {
            return response()->json([
                'error' => 'Machine is already idle.',
            ], 409);
        }

        $vendingMachine->update(['status' => VendingMachineStatus::Idle]);

        return response()->json([
            'message' => 'Machine has been reset to idle state.',
            'machine' => $vendingMachine->fresh(),
        ]);
    }

    #[OA\Put(
        path: '/api/vending-machines/{id}',
        summary: 'Update a vending machine',
        tags: ['Vending Machines'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Machine A (Updated)'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Machine updated',
                content: new OA\JsonContent(ref: '#/components/schemas/VendingMachine'),
            ),
            new OA\Response(response: 404, description: 'Machine not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(UpdateVendingMachineRequest $request, VendingMachine $vendingMachine): JsonResponse
    {
        $vendingMachine->update($request->validated());

        return response()->json($vendingMachine);
    }

    #[OA\Delete(
        path: '/api/vending-machines/{id}',
        summary: 'Delete a vending machine',
        tags: ['Vending Machines'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Machine deleted'),
            new OA\Response(response: 404, description: 'Machine not found'),
        ],
    )]
    public function destroy(VendingMachine $vendingMachine): JsonResponse
    {
        $vendingMachine->delete();

        return response()->json(null, 204);
    }
}

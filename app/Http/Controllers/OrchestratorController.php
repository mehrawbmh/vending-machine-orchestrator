<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChooseProductRequest;
use App\Services\OrchestratorService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use RuntimeException;

class OrchestratorController extends Controller
{
    public function __construct(
        private readonly OrchestratorService $orchestratorService,
    )
    {
    }

    #[OA\Post(
        path: '/api/orchestrator/start-work',
        summary: 'Select the least-used idle vending machine',
        description: 'The orchestrator picks the idle machine with the lowest usage_count and transitions it to choose_product state. Returns 409 if no idle machine is available.',
        tags: ['Orchestrator'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Machine selected',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Machine selected and moved to choose_product state.'),
                        new OA\Property(property: 'machine', ref: '#/components/schemas/VendingMachine'),
                    ],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'No idle machine available',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'No idle vending machine available.'),
                    ],
                ),
            ),
        ],
    )]
    public function startWork(): JsonResponse
    {
        try {
            $machine = $this->orchestratorService->startWork();

            return response()->json([
                'message' => 'Machine selected and moved to choose_product state.',
                'machine' => $machine,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 409);
        }
    }

    #[OA\Post(
        path: '/api/orchestrator/choose-product',
        summary: 'Purchase a product through a vending machine',
        description: 'Given a machine in choose_product state, a product, a count, and coins (must equal count at 1 coin per item), this endpoint locks the inventory, decrements stock, and moves the machine to processing. A background job returns it to idle after delivery.',
        tags: ['Orchestrator'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['machine_id', 'product_id', 'count', 'coins'],
                properties: [
                    new OA\Property(property: 'machine_id', type: 'integer', example: 1, description: 'ID of the machine in choose_product state (returned by start-work)'),
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'count', type: 'integer', example: 3, description: 'Number of items to purchase'),
                    new OA\Property(property: 'coins', type: 'integer', example: 3, description: 'Number of coins inserted (must equal count)'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product purchased, machine is processing',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Product selected. Machine is now processing.'),
                        new OA\Property(property: 'machine', ref: '#/components/schemas/VendingMachine'),
                        new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Business rule violation or validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Coins must equal the number of products (1 coin per item).'),
                    ],
                ),
            ),
        ],
    )]
    public function chooseProduct(ChooseProductRequest $request): JsonResponse
    {
        try {
            $result = $this->orchestratorService->chooseProduct(
                machineId: $request->validated('machine_id'),
                productId: $request->validated('product_id'),
                count: $request->validated('count'),
                coins: $request->validated('coins'),
            );

            return response()->json([
                'message' => 'Product selected. Machine is now processing.',
                'machine' => $result['machine'],
                'product' => $result['product'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

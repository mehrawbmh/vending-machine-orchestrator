<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductStockRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'List all products',
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of products',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json(Product::all());
    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Add a new product to the inventory',
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'stock'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Cola'),
                    new OA\Property(property: 'stock', type: 'integer', example: 100),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product created',
                content: new OA\JsonContent(ref: '#/components/schemas/Product'),
            ),
            new OA\Response(response: 400, description: 'Product with this name already exists'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json($product, 201);
    }

    #[OA\Patch(
        path: '/api/products/{id}/stock',
        summary: 'Update the stock of an existing product',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stock'],
                properties: [
                    new OA\Property(property: 'stock', type: 'integer', example: 50),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock updated',
                content: new OA\JsonContent(ref: '#/components/schemas/Product'),
            ),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function updateStock(UpdateProductStockRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json($product);
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Delete a product',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Product deleted'),
            new OA\Response(response: 404, description: 'Product not found'),
        ],
    )]
    public function delete(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(null, 204);
    }
}

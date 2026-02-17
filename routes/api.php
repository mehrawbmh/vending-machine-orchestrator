<?php

use App\Http\Controllers\OrchestratorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VendingMachineController;
use Illuminate\Support\Facades\Route;

Route::apiResource('vending-machines', VendingMachineController::class);
Route::post('/vending-machines/{vendingMachine}/reset', [VendingMachineController::class, 'reset']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::patch('/products/{product}/stock', [ProductController::class, 'updateStock']);
Route::delete('/products/{product}', [ProductController::class, 'delete']);


Route::post('/orchestrator/start-work', [OrchestratorController::class, 'startWork']);
Route::post('/orchestrator/choose-product', [OrchestratorController::class, 'chooseProduct']);

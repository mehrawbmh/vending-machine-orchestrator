<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Vending Machine Orchestrator API',
    description: 'RESTful API for managing vending machines and a shared product inventory. Machines operate as state machines coordinated by an orchestrator.',
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Local development')]
abstract class Controller
{
}

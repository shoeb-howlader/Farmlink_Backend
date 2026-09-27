<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class StatusController extends ApiController
{
    /**
     * Get the API v1 health and operational status.
     */
    public function __invoke(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'healthy',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ], 'Farmlink API v1 is operational');
    }
}

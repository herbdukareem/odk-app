<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => config('app.name', 'DataKit Quality Assurance'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}


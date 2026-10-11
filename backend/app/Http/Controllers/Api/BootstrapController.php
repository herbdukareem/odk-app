<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class BootstrapController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'product' => [
                'name' => 'DataKit Quality Assurance',
                'phase' => 'foundation',
            ],
            'roles' => config('odk.roles'),
            'statuses' => config('odk.statuses'),
            'integration' => [
                'nicare_base_url' => config('services.nicare.base_url'),
                'reference_sync_enabled' => (bool) config('services.nicare.reference_sync_enabled'),
            ],
        ]);
    }
}


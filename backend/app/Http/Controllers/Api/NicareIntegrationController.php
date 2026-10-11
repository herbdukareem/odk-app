<?php

namespace App\Http\Controllers\Api;

use App\Models\IntegrationSyncLog;
use App\Models\ReferenceFacility;
use App\Models\ReferenceInsuranceProgramme;
use App\Models\ReferenceLga;
use App\Models\ReferenceWard;
use App\Services\Integration\NicareReferenceSyncService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class NicareIntegrationController
{
    public function status(): JsonResponse
    {
        $lastSync = IntegrationSyncLog::query()
            ->where('provider', 'nicare')
            ->where('sync_type', 'reference_data')
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => 'nicare',
                'base_url' => config('services.nicare.base_url'),
                'configured' => filled(config('services.nicare.base_url')),
                'reference_sync_enabled' => (bool) config('services.nicare.reference_sync_enabled'),
                'counts' => [
                    'facilities' => ReferenceFacility::count(),
                    'lgas' => ReferenceLga::count(),
                    'wards' => ReferenceWard::count(),
                    'insurance_programmes' => ReferenceInsuranceProgramme::count(),
                ],
                'last_sync' => $lastSync,
            ],
        ]);
    }

    public function sync(NicareReferenceSyncService $syncService): JsonResponse
    {
        try {
            $result = $syncService->sync();
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reference data synced successfully.',
            'data' => $result,
        ], 202);
    }
}


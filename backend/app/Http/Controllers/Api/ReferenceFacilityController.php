<?php

namespace App\Http\Controllers\Api;

use App\Models\IntegrationSyncLog;
use App\Models\ReferenceFacility;
use App\Models\ReferenceLga;
use App\Models\ReferenceWard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceFacilityController
{
    public function index(Request $request): JsonResponse
    {
        $facilities = ReferenceFacility::query()
            ->with(['lga:id,source_id,code,name', 'ward:id,source_id,name,reference_lga_id'])
            ->when($request->input('active', '1') !== 'all', function ($query) use ($request): void {
                $query->where('active', $request->boolean('active', true));
            })
            ->when($request->filled('lga_id'), fn ($query) => $query->where('reference_lga_id', $request->integer('lga_id')))
            ->when($request->filled('ward_id'), fn ($query) => $query->where('reference_ward_id', $request->integer('ward_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->input('q'));
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('source_code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => [
                'facilities' => $facilities,
                'stats' => [
                    'total' => ReferenceFacility::count(),
                    'active' => ReferenceFacility::where('active', true)->count(),
                    'lgas' => ReferenceLga::where('active', true)->count(),
                    'wards' => ReferenceWard::where('active', true)->count(),
                ],
                'lgas' => ReferenceLga::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'source_id', 'code', 'name']),
                'wards' => ReferenceWard::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'source_id', 'source_lga_id', 'reference_lga_id', 'name']),
                'last_sync' => IntegrationSyncLog::query()
                    ->where('provider', 'nicare')
                    ->where('sync_type', 'reference_data')
                    ->latest()
                    ->first(),
            ],
        ]);
    }
}

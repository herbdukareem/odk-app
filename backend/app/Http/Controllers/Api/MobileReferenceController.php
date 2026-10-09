<?php

namespace App\Http\Controllers\Api;

use App\Models\ReferenceFacility;
use App\Models\ReferenceInsuranceProgramme;
use App\Models\ReferenceLga;
use App\Models\ReferenceWard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReferenceController
{
    public function index(Request $request): JsonResponse
    {
        $facilities = ReferenceFacility::query()
            ->with(['lga:id,source_id,code,name', 'ward:id,source_id,name,reference_lga_id'])
            ->where('active', true)
            ->when($request->filled('lga'), function ($query) use ($request): void {
                $query->whereHas('lga', fn ($lga) => $lga->where('name', $request->string('lga')));
            })
            ->when($request->filled('ward'), function ($query) use ($request): void {
                $query->whereHas('ward', fn ($ward) => $ward->where('name', $request->string('ward')));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (ReferenceFacility $facility): array => [
                'id' => $facility->source_id,
                'local_id' => $facility->id,
                'code' => $facility->source_code,
                'name' => $facility->name,
                'ownership' => $facility->ownership,
                'type' => $facility->type,
                'level_of_care' => $facility->level_of_care,
                'address' => $facility->address,
                'phone' => $facility->phone,
                'email' => $facility->email,
                'lga' => $facility->lga?->name,
                'lga_id' => $facility->lga?->source_id,
                'ward' => $facility->ward?->name,
                'ward_id' => $facility->ward?->source_id,
                'active' => $facility->active,
                'accreditation_status' => $facility->accreditation_status,
                'updated_at' => $facility->updated_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'facilities' => $facilities,
                'lgas' => ReferenceLga::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['source_id as id', 'code', 'name']),
                'wards' => ReferenceWard::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['source_id as id', 'reference_lga_id', 'source_lga_id', 'name']),
                'insurance_programmes' => ReferenceInsuranceProgramme::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['source_id as id', 'code', 'name', 'type']),
            ],
            'synced_at' => now()->toISOString(),
        ]);
    }
}

<?php

namespace App\Services\Integration;

use App\Models\IntegrationSyncLog;
use App\Models\ReferenceFacility;
use App\Models\ReferenceInsuranceProgramme;
use App\Models\ReferenceLga;
use App\Models\ReferenceWard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class NicareReferenceSyncService
{
    public function __construct(private NicareApiClient $client)
    {
    }

    /**
     * @return array{status: string, counts: array<string, int>, message: string|null}
     */
    public function sync(): array
    {
        $log = IntegrationSyncLog::create([
            'provider' => 'nicare',
            'sync_type' => 'reference_data',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $counts = DB::transaction(function (): array {
                $lgaCount = $this->syncLgas();
                $wardCount = $this->syncWards();
                $programmeCount = $this->syncInsuranceProgrammes();
                $facilityCount = $this->syncFacilities();

                return [
                    'lgas' => $lgaCount,
                    'wards' => $wardCount,
                    'insurance_programmes' => $programmeCount,
                    'facilities' => $facilityCount,
                ];
            });

            $log->update([
                'status' => 'completed',
                'counts' => $counts,
                'message' => 'Reference data synced successfully.',
                'finished_at' => now(),
            ]);

            return ['status' => 'completed', 'counts' => $counts, 'message' => null];
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function syncLgas(): int
    {
        $items = $this->client->getCollection('lgas', ['status' => 1]);
        $now = now();

        foreach ($items as $item) {
            ReferenceLga::updateOrCreate(
                ['source_id' => (int) $item['id']],
                [
                    'code' => $item['code'] ?? null,
                    'name' => (string) ($item['name'] ?? 'Unknown LGA'),
                    'zone' => $item['zone'] ?? null,
                    'active' => $this->isActive($item['status'] ?? 1),
                    'source_payload' => $item,
                    'source_updated_at' => $this->dateValue($item['updated_at'] ?? null),
                    'last_synced_at' => $now,
                ]
            );
        }

        return count($items);
    }

    private function syncWards(): int
    {
        $items = $this->client->getCollection('wards', ['status' => 1]);
        $now = now();

        foreach ($items as $item) {
            $sourceLgaId = (int) data_get($item, 'lga.id', $item['lga_id'] ?? 0) ?: null;
            $lga = $sourceLgaId ? ReferenceLga::where('source_id', $sourceLgaId)->first() : null;

            ReferenceWard::updateOrCreate(
                ['source_id' => (int) $item['id']],
                [
                    'source_lga_id' => $sourceLgaId,
                    'reference_lga_id' => $lga?->id,
                    'name' => (string) ($item['name'] ?? 'Unknown Ward'),
                    'settlement_type' => $item['settlement_type'] ?? null,
                    'active' => $this->isActive($item['status'] ?? 1),
                    'source_payload' => $item,
                    'source_updated_at' => $this->dateValue($item['updated_at'] ?? null),
                    'last_synced_at' => $now,
                ]
            );
        }

        return count($items);
    }

    private function syncInsuranceProgrammes(): int
    {
        $payload = $this->client->getPayload('premium_metadata');
        $items = data_get($payload, 'data.insurance_programmes', data_get($payload, 'data.programmes', []));
        $items = is_array($items) ? array_values($items) : [];
        $now = now();

        foreach ($items as $item) {
            ReferenceInsuranceProgramme::updateOrCreate(
                ['source_id' => (int) $item['id']],
                [
                    'code' => $item['code'] ?? null,
                    'name' => (string) ($item['name'] ?? 'Unknown Programme'),
                    'type' => $item['type'] ?? null,
                    'active' => $this->isActive($item['status'] ?? 1),
                    'source_payload' => $item,
                    'source_updated_at' => $this->dateValue($item['updated_at'] ?? null),
                    'last_synced_at' => $now,
                ]
            );
        }

        return count($items);
    }

    private function syncFacilities(): int
    {
        $items = $this->client->getCollection('facilities', ['status' => 1]);
        $now = now();

        foreach ($items as $item) {
            $sourceLgaId = (int) data_get($item, 'lga.id', $item['lga_id'] ?? 0) ?: null;
            $sourceWardId = (int) data_get($item, 'ward.id', $item['ward_id'] ?? 0) ?: null;
            $lga = $sourceLgaId ? ReferenceLga::where('source_id', $sourceLgaId)->first() : null;
            $ward = $sourceWardId ? ReferenceWard::where('source_id', $sourceWardId)->first() : null;

            ReferenceFacility::updateOrCreate(
                ['source_id' => (int) $item['id']],
                [
                    'source_code' => $item['hcp_code'] ?? $item['code'] ?? null,
                    'name' => (string) ($item['name'] ?? 'Unknown Facility'),
                    'ownership' => $item['ownership'] ?? $item['category'] ?? null,
                    'type' => $item['type'] ?? null,
                    'level_of_care' => $item['level_of_care'] ?? null,
                    'address' => $item['address'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'email' => $item['email'] ?? null,
                    'source_lga_id' => $sourceLgaId,
                    'source_ward_id' => $sourceWardId,
                    'reference_lga_id' => $lga?->id,
                    'reference_ward_id' => $ward?->id,
                    'active' => $this->isActive($item['status'] ?? 1),
                    'accreditation_status' => $item['accreditation_status'] ?? null,
                    'source_payload' => Arr::except($item, ['account_detail']),
                    'source_updated_at' => $this->dateValue($item['updated_at'] ?? null),
                    'last_synced_at' => $now,
                ]
            );
        }

        return count($items);
    }

    private function dateValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function isActive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'active', 'true', 'yes'], true);
    }
}

<?php

use Illuminate\Support\Facades\Artisan;
use App\Services\Integration\NicareReferenceSyncService;

Artisan::command('odk:about', function (): void {
    $this->info('NiCare Quality Assurance ODK backend');
})->purpose('Show the ODK backend name');

Artisan::command('nicare:sync-reference-data', function (NicareReferenceSyncService $syncService): int {
    $summary = $syncService->sync();

    foreach ($summary['counts'] as $key => $count) {
        $this->line(sprintf('%s: %s', str_replace('_', ' ', ucfirst($key)), $count));
    }

    $this->info('NICARE reference data sync completed.');

    return self::SUCCESS;
})->purpose('Sync facility and programme reference data from the main NICARE system');

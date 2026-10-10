<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\AssessmentForm;
use Database\Seeders\NicareQaChecklistSeeder;
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

Artisan::command('odk:ensure-nicare-form {--force : Re-sync the canonical NiCare QA Checklist definition}', function (): int {
    $formExists = AssessmentForm::query()
        ->where('slug', 'nicare-qa-checklist')
        ->exists();

    if ($formExists && !$this->option('force')) {
        $this->info('NiCare QA Checklist already exists; leaving the production form unchanged.');

        return self::SUCCESS;
    }

    $this->call('db:seed', [
        '--class' => NicareQaChecklistSeeder::class,
        '--force' => true,
    ]);

    return self::SUCCESS;
})->purpose('Create the NiCare QA Checklist on a fresh database without overwriting existing edits');

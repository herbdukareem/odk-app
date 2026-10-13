<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\AssessmentForm;
use App\Models\OdkPlan;
use App\Models\OdkWorkspace;
use Database\Seeders\NicareQaChecklistSeeder;
use Database\Seeders\OdkSaasPlanSeeder;
use App\Services\Integration\NicareReferenceSyncService;
use App\Services\Billing\BillingSubscriptionService;

Artisan::command('odk:about', function (): void {
    $this->info('DataKit Quality Assurance ODK backend');
})->purpose('Show the ODK backend name');

Artisan::command('odk:ensure-saas-plans {--sync-pricing : Apply ODK_PROFESSIONAL_PRICE to the Professional plan}', function (): int {
    $this->call('db:seed', [
        '--class' => OdkSaasPlanSeeder::class,
        '--force' => true,
    ]);

    if ($this->option('sync-pricing')) {
        $price = config('billing.professional_price');
        if ($price === null || !is_numeric($price) || (float) $price <= 0) {
            $this->error('Set a positive ODK_PROFESSIONAL_PRICE before syncing pricing.');

            return self::FAILURE;
        }

        OdkPlan::query()->where('code', 'professional')->update(['price_amount' => (float) $price]);
        $this->info('Professional plan pricing synchronized.');
    }

    return self::SUCCESS;
})->purpose('Create or update the DataKit SaaS plans');

Artisan::command('odk:expire-subscriptions', function (BillingSubscriptionService $billing): int {
    $count = $billing->expireDueSubscriptions();
    $this->info("Expired {$count} subscription(s).");

    return self::SUCCESS;
})->purpose('Downgrade paid subscriptions whose billing period has ended');

Artisan::command('nicare:sync-reference-data', function (NicareReferenceSyncService $syncService): int {
    $summary = $syncService->sync();

    foreach ($summary['counts'] as $key => $count) {
        $this->line(sprintf('%s: %s', str_replace('_', ' ', ucfirst($key)), $count));
    }

    $this->info('Reference data sync completed.');

    return self::SUCCESS;
})->purpose('Sync facility and programme reference data from the reference system');

Artisan::command('odk:ensure-nicare-form {--force : Re-sync the canonical DataKit QA Checklist definition}', function (): int {
    $workspace = OdkWorkspace::query()->where('slug', 'datakit')->first();
    if (!$workspace) {
        $this->error('The default DataKit workspace does not exist. Run migrations before seeding the canonical form.');

        return self::FAILURE;
    }

    $form = AssessmentForm::forWorkspace($workspace->id)
        ->where('slug', 'nicare-qa-checklist')
        ->withCount(['sections', 'fields'])
        ->first();
    $formExists = $form !== null;
    $formIsEmpty = $form && ((int) $form->sections_count === 0 || (int) $form->fields_count === 0);

    if ($formExists && !$formIsEmpty && !$this->option('force')) {
        $this->info('DataKit QA Checklist already exists; leaving the production form unchanged.');

        return self::SUCCESS;
    }

    if ($formIsEmpty && !$this->option('force')) {
        $this->info('DataKit QA Checklist is empty; restoring the canonical QA checklist definition.');
    }

    $this->call('db:seed', [
        '--class' => NicareQaChecklistSeeder::class,
        '--force' => true,
    ]);

    return self::SUCCESS;
})->purpose('Create the DataKit QA Checklist on a fresh database without overwriting existing edits');

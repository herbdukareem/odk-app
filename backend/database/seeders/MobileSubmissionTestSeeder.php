<?php

namespace Database\Seeders;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MobileSubmissionTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = OdkUser::where('email', 'amina.yusuf@health.gov.ng')->firstOrFail();
        $form = AssessmentForm::where('slug', 'nicare-qa-checklist')->firstOrFail();
        $version = $form->versions()->latest('version')->firstOrFail();

        $exercise = AssessmentExercise::query()
            ->where('status', AssessmentExercise::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        if (! $exercise) {
            $exercise = AssessmentExercise::create([
                'uuid' => (string) Str::uuid(),
                'assessment_form_id' => $form->id,
                'assessment_form_version_id' => $version->id,
                'name' => 'Mobile Submission Test',
                'code' => 'MOBILE-TEST-'.now()->format('Ymd'),
                'period_type' => AssessmentExercise::PERIOD_CUSTOM,
                'year' => (int) now()->format('Y'),
                'starts_at' => now()->subDay()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'status' => AssessmentExercise::STATUS_ACTIVE,
                'activated_at' => now(),
                'settings' => ['mobile_test' => true],
            ]);
        }

        ReferenceFacility::query()
            ->where('active', true)
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->each(function (ReferenceFacility $facility) use ($exercise, $user): void {
                AssessmentAssignment::updateOrCreate(
                    [
                        'assessment_exercise_id' => $exercise->id,
                        'reference_facility_id' => $facility->id,
                        'assignee_type' => AssessmentAssignment::ASSIGNEE_USER,
                        'odk_user_id' => $user->id,
                    ],
                    [
                        'uuid' => AssessmentAssignment::query()
                            ->where('assessment_exercise_id', $exercise->id)
                            ->where('reference_facility_id', $facility->id)
                            ->where('assignee_type', AssessmentAssignment::ASSIGNEE_USER)
                            ->where('odk_user_id', $user->id)
                            ->value('uuid') ?? (string) Str::uuid(),
                        'odk_team_id' => null,
                        'status' => AssessmentAssignment::STATUS_ASSIGNED,
                        'allow_duplicate_submission' => true,
                        'assigned_at' => now(),
                        'due_at' => now()->addWeeks(2)->toDateString(),
                        'settings' => ['mobile_test' => true],
                    ],
                );
            });
    }
}

<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlineAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_user_can_open_and_submit_an_online_assessment(): void
    {
        $user = $this->createUser('assessor@example.com');
        [$assignment] = $this->createAssignment($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/my/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $assignment->uuid)
            ->assertJsonPath('data.0.can_submit', true);

        $this->getJson('/api/my/assignments/'.$assignment->uuid)
            ->assertOk()
            ->assertJsonPath('data.form.version_data.fields.0.key', 'assessment_date');

        $this->postJson('/api/my/assignments/'.$assignment->uuid.'/submissions', [
            'client_submission_id' => 'online-invalid',
            'status' => 'submitted',
            'answers' => ['assessment_date' => ''],
        ])->assertStatus(422)->assertJsonValidationErrors('answers.assessment_date');

        $this->postJson('/api/my/assignments/'.$assignment->uuid.'/submissions', [
            'client_submission_id' => 'online-valid',
            'status' => 'submitted',
            'assessment_date' => now()->toDateString(),
            'answers' => ['assessment_date' => now()->toDateString()],
        ])->assertCreated()->assertJsonPath('data.source', 'online');

        $this->assertDatabaseHas('assessment_assignments', [
            'id' => $assignment->id,
            'status' => AssessmentAssignment::STATUS_SUBMITTED,
        ]);
    }

    public function test_user_cannot_open_another_users_online_assessment(): void
    {
        $assignedUser = $this->createUser('assigned@example.com');
        $otherUser = $this->createUser('other@example.com');
        [$assignment] = $this->createAssignment($assignedUser);
        Sanctum::actingAs($otherUser);

        $this->getJson('/api/my/assignments/'.$assignment->uuid)->assertForbidden();
        $this->postJson('/api/my/assignments/'.$assignment->uuid.'/submissions', [
            'client_submission_id' => 'unauthorized',
            'status' => 'submitted',
            'answers' => ['assessment_date' => now()->toDateString()],
        ])->assertForbidden();
    }

    private function createUser(string $email): OdkUser
    {
        return OdkUser::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Online Assessor',
            'email' => $email,
            'status' => OdkUser::STATUS_ACTIVE,
        ]);
    }

    private function createAssignment(OdkUser $user): array
    {
        $form = AssessmentForm::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'online-test-form',
            'title' => 'Online test form',
            'status' => AssessmentForm::STATUS_PUBLISHED,
        ]);
        $version = AssessmentFormVersion::create([
            'assessment_form_id' => $form->id,
            'version' => 1,
            'status' => AssessmentFormVersion::STATUS_PUBLISHED,
            'sections' => [['key' => 'details', 'label' => 'Details']],
            'fields' => [[
                'key' => 'assessment_date',
                'label' => 'Assessment date',
                'type' => 'date',
                'section' => 'details',
                'required' => true,
                'rules' => [],
            ]],
            'validation_rules' => [],
            'visibility_rules' => [],
            'scoring_rules' => [],
            'required_evidence_rules' => [],
            'signature_rules' => [],
        ]);
        $form->forceFill(['current_version_id' => $version->id])->save();

        $facility = ReferenceFacility::create([
            'source_id' => 1001,
            'source_code' => 'FAC-1001',
            'name' => 'Online Test Facility',
        ]);
        $exercise = AssessmentExercise::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Online test exercise',
            'code' => 'ONLINE-TEST',
            'period_type' => AssessmentExercise::PERIOD_CUSTOM,
            'year' => (int) now()->year,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'assessment_form_id' => $form->id,
            'assessment_form_version_id' => $version->id,
            'status' => AssessmentExercise::STATUS_ACTIVE,
        ]);
        $assignment = AssessmentAssignment::create([
            'uuid' => (string) Str::uuid(),
            'assessment_exercise_id' => $exercise->id,
            'reference_facility_id' => $facility->id,
            'assignee_type' => AssessmentAssignment::ASSIGNEE_USER,
            'odk_user_id' => $user->id,
            'status' => AssessmentAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        return [$assignment, $exercise, $form, $version, $facility];
    }
}

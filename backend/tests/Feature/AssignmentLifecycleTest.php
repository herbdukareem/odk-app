<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentSubmission;
use App\Models\OdkRole;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Models\ReferenceFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_cancel_assignment_without_submissions(): void
    {
        [$owner, $assignment] = $this->createAssignment();
        Sanctum::actingAs($owner);

        $this->postJson('/api/assignments/'.$assignment->uuid.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', AssessmentAssignment::STATUS_CANCELLED);

        $this->assertDatabaseHas('assessment_assignments', [
            'id' => $assignment->id,
            'status' => AssessmentAssignment::STATUS_CANCELLED,
        ]);
    }

    public function test_workspace_owner_can_delete_assignment_without_submissions(): void
    {
        [$owner, $assignment] = $this->createAssignment();
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/assignments/'.$assignment->uuid)
            ->assertOk();

        $this->assertDatabaseMissing('assessment_assignments', ['id' => $assignment->id]);
    }

    public function test_assignment_with_submission_cannot_be_deleted_but_can_be_archived(): void
    {
        [$owner, $assignment, $exercise, $form, $version, $facility] = $this->createAssignment();
        $submission = AssessmentSubmission::create([
            'uuid' => (string) Str::uuid(),
            'assessment_id' => 'ASSIGNMENT-LIFECYCLE-1',
            'assessment_form_id' => $form->id,
            'assessment_form_version_id' => $version->id,
            'assessment_exercise_id' => $exercise->id,
            'assessment_assignment_id' => $assignment->id,
            'reference_facility_id' => $facility->id,
            'facility_name' => $facility->name,
            'status' => AssessmentSubmission::STATUS_SUBMITTED,
            'source' => 'mobile',
        ]);
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/assignments/'.$assignment->uuid)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This assignment has submissions and must be archived instead of deleted.');

        $this->postJson('/api/assignments/'.$assignment->uuid.'/archive')
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->assertSoftDeleted('assessment_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('assessment_submissions', [
            'id' => $submission->id,
            'assessment_assignment_id' => $assignment->id,
        ]);
    }

    private function createAssignment(): array
    {
        $role = OdkRole::firstOrCreate(
            ['key' => 'member'],
            ['name' => 'Member', 'description' => null, 'permissions' => [], 'sort_order' => 1]
        );
        $owner = OdkUser::create([
            'uuid' => (string) Str::uuid(),
            'odk_role_id' => $role->id,
            'name' => 'Assignment Owner',
            'email' => 'assignment-owner@example.com',
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
        $workspace = OdkWorkspace::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'assignment-lifecycle',
            'name' => 'Assignment lifecycle workspace',
            'type' => OdkWorkspace::TYPE_ORGANIZATION,
            'status' => OdkWorkspace::STATUS_ACTIVE,
            'owner_user_id' => $owner->id,
        ]);
        $workspace->users()->attach($owner->id, [
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $form = AssessmentForm::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'assignment-lifecycle-form',
            'title' => 'Assignment lifecycle form',
            'status' => AssessmentForm::STATUS_PUBLISHED,
        ]);
        $version = AssessmentFormVersion::create([
            'assessment_form_id' => $form->id,
            'version' => 1,
            'status' => AssessmentFormVersion::STATUS_PUBLISHED,
            'sections' => [['key' => 'details', 'label' => 'Details']],
            'fields' => [],
            'validation_rules' => [],
            'visibility_rules' => [],
            'scoring_rules' => [],
            'required_evidence_rules' => [],
            'signature_rules' => [],
        ]);
        $form->forceFill(['current_version_id' => $version->id])->save();
        $facility = ReferenceFacility::create([
            'source_id' => 9901,
            'source_code' => 'ASSIGNMENT-FACILITY',
            'name' => 'Assignment lifecycle facility',
        ]);
        $exercise = AssessmentExercise::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Assignment lifecycle exercise',
            'code' => 'ASSIGNMENT-LIFECYCLE',
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
            'odk_user_id' => $owner->id,
            'status' => AssessmentAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        return [$owner, $assignment, $exercise, $form, $version, $facility];
    }
}

<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\OdkRole;
use App\Models\OdkTarget;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Models\ReferenceFacility;
use App\Support\OdkWorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiExerciseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private OdkUser $owner;

    private OdkWorkspace $workspace;

    private AssessmentFormVersion $version;

    private array $exercises;

    private array $facilities;

    protected function setUp(): void
    {
        parent::setUp();
        $role = OdkRole::create(['key' => 'member', 'name' => 'Member', 'permissions' => [], 'sort_order' => 1]);
        $this->owner = OdkUser::create([
            'uuid' => (string) Str::uuid(), 'odk_role_id' => $role->id,
            'name' => 'Batch owner', 'email' => 'batch@example.com', 'status' => 'active',
            'password' => 'password',
        ]);
        $this->workspace = OdkWorkspace::create([
            'uuid' => (string) Str::uuid(), 'slug' => 'batch', 'name' => 'Batch workspace',
            'type' => OdkWorkspace::TYPE_ORGANIZATION, 'status' => 'active', 'owner_user_id' => $this->owner->id,
        ]);
        $this->workspace->users()->attach($this->owner->id, ['role_key' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        Sanctum::actingAs($this->owner);
        app(OdkWorkspaceContext::class)->set($this->workspace);
        $form = AssessmentForm::create(['uuid' => (string) Str::uuid(), 'slug' => 'batch', 'title' => 'Batch form', 'status' => 'published']);
        $this->version = AssessmentFormVersion::create([
            'assessment_form_id' => $form->id, 'version' => 1, 'status' => 'published',
            'sections' => [], 'fields' => [], 'validation_rules' => [], 'visibility_rules' => [],
            'scoring_rules' => [], 'required_evidence_rules' => [], 'signature_rules' => [],
        ]);
        $this->exercises = [$this->exercise('October', '2026-10-31'), $this->exercise('November', '2026-11-30')];
        $this->facilities = [
            ReferenceFacility::create(['source_id' => 9901, 'name' => 'North clinic', 'source_code' => 'NORTH']),
            ReferenceFacility::create(['source_id' => 9902, 'name' => 'South clinic', 'source_code' => 'SOUTH']),
        ];
    }

    public function test_batch_creates_each_exercise_and_facility_pair_with_individual_due_dates(): void
    {
        $this->postJson('/api/assignments', $this->payload())
            ->assertCreated()->assertJsonCount(4, 'data');
        $this->assertDatabaseCount('assessment_assignments', 4);
        foreach ($this->exercises as $exercise) {
            foreach ($this->facilities as $facility) {
                $assignment = AssessmentAssignment::where('assessment_exercise_id', $exercise->id)
                    ->where('reference_facility_id', $facility->id)->sole();
                $this->assertEquals($this->workspace->id, $assignment->workspace_id);
                $this->assertEquals($this->owner->id, $assignment->odk_user_id);
                $this->assertEquals($exercise->ends_at->toDateString(), $assignment->due_at->toDateString());
            }
        }
    }

    public function test_shared_due_date_applies_to_entire_batch_and_scalar_clients_still_work(): void
    {
        $this->postJson('/api/assignments', [...$this->payload(), 'due_at' => '2026-12-15'])
            ->assertCreated()->assertJsonCount(4, 'data');
        foreach (AssessmentAssignment::all() as $assignment) {
            $this->assertEquals('2026-12-15', $assignment->due_at->toDateString());
        }

        $payload = $this->payload();
        unset($payload['assessment_exercise_ids']);
        $this->postJson('/api/assignments', [...$payload, 'assessment_exercise_id' => $this->exercises[0]->id, 'allow_duplicate_submission' => true])
            ->assertCreated()->assertJsonCount(2, 'data');
    }

    public function test_duplicate_on_later_exercise_rejects_whole_batch(): void
    {
        $this->postJson('/api/assignments', [...$this->payload(), 'assessment_exercise_ids' => [$this->exercises[1]->id]])
            ->assertCreated();
        $this->postJson('/api/assignments', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('facility_ids');
        $this->assertDatabaseCount('assessment_assignments', 2);
        $this->assertDatabaseMissing('assessment_assignments', ['assessment_exercise_id' => $this->exercises[0]->id]);
        $this->postJson('/api/assignments', [...$this->payload(), 'allow_duplicate_submission' => true])
            ->assertCreated()->assertJsonCount(4, 'data');
    }

    public function test_batch_cannot_include_another_workspaces_exercise(): void
    {
        $other = OdkWorkspace::create([
            'uuid' => (string) Str::uuid(), 'slug' => 'other', 'name' => 'Other',
            'type' => OdkWorkspace::TYPE_ORGANIZATION, 'status' => 'active', 'owner_user_id' => $this->owner->id,
        ]);
        $this->exercises[1]->update(['workspace_id' => $other->id]);
        $this->postJson('/api/assignments', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('assessment_exercise_ids');
        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    public function test_closed_or_incompatible_exercise_rejects_entire_batch(): void
    {
        $this->exercises[1]->update(['status' => 'closed']);
        $this->postJson('/api/assignments', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('assessment_assignments', 0);
        $this->exercises[1]->update(['status' => 'active', 'target_type' => 'site']);
        $this->postJson('/api/assignments', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    public function test_invalid_exercise_selections_are_rejected_without_writes(): void
    {
        foreach ([
            ['assessment_exercise_ids' => []],
            ['assessment_exercise_ids' => [$this->exercises[0]->id, $this->exercises[0]->id]],
            ['assessment_exercise_ids' => [99999]],
            ['assessment_exercise_id' => $this->exercises[0]->id],
            ['assessment_exercise_ids' => ['not-an-id']],
        ] as $invalid) {
            $this->postJson('/api/assignments', [...$this->payload(), ...$invalid])->assertUnprocessable();
            $this->assertDatabaseCount('assessment_assignments', 0);
        }
    }

    public function test_batches_support_non_facility_targets_and_no_target_exercises(): void
    {
        foreach ($this->exercises as $exercise) {
            $exercise->update(['target_type' => 'site']);
        }
        $target = OdkTarget::create(['uuid' => (string) Str::uuid(), 'type' => 'site', 'name' => 'Field site', 'active' => true]);
        $this->postJson('/api/assignments', [...$this->payload(), 'target_type' => 'site', 'target_ids' => [$target->id], 'facility_ids' => []])
            ->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.odk_target_id', $target->id);

        foreach ($this->exercises as $exercise) {
            $exercise->update(['target_type' => 'no_target']);
        }
        $this->postJson('/api/assignments', [...$this->payload(), 'target_type' => 'no_target', 'facility_ids' => []])
            ->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.reference_facility_id', null);
    }

    public function test_batch_rejects_inaccessible_targets_and_assignees(): void
    {
        foreach ($this->exercises as $exercise) {
            $exercise->update(['target_type' => 'site']);
        }
        $target = OdkTarget::create(['uuid' => (string) Str::uuid(), 'type' => 'site', 'name' => 'Inactive site', 'active' => false]);
        $this->postJson('/api/assignments', [...$this->payload(), 'target_type' => 'site', 'target_ids' => [$target->id]])
            ->assertUnprocessable();
        $this->owner->update(['status' => 'inactive']);
        $this->postJson('/api/assignments', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    public function test_member_cannot_create_batches_and_options_include_target_types(): void
    {
        $this->getJson('/api/assignments')->assertOk()->assertJsonPath('data.options.exercises.0.target_type', 'facility');
        $this->workspace->users()->updateExistingPivot($this->owner->id, ['role_key' => 'member']);
        $this->postJson('/api/assignments', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    public function test_non_member_users_are_not_available_as_batch_assignees(): void
    {
        $outsider = OdkUser::create([
            'uuid' => (string) Str::uuid(), 'odk_role_id' => $this->owner->odk_role_id,
            'name' => 'Other workspace assessor', 'email' => 'outsider@example.com', 'status' => 'active',
        ]);
        $response = $this->getJson('/api/assignments')->assertOk();
        $this->assertNotContains($outsider->id, array_column($response->json('data.options.users'), 'id'));
        $this->postJson('/api/assignments', [...$this->payload(), 'assignee_id' => $outsider->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    private function exercise(string $name, string $endsAt): AssessmentExercise
    {
        return AssessmentExercise::create([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'code' => strtoupper($name),
            'period_type' => 'custom', 'year' => 2026, 'starts_at' => '2026-10-01', 'ends_at' => $endsAt,
            'assessment_form_id' => $this->version->assessment_form_id, 'assessment_form_version_id' => $this->version->id,
            'status' => 'active', 'target_type' => 'facility',
        ]);
    }

    private function payload(): array
    {
        return [
            'assessment_exercise_ids' => array_map(fn ($exercise) => $exercise->id, $this->exercises),
            'facility_ids' => array_map(fn ($facility) => $facility->id, $this->facilities),
            'assignee_type' => 'user', 'assignee_id' => $this->owner->id,
            'target_type' => 'facility', 'due_at' => null,
        ];
    }
}

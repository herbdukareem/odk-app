<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_mobile_submission_falls_back_when_cached_version_id_is_from_another_environment(): void
    {
        $user = $this->createUser('mobile-assessor@example.com');
        [$assignment, $exercise, $form, $version, $facility] = $this->createAssignment($user);

        $response = $this->postJson('/api/mobile/submissions', [
            'client_submission_id' => 'mobile-stale-version',
            'form_slug' => $form->slug,
            'form_version_id' => 999999,
            'form_version' => $version->version,
            'assessment_exercise_id' => $exercise->id,
            'assessment_assignment_id' => $assignment->id,
            'facility_id' => $facility->source_id,
            'facility_name' => $facility->name,
            'assessment_date' => now()->toDateString(),
            'source' => 'mobile',
            'status' => 'submitted',
            'answers' => ['assessment_date' => now()->toDateString()],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.uuid', fn ($uuid) => is_string($uuid) && Str::isUuid($uuid));

        $this->assertDatabaseHas('assessment_submissions', [
            'client_submission_id' => 'mobile-stale-version',
            'assessment_form_version_id' => $version->id,
        ]);
    }

    public function test_mobile_evidence_is_stored_on_the_configured_s3_disk(): void
    {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $user = $this->createUser('mobile-evidence@example.com');
        [$assignment, $exercise, $form, $version, $facility] = $this->createAssignment($user);

        $submission = $this->postJson('/api/mobile/submissions', [
            'client_submission_id' => 'mobile-evidence',
            'form_slug' => $form->slug,
            'form_version_id' => $version->id,
            'assessment_exercise_id' => $exercise->id,
            'assessment_assignment_id' => $assignment->id,
            'facility_id' => $facility->source_id,
            'facility_name' => $facility->name,
            'assessment_date' => now()->toDateString(),
            'source' => 'mobile',
            'status' => 'submitted',
            'answers' => ['assessment_date' => now()->toDateString()],
        ])->assertCreated();

        $uuid = (string) $submission->json('data.uuid');

        $this->post('/api/mobile/submissions/'.$uuid.'/attachments', [
            'file' => UploadedFile::fake()->image('evidence.jpg'),
            'field_key' => 'facility_photos',
            'type' => 'photo',
            'original_name' => 'evidence.jpg',
            'captured_at' => now()->toISOString(),
        ])->assertCreated();

        $attachment = \App\Models\AssessmentSubmissionAttachment::query()
            ->whereHas('submission', fn ($query) => $query->where('uuid', $uuid))
            ->firstOrFail();

        Storage::disk('s3')->assertExists($attachment->path);
        $this->assertSame('s3', config('filesystems.default'));
        $this->assertDatabaseHas('assessment_submission_attachments', [
            'assessment_submission_id' => $attachment->assessment_submission_id,
            'path' => $attachment->path,
            'original_name' => 'evidence.jpg',
        ]);
    }

    public function test_mobile_evidence_retry_with_the_same_client_id_does_not_create_a_duplicate(): void
    {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $user = $this->createUser('mobile-evidence-retry@example.com');
        [$assignment, $exercise, $form, $version, $facility] = $this->createAssignment($user);

        $submission = $this->postJson('/api/mobile/submissions', [
            'client_submission_id' => 'mobile-evidence-retry',
            'form_slug' => $form->slug,
            'form_version_id' => $version->id,
            'assessment_exercise_id' => $exercise->id,
            'assessment_assignment_id' => $assignment->id,
            'facility_id' => $facility->source_id,
            'facility_name' => $facility->name,
            'assessment_date' => now()->toDateString(),
            'source' => 'mobile',
            'status' => 'submitted',
            'answers' => ['assessment_date' => now()->toDateString()],
        ])->assertCreated();

        $uuid = (string) $submission->json('data.uuid');
        $payload = [
            'field_key' => 'facility_photos',
            'client_attachment_id' => 'evidence-stable-id-1',
            'type' => 'photo',
            'original_name' => 'evidence.jpg',
            'captured_at' => now()->toISOString(),
        ];

        $this->post('/api/mobile/submissions/'.$uuid.'/attachments', [
            ...$payload,
            'file' => UploadedFile::fake()->image('evidence.jpg'),
        ])->assertCreated();

        $this->post('/api/mobile/submissions/'.$uuid.'/attachments', [
            ...$payload,
            'file' => UploadedFile::fake()->image('evidence-retry.jpg'),
        ])->assertCreated();

        $this->assertDatabaseCount('assessment_submission_attachments', 1);
        $this->assertDatabaseHas('assessment_submission_attachments', [
            'client_attachment_id' => 'evidence-stable-id-1',
            'original_name' => 'evidence.jpg',
        ]);
    }

    public function test_returned_submission_is_available_in_the_assessors_mobile_assignments(): void
    {
        $user = $this->createUser('returned-assessment@example.com');
        [$assignment, $exercise, $form, $version, $facility] = $this->createAssignment($user);

        $submission = $this->postJson('/api/mobile/submissions', [
            'client_submission_id' => 'mobile-returned',
            'form_slug' => $form->slug,
            'form_version_id' => $version->id,
            'assessment_exercise_id' => $exercise->id,
            'assessment_assignment_id' => $assignment->id,
            'facility_id' => $facility->source_id,
            'facility_name' => $facility->name,
            'assessment_date' => now()->toDateString(),
            'source' => 'mobile',
            'status' => 'submitted',
            'answers' => ['assessment_date' => now()->toDateString()],
        ])->assertCreated();

        $uuid = (string) $submission->json('data.uuid');

        $this->postJson('/api/submissions/'.$uuid.'/review', [
            'action' => 'return',
            'note' => 'Please correct the assessment details.',
        ])->assertOk();

        $this->assertDatabaseHas('assessment_assignments', [
            'id' => $assignment->id,
            'status' => AssessmentAssignment::STATUS_IN_PROGRESS,
        ]);

        $this->getJson('/api/mobile/assignments?assessor_id='.$user->id.'&assessor_email='.urlencode($user->email))
            ->assertOk()
            ->assertJsonPath('data.assignments.0.submission.uuid', $uuid)
            ->assertJsonPath('data.assignments.0.submission.status', 'returned')
            ->assertJsonPath('data.assignments.0.submission.review_note', 'Please correct the assessment details.');
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

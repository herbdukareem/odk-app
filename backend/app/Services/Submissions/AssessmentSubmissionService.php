<?php

namespace App\Services\Submissions;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionAttachment;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use App\Services\Forms\FormRuleTester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AssessmentSubmissionService
{
    public function __construct(private readonly FormRuleTester $ruleTester)
    {
    }

    public function submit(array $payload, bool $requireValid = false): AssessmentSubmission
    {
        return DB::transaction(function () use ($payload, $requireValid): AssessmentSubmission {
            $form = $this->resolveForm($payload);
            $version = $this->resolveVersion($form, $payload);
            $answers = $payload['answers'] ?? [];
            $facility = $this->resolveFacility($payload);
            $assignment = $this->resolveAssignment($payload);
            if ($assignment?->facility) {
                $facility = $assignment->facility;
            }
            $answers = $this->normalizeFacilityAnswers($answers, $facility);
            $validation = $version
                ? $this->ruleTester->testVersion($version, $answers)
                : $this->ruleTester->test($form, $answers);
            $exercise = $this->resolveExercise($payload, $assignment);
            $gps = $payload['gps'] ?? $answers['gps_coordinates'] ?? [];
            $status = $payload['status'] ?? AssessmentSubmission::STATUS_SUBMITTED;

            if ($requireValid && $status !== AssessmentSubmission::STATUS_DRAFT && !$validation['valid']) {
                throw ValidationException::withMessages(
                    collect($validation['errors'])
                        ->mapWithKeys(fn (array $error): array => [
                            'answers.'.($error['field'] ?? 'form') => $error['message'] ?? 'This answer is invalid.',
                        ])
                        ->all()
                );
            }

            $submission = !empty($payload['client_submission_id'])
                ? AssessmentSubmission::firstOrNew(['client_submission_id' => $payload['client_submission_id']])
                : new AssessmentSubmission;

            $this->ensureExerciseAcceptsSubmission($exercise, $status);
            $this->ensureSubmissionAllowed($submission, $exercise, $assignment, $facility, $status);

            $submission->fill([
                'uuid' => $submission->uuid ?? ($payload['uuid'] ?? (string) Str::uuid()),
                'assessment_id' => $submission->assessment_id ?? ($payload['assessment_id'] ?? $this->nextAssessmentId()),
                'client_submission_id' => $payload['client_submission_id'] ?? $submission->client_submission_id,
                'assessment_form_id' => $form->id,
                'assessment_form_version_id' => $version?->id,
                'assessment_exercise_id' => $exercise?->id,
                'assessment_assignment_id' => $assignment?->id,
                'reference_facility_id' => $facility?->id,
                'facility_external_id' => $payload['facility']['id'] ?? $payload['facility_id'] ?? $facility?->source_id,
                'facility_name' => $payload['facility']['name'] ?? $payload['facility_name'] ?? $facility?->name ?? 'Unknown facility',
                'facility_code' => $payload['facility']['code'] ?? $facility?->source_code,
                'facility_type' => $payload['facility']['type'] ?? $facility?->type,
                'lga_name' => $payload['facility']['lga'] ?? $payload['lga'] ?? $facility?->lga?->name,
                'ward_name' => $payload['facility']['ward'] ?? $payload['ward'] ?? $facility?->ward?->name,
                'assessor_id' => $payload['assessor']['id'] ?? $payload['assessor_id'] ?? null,
                'assessor_name' => $payload['assessor']['name'] ?? $payload['assessor_name'] ?? null,
                'assessment_date' => $payload['assessment_date'] ?? now()->toDateString(),
                'source' => $payload['source'] ?? 'mobile',
                'status' => $status,
                'sync_status' => $status === AssessmentSubmission::STATUS_DRAFT ? 'local_draft' : 'received',
                'score_total' => $validation['score']['total'] ?? null,
                'score_details' => $validation['score'] ?? null,
                'gps_latitude' => $gps['latitude'] ?? null,
                'gps_longitude' => $gps['longitude'] ?? null,
                'gps_accuracy_meters' => $gps['accuracy_meters'] ?? $gps['accuracy'] ?? null,
                'facility_snapshot' => $payload['facility'] ?? null,
                'assessor_snapshot' => $payload['assessor'] ?? null,
                'device_snapshot' => $payload['device'] ?? null,
                'answers_snapshot' => $answers,
                'validation_result' => $validation,
                'started_at' => $payload['started_at'] ?? null,
                'submitted_at' => $status === AssessmentSubmission::STATUS_DRAFT ? null : ($payload['submitted_at'] ?? now()),
            ])->save();

            $submission->answers()->delete();
            foreach ($answers as $fieldKey => $value) {
                $scoreRow = collect($validation['score']['fields'] ?? [])->firstWhere('field', $fieldKey);
                $field = $this->fieldDefinition($version, $fieldKey);

                $submission->answers()->create([
                    'field_key' => $fieldKey,
                    'section_key' => $field['section'] ?? null,
                    'value' => ['value' => $value],
                    'score' => $scoreRow['score'] ?? null,
                    'requires_review' => $this->answerRequiresReview($field, $value),
                    'metadata' => ['field_type' => $field['type'] ?? null],
                ]);
            }

            if (array_key_exists('attachments', $payload)) {
                $submission->attachments()->delete();
                foreach ($payload['attachments'] ?? [] as $attachment) {
                    $submission->attachments()->create([
                        'field_key' => $attachment['field_key'] ?? null,
                        'type' => $attachment['type'] ?? 'document',
                        'path' => $attachment['path'] ?? null,
                        'original_name' => $attachment['original_name'] ?? $attachment['name'] ?? null,
                        'mime_type' => $attachment['mime_type'] ?? null,
                        'size_bytes' => $attachment['size_bytes'] ?? null,
                        'metadata' => $attachment['metadata'] ?? null,
                        'captured_at' => $attachment['captured_at'] ?? null,
                    ]);
                }
            }

            $submission->syncEvents()->create([
                'client_submission_id' => $submission->client_submission_id,
                'direction' => 'upload',
                'status' => 'received',
                'reference_id' => $submission->assessment_id,
                'message' => 'Submission received from '.$submission->source.'.',
                'payload' => ['valid' => $validation['valid'], 'error_count' => count($validation['errors'] ?? [])],
                'attempted_at' => now(),
            ]);

            $this->markAssignmentProgress($assignment, $status);

            return $submission->fresh(['form', 'formVersion', 'answers', 'attachments', 'reviews', 'syncEvents']);
        });
    }

    public function submitOnline(OdkUser $user, array $payload): AssessmentSubmission
    {
        $assignment = $this->resolveAssignment($payload);

        abort_unless($assignment && $this->userCanAccessAssignment($user, $assignment), 403, 'This assessment is not assigned to your account or team.');

        $payload['source'] = 'online';
        $payload['assessor'] = [
            'id' => $user->staff_code ?: (string) $user->id,
            'local_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
        $payload['assessor_id'] = $user->staff_code ?: (string) $user->id;
        $payload['assessor_name'] = $user->name;

        return $this->submit($payload, true);
    }

    public function userCanAccessAssignment(OdkUser $user, AssessmentAssignment $assignment): bool
    {
        if ((int) $assignment->odk_user_id === (int) $user->id) {
            return true;
        }

        $teamIds = $user->teams()
            ->where('odk_teams.status', 'active')
            ->pluck('odk_teams.id')
            ->merge($user->leadingTeams()->where('status', 'active')->pluck('id'))
            ->unique();

        return $assignment->odk_team_id !== null && $teamIds->contains((int) $assignment->odk_team_id);
    }

    public function review(AssessmentSubmission $submission, array $payload): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission, $payload): AssessmentSubmission {
            $from = $submission->status;
            $to = match ($payload['action']) {
                'start_review' => AssessmentSubmission::STATUS_UNDER_REVIEW,
                'return' => AssessmentSubmission::STATUS_RETURNED,
                'approve' => AssessmentSubmission::STATUS_APPROVED,
                default => AssessmentSubmission::STATUS_REVIEWED,
            };

            $submission->forceFill([
                'status' => $to,
                'reviewed_at' => now(),
                'reviewed_by' => $payload['reviewer_name'] ?? $payload['reviewer_id'] ?? 'Portal reviewer',
                'review_note' => $payload['note'] ?? null,
            ])->save();

            if ($to === AssessmentSubmission::STATUS_RETURNED && $submission->assignment) {
                $submission->assignment->forceFill([
                    'status' => AssessmentAssignment::STATUS_IN_PROGRESS,
                    'submitted_at' => null,
                    'completed_at' => null,
                ])->save();
            }

            $submission->reviews()->create([
                'action' => $payload['action'],
                'from_status' => $from,
                'to_status' => $to,
                'reviewer_id' => $payload['reviewer_id'] ?? null,
                'reviewer_name' => $payload['reviewer_name'] ?? null,
                'note' => $payload['note'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            return $submission->fresh(['form', 'formVersion', 'answers', 'attachments', 'reviews', 'syncEvents']);
        });
    }

    public function attachEvidence(AssessmentSubmission $submission, UploadedFile $file, array $payload): AssessmentSubmissionAttachment
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::uuid().'.'.$extension;
        $originalName = $payload['original_name'] ?? $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $type = $payload['type'] ?? $this->inferAttachmentType($mimeType);
        $directory = 'odk-evidence/submissions/'.$submission->uuid;
        $disk = (string) config('filesystems.default', 's3');
        $storage = Storage::disk($disk);
        $path = $storage->putFileAs($directory, $file, $filename);

        if (!$path) {
            throw new RuntimeException('Evidence file could not be stored.');
        }

        try {
            // Read metadata from the configured object store. The PHP upload temp file
            // may already be unavailable by this point on the production host.
            $sizeBytes = null;
            try {
                $sizeBytes = $storage->size($path);
            } catch (Throwable) {
                // Some S3-compatible credentials allow writes but not HEAD metadata.
            }

            $attachment = $submission->attachments()->create([
                'field_key' => $payload['field_key'] ?? null,
                'type' => $type,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'metadata' => $payload['metadata'] ?? null,
                'captured_at' => $payload['captured_at'] ?? null,
            ]);

            $submission->syncEvents()->create([
                'client_submission_id' => $submission->client_submission_id,
                'direction' => 'upload',
                'status' => 'received',
                'reference_id' => $submission->assessment_id,
                'message' => 'Evidence file uploaded.',
                'payload' => [
                    'attachment_id' => $attachment->id,
                    'field_key' => $attachment->field_key,
                    'type' => $attachment->type,
                    'disk' => $disk,
                ],
                'attempted_at' => now(),
            ]);

            return $attachment->fresh('submission');
        } catch (Throwable $exception) {
            // Avoid orphaning an object when the database record cannot be created.
            try {
                $storage->delete($path);
            } catch (Throwable) {
                // Preserve the original exception for the API response/log.
            }

            throw $exception;
        }
    }

    private function resolveForm(array $payload): AssessmentForm
    {
        if (!empty($payload['form_slug'])) {
            return AssessmentForm::where('slug', $payload['form_slug'])->firstOrFail();
        }

        if (!empty($payload['assessment_form_id'])) {
            return AssessmentForm::findOrFail($payload['assessment_form_id']);
        }

        return AssessmentForm::where('slug', 'nicare-qa-checklist')->firstOrFail();
    }

    private function normalizeFacilityAnswers(array $answers, ?ReferenceFacility $facility): array
    {
        if ($facility === null) {
            return $answers;
        }

        if ($facility->lga?->source_id !== null) {
            $answers['lga_id'] = $facility->lga->source_id;
        }

        if ($facility->ward?->source_id !== null) {
            $answers['ward_id'] = $facility->ward->source_id;
        }

        return $answers;
    }

    private function resolveVersion(AssessmentForm $form, array $payload): ?AssessmentFormVersion
    {
        if (!empty($payload['form_version_id'])) {
            $version = AssessmentFormVersion::where('assessment_form_id', $form->id)
                ->find($payload['form_version_id']);

            if ($version) {
                return $version;
            }
        }

        if (!empty($payload['form_version'])) {
            $version = $form->versions()->where('version', $payload['form_version'])->first();

            if ($version) {
                return $version;
            }
        }

        return $form->currentVersion;
    }

    private function resolveFacility(array $payload): ?ReferenceFacility
    {
        $facilityId = $payload['facility']['id'] ?? $payload['facility_id'] ?? null;
        if ($facilityId === null) {
            return null;
        }

        return ReferenceFacility::query()
            ->with(['lga', 'ward'])
            ->where('source_code', $facilityId)
            ->when(is_numeric($facilityId), fn ($query) => $query->orWhere('source_id', $facilityId)->orWhere('id', $facilityId))
            ->first();
    }

    private function resolveAssignment(array $payload): ?AssessmentAssignment
    {
        $assignmentUuid = $payload['assignment_uuid'] ?? null;
        if ($assignmentUuid) {
            return AssessmentAssignment::with(['exercise', 'facility.lga', 'facility.ward'])->where('uuid', $assignmentUuid)->firstOrFail();
        }

        $assignmentId = $payload['assessment_assignment_id'] ?? $payload['assignment_id'] ?? null;
        if ($assignmentId) {
            return AssessmentAssignment::with(['exercise', 'facility.lga', 'facility.ward'])->findOrFail($assignmentId);
        }

        return null;
    }

    private function resolveExercise(array $payload, ?AssessmentAssignment $assignment): ?AssessmentExercise
    {
        if ($assignment?->exercise) {
            return $assignment->exercise;
        }

        $exerciseUuid = $payload['assessment_exercise_uuid'] ?? $payload['exercise_uuid'] ?? null;
        if ($exerciseUuid) {
            return AssessmentExercise::where('uuid', $exerciseUuid)->firstOrFail();
        }

        $exerciseId = $payload['assessment_exercise_id'] ?? $payload['exercise_id'] ?? null;
        if ($exerciseId) {
            return AssessmentExercise::findOrFail($exerciseId);
        }

        return null;
    }

    private function ensureSubmissionAllowed(
        AssessmentSubmission $submission,
        ?AssessmentExercise $exercise,
        ?AssessmentAssignment $assignment,
        ?ReferenceFacility $facility,
        string $status
    ): void {
        if ($exercise === null || $facility === null || $status === AssessmentSubmission::STATUS_DRAFT) {
            return;
        }

        if ($assignment?->allow_duplicate_submission) {
            return;
        }

        $duplicate = AssessmentSubmission::query()
            ->where('assessment_exercise_id', $exercise->id)
            ->where('reference_facility_id', $facility->id)
            ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)
            ->when($submission->exists, fn ($query) => $query->whereKeyNot($submission->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'facility_id' => ['This facility already has a submitted assessment for the selected exercise.'],
            ]);
        }
    }

    private function ensureExerciseAcceptsSubmission(?AssessmentExercise $exercise, string $status): void
    {
        if ($exercise === null || $status === AssessmentSubmission::STATUS_DRAFT) {
            return;
        }

        if ($exercise->status !== AssessmentExercise::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'assessment_exercise_id' => ['This assessment exercise is not currently accepting submissions.'],
            ]);
        }

        $today = now()->toDateString();
        $startsAt = $exercise->starts_at?->toDateString();
        $endsAt = $exercise->ends_at?->toDateString();

        if (($startsAt !== null && $today < $startsAt) || ($endsAt !== null && $today > $endsAt)) {
            throw ValidationException::withMessages([
                'assessment_exercise_id' => ['This submission is outside the assessment exercise window.'],
            ]);
        }
    }

    private function markAssignmentProgress(?AssessmentAssignment $assignment, string $submissionStatus): void
    {
        if ($assignment === null) {
            return;
        }

        if ($submissionStatus === AssessmentSubmission::STATUS_DRAFT) {
            if ($assignment->status === AssessmentAssignment::STATUS_ASSIGNED) {
                $assignment->forceFill([
                    'status' => AssessmentAssignment::STATUS_IN_PROGRESS,
                    'started_at' => $assignment->started_at ?? now(),
                ])->save();
            }

            return;
        }

        $assignment->forceFill([
            'status' => AssessmentAssignment::STATUS_SUBMITTED,
            'started_at' => $assignment->started_at ?? now(),
            'submitted_at' => $assignment->submitted_at ?? now(),
        ])->save();
    }

    private function fieldDefinition(?AssessmentFormVersion $version, string $fieldKey): array
    {
        return collect($version?->fields ?? [])->firstWhere('key', $fieldKey) ?? [];
    }

    private function answerRequiresReview(array $field, mixed $value): bool
    {
        if (is_array($value)) {
            $value = $value['answer'] ?? $value['value'] ?? null;
        }

        foreach ($field['options'] ?? [] as $option) {
            if (($option['value'] ?? null) === $value) {
                return (bool) ($option['needs_review'] ?? false);
            }
        }

        return false;
    }

    private function inferAttachmentType(?string $mime): string
    {
        $mime ??= '';

        if (str_starts_with($mime, 'image/')) {
            return 'photo';
        }

        if ($mime === 'application/pdf') {
            return 'document';
        }

        return 'file';
    }

    private function nextAssessmentId(): string
    {
        return 'NIC-ASS-'.now()->format('Ymd').'-'.str_pad((string) (AssessmentSubmission::query()->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}

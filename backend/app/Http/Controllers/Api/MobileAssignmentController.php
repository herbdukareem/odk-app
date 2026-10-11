<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentSubmission;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAssignmentController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'assessor' => null,
                    'assignments' => [],
                    'exercises' => [],
                    'forms' => [],
                    'facilities' => [],
                ],
                'synced_at' => now()->toISOString(),
            ]);
        }

        $teamIds = $user->teams()->pluck('odk_teams.id')
            ->merge($user->leadingTeams()->pluck('id'))
            ->unique()
            ->values();

        $assignments = AssessmentAssignment::query()
            ->with([
                'exercise.form',
                'exercise.formVersion',
                'facility.lga:id,source_id,code,name',
                'facility.ward:id,source_id,name,reference_lga_id',
                'user:id,name,email,staff_code',
                'team:id,name',
                'submissions' => fn ($query) => $query
                    ->where('status', AssessmentSubmission::STATUS_RETURNED)
                    ->latest('updated_at')
                    ->with('attachments'),
            ])
            ->whereIn('status', [
                AssessmentAssignment::STATUS_ASSIGNED,
                AssessmentAssignment::STATUS_IN_PROGRESS,
            ])
            ->whereHas('exercise', function ($query): void {
                $query->whereIn('status', [
                    AssessmentExercise::STATUS_SCHEDULED,
                    AssessmentExercise::STATUS_ACTIVE,
                ]);
            })
            ->where(function ($query) use ($user, $teamIds): void {
                $query->where('odk_user_id', $user->id);

                if ($teamIds->isNotEmpty()) {
                    $query->orWhereIn('odk_team_id', $teamIds);
                }
            })
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get();

        $forms = $assignments
            ->map(fn (AssessmentAssignment $assignment) => $assignment->exercise)
            ->filter()
            ->unique(fn (AssessmentExercise $exercise) => $exercise->assessment_form_version_id)
            ->map(fn (AssessmentExercise $exercise) => $this->formPayload($exercise->form, $exercise->formVersion))
            ->filter()
            ->values();

        $exercises = $assignments
            ->map(fn (AssessmentAssignment $assignment) => $assignment->exercise)
            ->filter()
            ->unique('id')
            ->map(fn (AssessmentExercise $exercise) => $this->exercisePayload($exercise))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'assessor' => [
                    'id' => $user->staff_code ?: (string) $user->id,
                    'local_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'assignments' => $assignments->map(fn (AssessmentAssignment $assignment) => $this->assignmentPayload($assignment))->values(),
                'exercises' => $exercises,
                'forms' => $forms,
                'facilities' => $assignments
                    ->map(fn (AssessmentAssignment $assignment) => $this->facilityPayload($assignment))
                    ->filter()
                    ->values(),
            ],
            'synced_at' => now()->toISOString(),
        ]);
    }

    private function resolveUser(Request $request): ?OdkUser
    {
        $email = trim((string) $request->query('assessor_email', ''));
        $assessorId = trim((string) $request->query('assessor_id', ''));

        if ($email === '' && $assessorId === '') {
            return null;
        }

        return OdkUser::query()
            ->where('status', OdkUser::STATUS_ACTIVE)
            ->where(function ($query) use ($email, $assessorId): void {
                if ($email !== '') {
                    $query->orWhere('email', $email);
                }

                if ($assessorId !== '') {
                    $query->orWhere('staff_code', $assessorId);

                    if (is_numeric($assessorId)) {
                        $query->orWhere('id', (int) $assessorId);
                    }
                }
            })
            ->first();
    }

    private function assignmentPayload(AssessmentAssignment $assignment): array
    {
        $submission = $assignment->submissions->first();

        return [
            'id' => $assignment->id,
            'uuid' => $assignment->uuid,
            'status' => $assignment->status,
            'assignee_type' => $assignment->assignee_type,
            'assignee_name' => $assignment->user?->name ?? $assignment->team?->name,
            'assigned_at' => $assignment->assigned_at?->toISOString(),
            'due_at' => $assignment->due_at?->toDateString(),
            'allow_duplicate_submission' => $assignment->allow_duplicate_submission,
            'exercise' => $assignment->exercise ? $this->exercisePayload($assignment->exercise) : null,
            'facility' => $assignment->facility ? $this->facilityPayload($assignment) : null,
            'submission' => $submission ? [
                'uuid' => $submission->uuid,
                'client_submission_id' => $submission->client_submission_id,
                'assessment_id' => $submission->assessment_id,
                'status' => $submission->status,
                'answers' => $submission->answers_snapshot,
                'validation_result' => $submission->validation_result,
                'review_note' => $submission->review_note,
                'reviewed_at' => $submission->reviewed_at?->toISOString(),
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'attachments' => $submission->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'field_key' => $attachment->field_key,
                    'type' => $attachment->type,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'download_url' => $attachment->download_url,
                ])->values()->all(),
            ] : null,
        ];
    }

    private function exercisePayload(AssessmentExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'uuid' => $exercise->uuid,
            'name' => $exercise->name,
            'code' => $exercise->code,
            'period_type' => $exercise->period_type,
            'year' => $exercise->year,
            'quarter' => $exercise->quarter,
            'month' => $exercise->month,
            'starts_at' => $exercise->starts_at?->toDateString(),
            'ends_at' => $exercise->ends_at?->toDateString(),
            'status' => $exercise->status,
            'form_slug' => $exercise->form?->slug,
            'form_version_id' => $exercise->formVersion?->id,
            'form_version' => $exercise->formVersion?->version,
        ];
    }

    private function formPayload(?AssessmentForm $form, ?AssessmentFormVersion $version): ?array
    {
        if ($form === null || $version === null) {
            return null;
        }

        return [
            'id' => $form->id,
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'requires_scoring' => $version->requires_scoring,
            'scoring_max_score' => $version->scoring_max_score,
            'current_version' => [
                'id' => $version->id,
                'version' => $version->version,
                'metadata_schema' => $version->metadata_schema,
                'requires_scoring' => $version->requires_scoring,
                'scoring_max_score' => $version->scoring_max_score,
                'sections' => $version->sections,
                'fields' => collect($version->fields ?? [])->reject(fn (array $field) => ($field['type'] ?? null) === 'signature')->values()->all(),
                'validation_rules' => $version->validation_rules,
                'visibility_rules' => $version->visibility_rules,
                'scoring_rules' => $version->scoring_rules,
                'required_evidence_rules' => $version->required_evidence_rules,
                'signature_rules' => [],
            ],
        ];
    }

    private function facilityPayload(AssessmentAssignment $assignment): ?array
    {
        $facility = $assignment->facility;
        $exercise = $assignment->exercise;

        if ($facility === null) {
            return null;
        }

        return [
            'id' => $facility->source_id,
            'local_id' => $facility->id,
            'code' => $facility->source_code,
            'name' => $facility->name,
            'ownership' => $facility->ownership,
            'type' => $facility->type,
            'level_of_care' => $facility->level_of_care,
            'address' => $facility->address,
            'phone' => $facility->phone,
            'email' => $facility->email,
            'lga' => $facility->lga?->name,
            'lga_id' => $facility->lga?->source_id,
            'ward' => $facility->ward?->name,
            'ward_id' => $facility->ward?->source_id,
            'active' => $facility->active,
            'accreditation_status' => $facility->accreditation_status,
            'updated_at' => $facility->updated_at?->toISOString(),
            'assignment_id' => $assignment->id,
            'assignment_uuid' => $assignment->uuid,
            'assignment_status' => $assignment->status,
            'assignment_due_at' => $assignment->due_at?->toDateString(),
            'allow_duplicate_submission' => $assignment->allow_duplicate_submission,
            'exercise_id' => $exercise?->id,
            'exercise_uuid' => $exercise?->uuid,
            'exercise_name' => $exercise?->name,
            'exercise_code' => $exercise?->code,
            'exercise_status' => $exercise?->status,
            'exercise_starts_at' => $exercise?->starts_at?->toDateString(),
            'exercise_ends_at' => $exercise?->ends_at?->toDateString(),
            'form_slug' => $exercise?->form?->slug,
            'form_version_id' => $exercise?->formVersion?->id,
        ];
    }
}

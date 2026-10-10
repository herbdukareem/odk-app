<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentSubmission;
use App\Services\Submissions\AssessmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineAssessmentController
{
    public function index(Request $request, AssessmentSubmissionService $submissions): JsonResponse
    {
        $user = $request->user();
        $teamIds = $this->teamIds($user);

        $assignments = AssessmentAssignment::query()
            ->with([
                'exercise:id,uuid,assessment_form_id,assessment_form_version_id,name,code,status,starts_at,ends_at',
                'exercise.form:id,slug,title,description',
                'exercise.formVersion:id,assessment_form_id,version,status,requires_scoring,scoring_max_score',
                'facility:id,source_id,source_code,name,reference_lga_id,reference_ward_id',
                'facility.lga:id,name',
                'facility.ward:id,name',
                'submissions' => fn ($query) => $query->latest('updated_at'),
            ])
            ->whereIn('status', [
                AssessmentAssignment::STATUS_ASSIGNED,
                AssessmentAssignment::STATUS_IN_PROGRESS,
                AssessmentAssignment::STATUS_SUBMITTED,
                AssessmentAssignment::STATUS_REVIEWED,
            ])
            ->where(function ($query) use ($user, $teamIds): void {
                $query->where('odk_user_id', $user->id);

                if ($teamIds->isNotEmpty()) {
                    $query->orWhereIn('odk_team_id', $teamIds);
                }
            })
            ->whereHas('exercise', function ($query): void {
                $query->whereIn('status', [
                    AssessmentExercise::STATUS_SCHEDULED,
                    AssessmentExercise::STATUS_ACTIVE,
                    AssessmentExercise::STATUS_CLOSED,
                ]);
            })
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->input('q'));
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereHas('facility', fn ($facilityQuery) => $facilityQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('source_code', 'like', "%{$search}%"))
                        ->orWhereHas('exercise', fn ($exerciseQuery) => $exerciseQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("CASE status WHEN 'assigned' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'submitted' THEN 3 WHEN 'reviewed' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignments
                ->map(fn (AssessmentAssignment $assignment): array => $this->assignmentPayload($assignment, $submissions->userCanAccessAssignment($user, $assignment)))
                ->values(),
        ]);
    }

    public function show(Request $request, AssessmentAssignment $assignment, AssessmentSubmissionService $submissions): JsonResponse
    {
        abort_unless($submissions->userCanAccessAssignment($request->user(), $assignment), 403, 'This assessment is not assigned to your account or team.');

        $assignment->load([
            'exercise.form:id,slug,title,description',
            'exercise.formVersion',
            'facility.lga:id,name',
            'facility.ward:id,name',
            'submissions' => fn ($query) => $query->latest('updated_at'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->assignmentPayload($assignment, true, true),
        ]);
    }

    public function store(Request $request, AssessmentAssignment $assignment, AssessmentSubmissionService $submissions): JsonResponse
    {
        abort_unless($submissions->userCanAccessAssignment($request->user(), $assignment), 403, 'This assessment is not assigned to your account or team.');

        $payload = $request->validate([
            'client_submission_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,submitted'],
            'assessment_date' => ['nullable', 'date'],
            'answers' => ['required', 'array'],
            'gps' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'submitted_at' => ['nullable', 'date'],
        ]);

        $assignment->load(['exercise.form', 'exercise.formVersion']);
        abort_unless($assignment->exercise?->form && $assignment->exercise?->formVersion, 422, 'The assigned form version is unavailable.');
        abort_unless(
            in_array($assignment->status, [AssessmentAssignment::STATUS_ASSIGNED, AssessmentAssignment::STATUS_IN_PROGRESS], true),
            422,
            'This assignment is no longer open for submission.'
        );
        abort_unless(
            $assignment->exercise->status === AssessmentExercise::STATUS_ACTIVE,
            422,
            'This assessment exercise is not currently accepting submissions.'
        );

        $facility = $assignment->facility()->with(['lga', 'ward'])->first();
        abort_unless($facility, 422, 'The assigned facility is unavailable.');

        $payload['answers'] = array_replace($payload['answers'], [
            'facility_id' => $facility->source_id,
            'facility_name' => $facility->name,
            'lga_id' => $facility->lga?->source_id,
            'ward_id' => $facility->ward?->source_id,
        ]);
        $payload['facility_id'] = $facility->source_id;
        $payload['facility'] = [
            'id' => $facility->source_id,
            'local_id' => $facility->id,
            'code' => $facility->source_code,
            'name' => $facility->name,
            'type' => $facility->type,
            'lga_id' => $facility->lga?->source_id,
            'lga' => $facility->lga?->name,
            'ward_id' => $facility->ward?->source_id,
            'ward' => $facility->ward?->name,
        ];

        $payload += [
            'assignment_uuid' => $assignment->uuid,
            'assessment_assignment_id' => $assignment->id,
            'assessment_exercise_id' => $assignment->assessment_exercise_id,
            'assessment_exercise_uuid' => $assignment->exercise->uuid,
            'form_slug' => $assignment->exercise->form->slug,
            'form_version_id' => $assignment->exercise->formVersion->id,
            'facility_id' => $facility->source_id,
        ];

        return response()->json([
            'success' => true,
            'data' => $submissions->submitOnline($request->user(), $payload),
        ], 201);
    }

    public function uploadAttachment(Request $request, AssessmentSubmission $submission, AssessmentSubmissionService $submissions): JsonResponse
    {
        $submission->load('assignment');
        abort_unless($submission->assignment && $submissions->userCanAccessAssignment($request->user(), $submission->assignment), 403, 'You cannot modify this submission.');

        $payload = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,webp,svg'],
            'field_key' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
            'captured_at' => ['nullable', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $submissions->attachEvidence($submission, $request->file('file'), $payload),
        ], 201);
    }

    private function teamIds($user)
    {
        return $user->teams()
            ->where('odk_teams.status', 'active')
            ->pluck('odk_teams.id')
            ->merge($user->leadingTeams()->where('status', 'active')->pluck('id'))
            ->unique()
            ->values();
    }

    private function assignmentPayload(AssessmentAssignment $assignment, bool $canAccess, bool $includeForm = false): array
    {
        $exercise = $assignment->exercise;
        $form = $exercise?->form;
        $version = $exercise?->formVersion;
        $latestSubmission = $assignment->submissions->first();
        $canSubmit = $canAccess
            && in_array($assignment->status, [AssessmentAssignment::STATUS_ASSIGNED, AssessmentAssignment::STATUS_IN_PROGRESS], true)
            && $exercise?->status === AssessmentExercise::STATUS_ACTIVE;

        $payload = [
            'id' => $assignment->id,
            'uuid' => $assignment->uuid,
            'status' => $assignment->status,
            'can_submit' => $canSubmit,
            'allow_duplicate_submission' => $assignment->allow_duplicate_submission,
            'assigned_at' => $assignment->assigned_at?->toISOString(),
            'due_at' => $assignment->due_at?->toDateString(),
            'exercise' => $exercise ? [
                'id' => $exercise->id,
                'uuid' => $exercise->uuid,
                'name' => $exercise->name,
                'code' => $exercise->code,
                'status' => $exercise->status,
                'starts_at' => $exercise->starts_at?->toDateString(),
                'ends_at' => $exercise->ends_at?->toDateString(),
            ] : null,
            'facility' => $assignment->facility ? [
                'id' => $assignment->facility->source_id,
                'local_id' => $assignment->facility->id,
                'code' => $assignment->facility->source_code,
                'name' => $assignment->facility->name,
                'lga_id' => $assignment->facility->lga?->source_id,
                'lga' => $assignment->facility->lga?->name,
                'ward_id' => $assignment->facility->ward?->source_id,
                'ward' => $assignment->facility->ward?->name,
            ] : null,
            'form' => $form ? [
                'slug' => $form->slug,
                'title' => $form->title,
                'description' => $form->description,
                'version' => $version?->version,
            ] : null,
            'submission' => $latestSubmission ? [
                'uuid' => $latestSubmission->uuid,
                'status' => $latestSubmission->status,
                'answers' => $latestSubmission->answers_snapshot,
                'validation_result' => $latestSubmission->validation_result,
                'submitted_at' => $latestSubmission->submitted_at?->toISOString(),
                'attachments' => $latestSubmission->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'field_key' => $attachment->field_key,
                    'type' => $attachment->type,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'download_url' => $attachment->download_url,
                ])->values()->all(),
            ] : null,
        ];

        if ($includeForm && $version) {
            $payload['form']['version_data'] = [
                'id' => $version->id,
                'version' => $version->version,
                'sections' => $version->sections,
                'fields' => $version->fields,
                'validation_rules' => $version->validation_rules,
                'visibility_rules' => $version->visibility_rules,
                'scoring_rules' => $version->scoring_rules,
                'required_evidence_rules' => $version->required_evidence_rules,
                'signature_rules' => $version->signature_rules,
            ];
        }

        return $payload;
    }
}

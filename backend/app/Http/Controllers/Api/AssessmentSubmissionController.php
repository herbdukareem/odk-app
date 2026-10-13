<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionAttachment;
use App\Services\Submissions\AssessmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentSubmissionController
{
    public function index(Request $request): JsonResponse
    {
        $submissions = AssessmentSubmission::query()
            ->with([
                'form:id,title,slug,requires_scoring,scoring_max_score',
                'formVersion:id,version,requires_scoring,scoring_max_score',
                'exercise:id,uuid,name,code,status,starts_at,ends_at',
                'assignment:id,uuid,status,assignee_type,odk_user_id,odk_team_id',
                'attachments:id,assessment_submission_id,type,field_key,original_name,mime_type,size_bytes,captured_at,path',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('exercise_id'), fn ($query) => $query->where('assessment_exercise_id', $request->integer('exercise_id')))
            ->when($request->filled('facility'), fn ($query) => $query->where('facility_name', 'like', '%'.$request->string('facility').'%'))
            ->latest('submitted_at')
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json(['success' => true, 'data' => $submissions]);
    }

    public function show(AssessmentSubmission $submission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $submission->load([
                'form',
                'formVersion',
                'exercise',
                'assignment.user:id,name,email',
                'assignment.team:id,name',
                'answers' => fn ($query) => $query->orderBy('section_key')->orderBy('field_key'),
                'attachments.submission',
                'reviews',
                'syncEvents',
            ]),
        ]);
    }

    public function store(Request $request, AssessmentSubmissionService $submissions): JsonResponse
    {
        $payload = $request->validate([
            'client_submission_id' => ['nullable', 'string', 'max:255'],
            'assessment_id' => ['nullable', 'string', 'max:255'],
            'form_slug' => ['nullable', 'string', 'max:255'],
            'form_version' => ['nullable'],
            'form_version_id' => ['nullable', 'integer'],
            'assessment_exercise_id' => ['nullable', 'integer'],
            'assessment_exercise_uuid' => ['nullable', 'string', 'max:255'],
            'exercise_id' => ['nullable', 'integer'],
            'exercise_uuid' => ['nullable', 'string', 'max:255'],
            'assessment_assignment_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'assignment_uuid' => ['nullable', 'string', 'max:255'],
            'facility_id' => ['nullable'],
            'facility_name' => ['nullable', 'string', 'max:255'],
            'facility' => ['nullable', 'array'],
            'lga' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'assessor_id' => ['nullable', 'string', 'max:255'],
            'assessor_name' => ['nullable', 'string', 'max:255'],
            'assessor' => ['nullable', 'array'],
            'assessment_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'answers' => ['required', 'array'],
            'gps' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'device' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'submitted_at' => ['nullable', 'date'],
        ]);

        $user = $request->user('sanctum');
        $submission = $user instanceof \App\Models\OdkUser
            ? $submissions->submitMobile($user, $payload)
            : $submissions->submit($payload);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'uuid' => $submission->uuid,
            ],
        ], 201);
    }

    public function review(Request $request, AssessmentSubmission $submission, AssessmentSubmissionService $submissions): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'in:start_review,return,review,approve'],
            'note' => ['nullable', 'string'],
            'reviewer_id' => ['nullable', 'string', 'max:255'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $submissions->review($submission, $payload),
        ]);
    }

    public function uploadAttachment(Request $request, AssessmentSubmission $submission, AssessmentSubmissionService $submissions): JsonResponse
    {
        $user = $request->user('sanctum');
        if ($user instanceof \App\Models\OdkUser) {
            $submission->load('assignment');
            abort_unless($submission->assignment && $submissions->userCanAccessAssignment($user, $submission->assignment), 403, 'You cannot modify this submission.');
        }

        $payload = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,webp,svg,heic,heif'],
            'field_key' => ['nullable', 'string', 'max:255'],
            'client_attachment_id' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'captured_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $submissions->attachEvidence($submission, $request->file('file'), $payload),
        ], 201);
    }

    public function downloadAttachment(AssessmentSubmission $submission, AssessmentSubmissionAttachment $attachment): StreamedResponse
    {
        abort_unless((int) $attachment->assessment_submission_id === (int) $submission->id, 404);
        abort_unless($attachment->path, 404);

        $disk = (string) config('filesystems.default', 'local');
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        return Storage::disk($disk)->download($attachment->path, $attachment->original_name ?: basename($attachment->path), [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }
}

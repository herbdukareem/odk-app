<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExerciseReportController
{
    public function index(Request $request): JsonResponse
    {
        $exercise = $this->selectedExercise($request);

        return response()->json([
            'success' => true,
            'data' => [
                'exercises' => $this->exerciseOptions(),
                'selected_exercise' => $exercise ? $this->exercisePayload($exercise) : null,
                'summary' => $exercise ? $this->summary($exercise) : $this->emptySummary(),
                'assessor_progress' => $exercise ? $this->assessorProgress($exercise) : [],
                'team_progress' => $exercise ? $this->teamProgress($exercise) : [],
                'facility_completion' => $exercise ? $this->facilityCompletion($exercise) : [],
                'review_queue' => $exercise ? $this->reviewQueue($exercise) : [],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $exercise = $this->selectedExercise($request);
        abort_if($exercise === null, 404, 'Assessment exercise not found.');

        $type = (string) $request->query('type', 'submissions');
        $rows = match ($type) {
            'facility_completion' => $this->facilityCompletion($exercise),
            'assessor_progress' => $this->assessorProgress($exercise),
            'team_progress' => $this->teamProgress($exercise),
            default => $this->submissionExportRows($exercise),
        };

        $filename = strtolower($exercise->code ?: 'exercise').'-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            $first = $rows[0] ?? [];
            if ($first) {
                fputcsv($handle, array_keys($first));
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function selectedExercise(Request $request): ?AssessmentExercise
    {
        $query = AssessmentExercise::query()
            ->with(['form:id,title,slug', 'formVersion:id,version,status'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if ($request->filled('exercise_id')) {
            return $query->whereKey($request->integer('exercise_id'))->first();
        }

        return $query->whereIn('status', [
            AssessmentExercise::STATUS_ACTIVE,
            AssessmentExercise::STATUS_SCHEDULED,
            AssessmentExercise::STATUS_CLOSED,
        ])->first();
    }

    private function exerciseOptions(): array
    {
        return AssessmentExercise::query()
            ->with(['form:id,title'])
            ->withCount(['assignments', 'submissions'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssessmentExercise $exercise): array => [
                'id' => $exercise->id,
                'uuid' => $exercise->uuid,
                'name' => $exercise->name,
                'code' => $exercise->code,
                'status' => $exercise->status,
                'starts_at' => $exercise->starts_at?->toDateString(),
                'ends_at' => $exercise->ends_at?->toDateString(),
                'form_title' => $exercise->form?->title,
                'assignments_count' => $exercise->assignments_count,
                'submissions_count' => $exercise->submissions_count,
            ])
            ->all();
    }

    private function exercisePayload(AssessmentExercise $exercise): array
    {
        $exercise->loadMissing('form:id,title');
        $exercise->loadCount(['assignments', 'submissions']);

        return [
            'id' => $exercise->id,
            'uuid' => $exercise->uuid,
            'name' => $exercise->name,
            'code' => $exercise->code,
            'status' => $exercise->status,
            'starts_at' => $exercise->starts_at?->toDateString(),
            'ends_at' => $exercise->ends_at?->toDateString(),
            'form_title' => $exercise->form?->title,
            'assignments_count' => $exercise->assignments_count,
            'submissions_count' => $exercise->submissions_count,
        ];
    }

    private function summary(AssessmentExercise $exercise): array
    {
        $assignments = AssessmentAssignment::where('assessment_exercise_id', $exercise->id);
        $submissions = AssessmentSubmission::where('assessment_exercise_id', $exercise->id)
            ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT);

        $totalAssignments = (clone $assignments)->count();
        $submittedAssignments = (clone $assignments)->whereIn('status', [
            AssessmentAssignment::STATUS_SUBMITTED,
            AssessmentAssignment::STATUS_REVIEWED,
        ])->count();
        $reviewedAssignments = (clone $assignments)->where('status', AssessmentAssignment::STATUS_REVIEWED)->count();
        $approvedSubmissions = (clone $submissions)->where('status', AssessmentSubmission::STATUS_APPROVED)->count();

        return [
            'total_assignments' => $totalAssignments,
            'assigned' => (clone $assignments)->where('status', AssessmentAssignment::STATUS_ASSIGNED)->count(),
            'in_progress' => (clone $assignments)->where('status', AssessmentAssignment::STATUS_IN_PROGRESS)->count(),
            'submitted_assignments' => $submittedAssignments,
            'reviewed_assignments' => $reviewedAssignments,
            'pending_facilities' => max($totalAssignments - $submittedAssignments, 0),
            'completion_rate' => $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 1) : 0,
            'review_rate' => $submittedAssignments > 0 ? round(($reviewedAssignments / $submittedAssignments) * 100, 1) : 0,
            'submissions' => (clone $submissions)->count(),
            'awaiting_review' => (clone $submissions)->whereIn('status', [
                AssessmentSubmission::STATUS_SUBMITTED,
                AssessmentSubmission::STATUS_UNDER_REVIEW,
            ])->count(),
            'returned' => (clone $submissions)->where('status', AssessmentSubmission::STATUS_RETURNED)->count(),
            'approved' => $approvedSubmissions,
            'average_score' => round((float) ((clone $submissions)->avg('score_total') ?? 0), 1),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_assignments' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'submitted_assignments' => 0,
            'reviewed_assignments' => 0,
            'pending_facilities' => 0,
            'completion_rate' => 0,
            'review_rate' => 0,
            'submissions' => 0,
            'awaiting_review' => 0,
            'returned' => 0,
            'approved' => 0,
            'average_score' => 0,
        ];
    }

    private function assessorProgress(AssessmentExercise $exercise): array
    {
        return AssessmentAssignment::query()
            ->with(['user:id,name,email'])
            ->withCount(['submissions' => fn ($query) => $query->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)])
            ->where('assessment_exercise_id', $exercise->id)
            ->where('assignee_type', AssessmentAssignment::ASSIGNEE_USER)
            ->get()
            ->groupBy('odk_user_id')
            ->map(function ($items): array {
                $first = $items->first();
                $total = $items->count();
                $submitted = $items->whereIn('status', [
                    AssessmentAssignment::STATUS_SUBMITTED,
                    AssessmentAssignment::STATUS_REVIEWED,
                ])->count();

                return [
                    'id' => $first->odk_user_id,
                    'name' => $first->user?->name ?? 'Unassigned assessor',
                    'email' => $first->user?->email,
                    'assignments' => $total,
                    'submitted' => $submitted,
                    'reviewed' => $items->where('status', AssessmentAssignment::STATUS_REVIEWED)->count(),
                    'pending' => max($total - $submitted, 0),
                    'completion_rate' => $total > 0 ? round(($submitted / $total) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function teamProgress(AssessmentExercise $exercise): array
    {
        return AssessmentAssignment::query()
            ->with(['team:id,name'])
            ->where('assessment_exercise_id', $exercise->id)
            ->where('assignee_type', AssessmentAssignment::ASSIGNEE_TEAM)
            ->get()
            ->groupBy('odk_team_id')
            ->map(function ($items): array {
                $first = $items->first();
                $total = $items->count();
                $submitted = $items->whereIn('status', [
                    AssessmentAssignment::STATUS_SUBMITTED,
                    AssessmentAssignment::STATUS_REVIEWED,
                ])->count();

                return [
                    'id' => $first->odk_team_id,
                    'name' => $first->team?->name ?? 'Unassigned team',
                    'assignments' => $total,
                    'submitted' => $submitted,
                    'reviewed' => $items->where('status', AssessmentAssignment::STATUS_REVIEWED)->count(),
                    'pending' => max($total - $submitted, 0),
                    'completion_rate' => $total > 0 ? round(($submitted / $total) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function facilityCompletion(AssessmentExercise $exercise): array
    {
        return AssessmentAssignment::query()
            ->with([
                'facility:id,source_code,name,reference_lga_id,reference_ward_id',
                'facility.lga:id,name',
                'facility.ward:id,name',
                'user:id,name',
                'team:id,name',
                'submissions' => fn ($query) => $query
                    ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)
                    ->latest('submitted_at')
                    ->latest(),
            ])
            ->where('assessment_exercise_id', $exercise->id)
            ->orderBy('status')
            ->get()
            ->map(function (AssessmentAssignment $assignment): array {
                $latest = $assignment->submissions->first();

                return [
                    'assignment_id' => $assignment->id,
                    'facility_name' => $assignment->facility?->name ?? 'Unknown facility',
                    'facility_code' => $assignment->facility?->source_code,
                    'lga' => $assignment->facility?->lga?->name,
                    'ward' => $assignment->facility?->ward?->name,
                    'assignee_type' => $assignment->assignee_type,
                    'assignee_name' => $assignment->assignee_type === AssessmentAssignment::ASSIGNEE_TEAM
                        ? ($assignment->team?->name ?? 'Unknown team')
                        : ($assignment->user?->name ?? 'Unknown assessor'),
                    'assignment_status' => $assignment->status,
                    'submitted_at' => $latest?->submitted_at?->toISOString(),
                    'submission_status' => $latest?->status,
                    'score_total' => $latest?->score_total,
                    'reviewed_at' => $latest?->reviewed_at?->toISOString(),
                ];
            })
            ->all();
    }

    private function reviewQueue(AssessmentExercise $exercise): array
    {
        return AssessmentSubmission::query()
            ->with(['form:id,title,slug', 'formVersion:id,version'])
            ->where('assessment_exercise_id', $exercise->id)
            ->whereIn('status', [
                AssessmentSubmission::STATUS_SUBMITTED,
                AssessmentSubmission::STATUS_UNDER_REVIEW,
                AssessmentSubmission::STATUS_RETURNED,
            ])
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (AssessmentSubmission $submission): array => [
                'uuid' => $submission->uuid,
                'assessment_id' => $submission->assessment_id,
                'facility_name' => $submission->facility_name,
                'assessor_name' => $submission->assessor_name,
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'status' => $submission->status,
                'score_total' => $submission->score_total,
                'form_title' => $submission->form?->title,
            ])
            ->all();
    }

    private function submissionExportRows(AssessmentExercise $exercise): array
    {
        return AssessmentSubmission::query()
            ->with(['assignment.user:id,name', 'assignment.team:id,name'])
            ->where('assessment_exercise_id', $exercise->id)
            ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)
            ->latest('submitted_at')
            ->get()
            ->map(fn (AssessmentSubmission $submission): array => [
                'assessment_id' => $submission->assessment_id,
                'facility_name' => $submission->facility_name,
                'facility_code' => $submission->facility_code,
                'lga' => $submission->lga_name,
                'ward' => $submission->ward_name,
                'assessor_name' => $submission->assessor_name,
                'assignment_assignee' => $submission->assignment?->assignee_type === AssessmentAssignment::ASSIGNEE_TEAM
                    ? $submission->assignment?->team?->name
                    : $submission->assignment?->user?->name,
                'status' => $submission->status,
                'score_total' => $submission->score_total,
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'reviewed_at' => $submission->reviewed_at?->toISOString(),
            ])
            ->all();
    }
}

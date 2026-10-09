<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\AssessmentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = $this->dateRange((string) $request->query('range', 'last_30_days'));
        $exercise = $this->selectedExercise($request);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => [
                    'key' => (string) $request->query('range', 'last_30_days'),
                    'label' => $range['label'],
                    'date_from' => $range['from'],
                    'date_to' => $range['to'],
                ],
                'current_exercise' => $exercise ? $this->exercisePayload($exercise) : null,
                'metrics' => $this->metrics($exercise, $range['from'], $range['to']),
                'chart' => $this->chart($exercise, $range['from'], $range['to']),
                'recent_submissions' => $this->recentSubmissions($exercise),
                'recent_assignments' => $this->recentAssignments($exercise),
                'exercise_options' => $this->exerciseOptions(),
            ],
        ]);
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

        return $query->where('status', AssessmentExercise::STATUS_ACTIVE)->first()
            ?? AssessmentExercise::query()
                ->with(['form:id,title,slug', 'formVersion:id,version,status'])
                ->where('status', AssessmentExercise::STATUS_SCHEDULED)
                ->orderBy('starts_at')
                ->first()
            ?? AssessmentExercise::query()
                ->with(['form:id,title,slug', 'formVersion:id,version,status'])
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->first();
    }

    private function metrics(?AssessmentExercise $exercise, string $from, string $to): array
    {
        $assignmentQuery = AssessmentAssignment::query();
        $submissionQuery = AssessmentSubmission::query()->where('status', '!=', AssessmentSubmission::STATUS_DRAFT);

        if ($exercise) {
            $assignmentQuery->where('assessment_exercise_id', $exercise->id);
            $submissionQuery->where('assessment_exercise_id', $exercise->id);
        }

        $rangeSubmissionQuery = (clone $submissionQuery)
            ->whereBetween(DB::raw('DATE(submitted_at)'), [$from, $to]);

        $totalAssignments = (clone $assignmentQuery)->count();
        $submittedAssignments = (clone $assignmentQuery)->whereIn('status', [
            AssessmentAssignment::STATUS_SUBMITTED,
            AssessmentAssignment::STATUS_REVIEWED,
        ])->count();
        $reviewedAssignments = (clone $assignmentQuery)->where('status', AssessmentAssignment::STATUS_REVIEWED)->count();
        $awaitingReview = (clone $submissionQuery)->whereIn('status', [
            AssessmentSubmission::STATUS_SUBMITTED,
            AssessmentSubmission::STATUS_UNDER_REVIEW,
        ])->count();

        return [
            'total_exercises' => AssessmentExercise::query()->count(),
            'active_exercises' => AssessmentExercise::query()->where('status', AssessmentExercise::STATUS_ACTIVE)->count(),
            'total_assignments' => $totalAssignments,
            'assigned' => (clone $assignmentQuery)->where('status', AssessmentAssignment::STATUS_ASSIGNED)->count(),
            'in_progress' => (clone $assignmentQuery)->where('status', AssessmentAssignment::STATUS_IN_PROGRESS)->count(),
            'submitted_assignments' => $submittedAssignments,
            'reviewed_assignments' => $reviewedAssignments,
            'pending_facilities' => max($totalAssignments - $submittedAssignments, 0),
            'completion_rate' => $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 1) : 0,
            'review_rate' => $submittedAssignments > 0 ? round(($reviewedAssignments / $submittedAssignments) * 100, 1) : 0,
            'submissions_in_range' => (clone $rangeSubmissionQuery)->count(),
            'awaiting_review' => $awaitingReview,
            'returned' => (clone $submissionQuery)->where('status', AssessmentSubmission::STATUS_RETURNED)->count(),
            'approved' => (clone $submissionQuery)->where('status', AssessmentSubmission::STATUS_APPROVED)->count(),
            'average_score' => round((float) ((clone $submissionQuery)->avg('score_total') ?? 0), 1),
        ];
    }

    private function chart(?AssessmentExercise $exercise, string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $days = max($start->diffInDays($end), 0);
        $step = $days > 45 ? 7 : 1;

        $rows = AssessmentSubmission::query()
            ->selectRaw('DATE(submitted_at) as submitted_date, COUNT(*) as total')
            ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)
            ->whereBetween(DB::raw('DATE(submitted_at)'), [$from, $to])
            ->when($exercise, fn ($query) => $query->where('assessment_exercise_id', $exercise->id))
            ->groupBy('submitted_date')
            ->pluck('total', 'submitted_date');

        $points = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $bucketEnd = $cursor->copy()->addDays($step - 1)->min($end);
            $count = 0;
            $day = $cursor->copy();

            while ($day <= $bucketEnd) {
                $count += (int) ($rows[$day->toDateString()] ?? 0);
                $day->addDay();
            }

            $points[] = [
                'label' => $step > 1 ? $cursor->format('j M') : $cursor->format('j M'),
                'date' => $cursor->toDateString(),
                'count' => $count,
            ];

            $cursor->addDays($step);
        }

        return [
            'points' => $points,
            'max' => max(array_column($points, 'count') ?: [0]),
        ];
    }

    private function recentSubmissions(?AssessmentExercise $exercise): array
    {
        return AssessmentSubmission::query()
            ->with(['exercise:id,name,code'])
            ->where('status', '!=', AssessmentSubmission::STATUS_DRAFT)
            ->when($exercise, fn ($query) => $query->where('assessment_exercise_id', $exercise->id))
            ->latest('submitted_at')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AssessmentSubmission $submission): array => [
                'uuid' => $submission->uuid,
                'assessment_id' => $submission->assessment_id,
                'facility_name' => $submission->facility_name,
                'assessor_name' => $submission->assessor_name,
                'exercise_name' => $submission->exercise?->name,
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'status' => $submission->status,
                'score_total' => $submission->score_total,
            ])
            ->all();
    }

    private function recentAssignments(?AssessmentExercise $exercise): array
    {
        return AssessmentAssignment::query()
            ->with([
                'exercise:id,name,code',
                'facility:id,name,source_code',
                'user:id,name',
                'team:id,name',
            ])
            ->when($exercise, fn ($query) => $query->where('assessment_exercise_id', $exercise->id))
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (AssessmentAssignment $assignment): array => [
                'uuid' => $assignment->uuid,
                'facility_name' => $assignment->facility?->name ?? 'Unknown facility',
                'exercise_name' => $assignment->exercise?->name,
                'assignee_name' => $assignment->assignee_type === AssessmentAssignment::ASSIGNEE_TEAM
                    ? ($assignment->team?->name ?? 'Unknown team')
                    : ($assignment->user?->name ?? 'Unknown assessor'),
                'assignee_type' => $assignment->assignee_type,
                'status' => $assignment->status,
                'due_at' => $assignment->due_at?->toDateString(),
                'updated_at' => $assignment->updated_at?->toISOString(),
            ])
            ->all();
    }

    private function exerciseOptions(): array
    {
        return AssessmentExercise::query()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get(['id', 'uuid', 'name', 'code', 'status', 'starts_at', 'ends_at'])
            ->map(fn (AssessmentExercise $exercise): array => [
                'id' => $exercise->id,
                'uuid' => $exercise->uuid,
                'name' => $exercise->name,
                'code' => $exercise->code,
                'status' => $exercise->status,
                'starts_at' => $exercise->starts_at?->toDateString(),
                'ends_at' => $exercise->ends_at?->toDateString(),
            ])
            ->all();
    }

    private function exercisePayload(AssessmentExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'uuid' => $exercise->uuid,
            'name' => $exercise->name,
            'code' => $exercise->code,
            'status' => $exercise->status,
            'period_type' => $exercise->period_type,
            'starts_at' => $exercise->starts_at?->toDateString(),
            'ends_at' => $exercise->ends_at?->toDateString(),
            'form_title' => $exercise->form?->title,
            'form_slug' => $exercise->form?->slug,
            'form_version' => $exercise->formVersion?->version,
        ];
    }

    private function dateRange(string $range): array
    {
        return match ($range) {
            'this_month' => [
                'label' => 'This month',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ],
            'this_quarter' => [
                'label' => 'This quarter',
                'from' => now()->startOfQuarter()->toDateString(),
                'to' => now()->toDateString(),
            ],
            'this_year' => [
                'label' => 'This year',
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
            ],
            'all_time' => [
                'label' => 'All time',
                'from' => '2000-01-01',
                'to' => now()->toDateString(),
            ],
            default => [
                'label' => 'Last 30 days',
                'from' => now()->subDays(29)->toDateString(),
                'to' => now()->toDateString(),
            ],
        };
    }
}

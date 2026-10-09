<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentExercise;
use App\Models\OdkTeam;
use App\Models\OdkUser;
use App\Models\ReferenceFacility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentAssignmentController
{
    public function index(Request $request): JsonResponse
    {
        $assignments = AssessmentAssignment::query()
            ->with([
                'exercise:id,uuid,name,code,status,starts_at,ends_at',
                'facility:id,source_id,source_code,name,reference_lga_id,reference_ward_id',
                'facility.lga:id,name',
                'facility.ward:id,name',
                'user:id,uuid,name,email,status',
                'team:id,uuid,name,status',
            ])
            ->withCount('submissions')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('exercise_id'), fn ($query) => $query->where('assessment_exercise_id', $request->integer('exercise_id')))
            ->when($request->filled('assignee_type'), fn ($query) => $query->where('assignee_type', $request->string('assignee_type')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->input('q'));
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereHas('facility', fn ($facilityQuery) => $facilityQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('source_code', 'like', "%{$search}%"))
                        ->orWhereHas('exercise', fn ($exerciseQuery) => $exerciseQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => [
                'assignments' => $assignments,
                'options' => $this->options(),
                'stats' => [
                    'total' => AssessmentAssignment::count(),
                    'assigned' => AssessmentAssignment::where('status', AssessmentAssignment::STATUS_ASSIGNED)->count(),
                    'in_progress' => AssessmentAssignment::where('status', AssessmentAssignment::STATUS_IN_PROGRESS)->count(),
                    'submitted' => AssessmentAssignment::where('status', AssessmentAssignment::STATUS_SUBMITTED)->count(),
                    'reviewed' => AssessmentAssignment::where('status', AssessmentAssignment::STATUS_REVIEWED)->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assessment_exercise_id' => ['required', 'integer', 'exists:assessment_exercises,id'],
            'assignee_type' => ['required', Rule::in([AssessmentAssignment::ASSIGNEE_USER, AssessmentAssignment::ASSIGNEE_TEAM])],
            'assignee_id' => ['required', 'integer'],
            'facility_ids' => ['required', 'array', 'min:1'],
            'facility_ids.*' => ['integer', 'exists:reference_facilities,id'],
            'status' => ['nullable', Rule::in($this->statuses())],
            'allow_duplicate_submission' => ['boolean'],
            'due_at' => ['nullable', 'date'],
        ]);

        $exercise = AssessmentExercise::findOrFail($data['assessment_exercise_id']);
        $this->assertAssigneeExists($data['assignee_type'], (int) $data['assignee_id']);
        $allowDuplicate = (bool) ($data['allow_duplicate_submission'] ?? false);
        $facilityIds = array_values(array_unique($data['facility_ids']));

        if (!$allowDuplicate) {
            $this->assertNoDuplicateAssignments($exercise->id, $facilityIds);
        }

        $assignments = DB::transaction(function () use ($data, $exercise, $facilityIds, $allowDuplicate) {
            return collect($facilityIds)->map(function (int $facilityId) use ($data, $exercise, $allowDuplicate): AssessmentAssignment {
                return AssessmentAssignment::create([
                    'uuid' => (string) Str::uuid(),
                    'assessment_exercise_id' => $exercise->id,
                    'reference_facility_id' => $facilityId,
                    'assignee_type' => $data['assignee_type'],
                    'odk_user_id' => $data['assignee_type'] === AssessmentAssignment::ASSIGNEE_USER ? $data['assignee_id'] : null,
                    'odk_team_id' => $data['assignee_type'] === AssessmentAssignment::ASSIGNEE_TEAM ? $data['assignee_id'] : null,
                    'status' => $data['status'] ?? AssessmentAssignment::STATUS_ASSIGNED,
                    'allow_duplicate_submission' => $allowDuplicate,
                    'assigned_at' => now(),
                    'due_at' => $data['due_at'] ?? $exercise->ends_at,
                ]);
            });
        });

        return response()->json([
            'success' => true,
            'data' => $assignments->map(fn (AssessmentAssignment $assignment) => $assignment->fresh($this->loads()))->values(),
        ], 201);
    }

    public function update(Request $request, AssessmentAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'assignee_type' => ['required', Rule::in([AssessmentAssignment::ASSIGNEE_USER, AssessmentAssignment::ASSIGNEE_TEAM])],
            'assignee_id' => ['required', 'integer'],
            'status' => ['required', Rule::in($this->statuses())],
            'allow_duplicate_submission' => ['boolean'],
            'due_at' => ['nullable', 'date'],
        ]);

        $this->assertAssigneeExists($data['assignee_type'], (int) $data['assignee_id']);

        $assignment->update([
            'assignee_type' => $data['assignee_type'],
            'odk_user_id' => $data['assignee_type'] === AssessmentAssignment::ASSIGNEE_USER ? $data['assignee_id'] : null,
            'odk_team_id' => $data['assignee_type'] === AssessmentAssignment::ASSIGNEE_TEAM ? $data['assignee_id'] : null,
            'status' => $data['status'],
            'allow_duplicate_submission' => (bool) ($data['allow_duplicate_submission'] ?? false),
            'due_at' => $data['due_at'] ?? $assignment->due_at,
            ...$this->statusTimestamps($assignment, $data['status']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $assignment->fresh($this->loads()),
        ]);
    }

    private function assertAssigneeExists(string $type, int $id): void
    {
        $exists = $type === AssessmentAssignment::ASSIGNEE_USER
            ? OdkUser::whereKey($id)->where('status', OdkUser::STATUS_ACTIVE)->exists()
            : OdkTeam::whereKey($id)->where('status', OdkTeam::STATUS_ACTIVE)->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'assignee_id' => ['Select an active '.($type === AssessmentAssignment::ASSIGNEE_USER ? 'user' : 'team').'.'],
            ]);
        }
    }

    private function assertNoDuplicateAssignments(int $exerciseId, array $facilityIds): void
    {
        $duplicates = AssessmentAssignment::query()
            ->with('facility:id,name')
            ->where('assessment_exercise_id', $exerciseId)
            ->whereIn('reference_facility_id', $facilityIds)
            ->where('allow_duplicate_submission', false)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'facility_ids' => ['Already assigned for this exercise: '.$duplicates->pluck('facility.name')->filter()->join(', ')],
            ]);
        }
    }

    private function statusTimestamps(AssessmentAssignment $assignment, string $status): array
    {
        return [
            'started_at' => $status === AssessmentAssignment::STATUS_IN_PROGRESS && $assignment->started_at === null ? now() : $assignment->started_at,
            'submitted_at' => $status === AssessmentAssignment::STATUS_SUBMITTED && $assignment->submitted_at === null ? now() : $assignment->submitted_at,
            'completed_at' => $status === AssessmentAssignment::STATUS_REVIEWED && $assignment->completed_at === null ? now() : $assignment->completed_at,
        ];
    }

    private function options(): array
    {
        return [
            'exercises' => AssessmentExercise::query()
                ->whereIn('status', [AssessmentExercise::STATUS_DRAFT, AssessmentExercise::STATUS_SCHEDULED, AssessmentExercise::STATUS_ACTIVE])
                ->orderByDesc('starts_at')
                ->get(['id', 'uuid', 'name', 'code', 'status', 'starts_at', 'ends_at']),
            'users' => OdkUser::query()
                ->where('status', OdkUser::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'email']),
            'teams' => OdkTeam::query()
                ->where('status', OdkTeam::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name']),
            'facilities' => ReferenceFacility::query()
                ->with(['lga:id,name', 'ward:id,name'])
                ->where('active', true)
                ->orderBy('name')
                ->limit(1000)
                ->get(['id', 'source_id', 'source_code', 'name', 'reference_lga_id', 'reference_ward_id']),
        ];
    }

    private function statuses(): array
    {
        return [
            AssessmentAssignment::STATUS_ASSIGNED,
            AssessmentAssignment::STATUS_IN_PROGRESS,
            AssessmentAssignment::STATUS_SUBMITTED,
            AssessmentAssignment::STATUS_REVIEWED,
            AssessmentAssignment::STATUS_CANCELLED,
            AssessmentAssignment::STATUS_EXPIRED,
        ];
    }

    private function loads(): array
    {
        return [
            'exercise:id,uuid,name,code,status,starts_at,ends_at',
            'facility:id,source_id,source_code,name,reference_lga_id,reference_ward_id',
            'facility.lga:id,name',
            'facility.ward:id,name',
            'user:id,uuid,name,email,status',
            'team:id,uuid,name,status',
        ];
    }
}

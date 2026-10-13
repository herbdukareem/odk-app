<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\OdkTarget;
use App\Services\Saas\OdkSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentExerciseController
{
    public function index(Request $request): JsonResponse
    {
        $exercises = AssessmentExercise::query()
            ->with([
                'form:id,slug,title',
                'formVersion:id,assessment_form_id,version,status,published_at',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('year'), fn ($query) => $query->where('year', $request->integer('year')))
            ->when($request->filled('period_type'), fn ($query) => $query->where('period_type', $request->string('period_type')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->input('q'));
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('starts_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => [
                'exercises' => $exercises,
                'form_options' => $this->publishedFormOptions(),
                'stats' => [
                    'total' => AssessmentExercise::count(),
                    'draft' => AssessmentExercise::where('status', AssessmentExercise::STATUS_DRAFT)->count(),
                    'scheduled' => AssessmentExercise::where('status', AssessmentExercise::STATUS_SCHEDULED)->count(),
                    'active' => AssessmentExercise::where('status', AssessmentExercise::STATUS_ACTIVE)->count(),
                    'closed' => AssessmentExercise::where('status', AssessmentExercise::STATUS_CLOSED)->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request, OdkSubscriptionService $subscriptions): JsonResponse
    {
        $subscriptions->assertCurrentWithinLimit('exercises');
        $data = $this->validated($request);
        $version = $this->publishedVersionForForm((int) $data['assessment_form_id'], (int) $data['assessment_form_version_id']);

        $exercise = DB::transaction(function () use ($data, $version): AssessmentExercise {
            return AssessmentExercise::create([
                ...$this->values($data, $version),
                'uuid' => (string) Str::uuid(),
                'code' => $this->uniqueCode($data['name'], (int) $data['year'], $data['quarter'] ?? null, $data['month'] ?? null),
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $exercise->load(['form:id,slug,title', 'formVersion:id,assessment_form_id,version,status,published_at']),
        ], 201);
    }

    public function show(AssessmentExercise $exercise): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $exercise->load(['form:id,slug,title', 'formVersion:id,assessment_form_id,version,status,published_at']),
        ]);
    }

    public function update(Request $request, AssessmentExercise $exercise): JsonResponse
    {
        $data = $this->validated($request, $exercise);
        $version = $this->publishedVersionForForm((int) $data['assessment_form_id'], (int) $data['assessment_form_version_id']);

        DB::transaction(function () use ($exercise, $data, $version): void {
            $exercise->update($this->values($data, $version));
        });

        return response()->json([
            'success' => true,
            'data' => $exercise->fresh()->load(['form:id,slug,title', 'formVersion:id,assessment_form_id,version,status,published_at']),
        ]);
    }

    private function validated(Request $request, ?AssessmentExercise $exercise = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_type' => ['required', Rule::in([
                AssessmentExercise::PERIOD_QUARTER,
                AssessmentExercise::PERIOD_MONTH,
                AssessmentExercise::PERIOD_CUSTOM,
            ])],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'quarter' => ['nullable', 'required_if:period_type,quarter', 'integer', 'min:1', 'max:4'],
            'month' => ['nullable', 'required_if:period_type,month', 'integer', 'min:1', 'max:12'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'assessment_form_id' => ['required', 'integer', 'exists:assessment_forms,id'],
            'assessment_form_version_id' => ['required', 'integer', 'exists:assessment_form_versions,id'],
            'status' => ['required', Rule::in([
                AssessmentExercise::STATUS_DRAFT,
                AssessmentExercise::STATUS_SCHEDULED,
                AssessmentExercise::STATUS_ACTIVE,
                AssessmentExercise::STATUS_CLOSED,
                AssessmentExercise::STATUS_ARCHIVED,
            ])],
            'target_type' => ['nullable', Rule::in(OdkTarget::types())],
        ]);

        if ($data['period_type'] !== AssessmentExercise::PERIOD_QUARTER) {
            $data['quarter'] = null;
        }

        if ($data['period_type'] !== AssessmentExercise::PERIOD_MONTH) {
            $data['month'] = null;
        }

        return $data;
    }

    private function values(array $data, AssessmentFormVersion $version): array
    {
        $statusTimestamps = $this->statusTimestamps($data['status']);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'period_type' => $data['period_type'],
            'year' => $data['year'],
            'quarter' => $data['quarter'] ?? null,
            'month' => $data['month'] ?? null,
            'starts_at' => Carbon::parse($data['starts_at'])->toDateString(),
            'ends_at' => Carbon::parse($data['ends_at'])->toDateString(),
            'assessment_form_id' => $version->assessment_form_id,
            'assessment_form_version_id' => $version->id,
            'status' => $data['status'],
            'target_type' => $data['target_type'] ?? OdkTarget::TYPE_FACILITY,
            ...$statusTimestamps,
        ];
    }

    private function statusTimestamps(string $status): array
    {
        return [
            'activated_at' => $status === AssessmentExercise::STATUS_ACTIVE ? now() : null,
            'closed_at' => in_array($status, [AssessmentExercise::STATUS_CLOSED, AssessmentExercise::STATUS_ARCHIVED], true) ? now() : null,
        ];
    }

    private function publishedVersionForForm(int $formId, int $versionId): AssessmentFormVersion
    {
        return AssessmentFormVersion::query()
            ->whereKey($versionId)
            ->where('assessment_form_id', $formId)
            ->where('status', AssessmentFormVersion::STATUS_PUBLISHED)
            ->firstOrFail();
    }

    private function publishedFormOptions(): array
    {
        return AssessmentForm::query()
            ->where('status', AssessmentForm::STATUS_PUBLISHED)
            ->with(['versions' => fn ($query) => $query
                ->where('status', AssessmentFormVersion::STATUS_PUBLISHED)
                ->orderByDesc('version')])
            ->orderBy('title')
            ->get(['id', 'slug', 'title'])
            ->filter(fn (AssessmentForm $form) => $form->versions->isNotEmpty())
            ->values()
            ->map(fn (AssessmentForm $form): array => [
                'id' => $form->id,
                'slug' => $form->slug,
                'title' => $form->title,
                'versions' => $form->versions->map(fn (AssessmentFormVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'published_at' => $version->published_at?->toISOString(),
                ])->values(),
            ])
            ->all();
    }

    private function uniqueCode(string $name, int $year, mixed $quarter, mixed $month): string
    {
        $period = $quarter ? "q{$quarter}" : ($month ? 'm'.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : 'custom');
        $base = strtoupper(Str::slug($name, '-')).'-'.$year.'-'.strtoupper($period);
        $code = $base;
        $suffix = 2;

        while (AssessmentExercise::where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}

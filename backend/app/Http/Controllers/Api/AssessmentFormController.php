<?php

namespace App\Http\Controllers\Api;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormField;
use App\Models\AssessmentFormFieldOption;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentFormVersion;
use App\Models\OdkTarget;
use App\Services\Saas\OdkSubscriptionService;
use App\Services\Forms\FormBuilderSnapshotService;
use App\Services\Forms\FormRuleTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentFormController
{
    public function index(Request $request): JsonResponse
    {
        $forms = AssessmentForm::query()
            ->with('currentVersion:id,assessment_form_id,version,status,offline_enabled,published_at')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $forms,
        ]);
    }

    public function show(AssessmentForm $form): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $form->load([
                'currentVersion',
                'versions' => fn ($query) => $query->orderByDesc('version'),
                'sections.fields.options',
                'fields.options',
                'builderRules.field',
            ]),
        ]);
    }

    public function store(Request $request, OdkSubscriptionService $subscriptions): JsonResponse
    {
        $subscriptions->assertCurrentWithinLimit('forms');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'target_type' => ['nullable', 'string', 'in:'.implode(',', OdkTarget::types())],
            'requires_scoring' => ['boolean'],
            'scoring_max_score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $form = AssessmentForm::create([
            'uuid' => (string) Str::uuid(),
            'slug' => $this->uniqueSlug($data['title']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_audience' => $data['target_audience'] ?? 'Facility assessment teams',
            'target_type' => $data['target_type'] ?? OdkTarget::TYPE_FACILITY,
            'requires_scoring' => (bool) ($data['requires_scoring'] ?? false),
            'scoring_max_score' => ($data['requires_scoring'] ?? false) ? ($data['scoring_max_score'] ?? null) : null,
            'status' => AssessmentForm::STATUS_DRAFT,
        ]);

        AssessmentFormSection::create([
            'assessment_form_id' => $form->id,
            'key' => 'assessment_metadata',
            'label' => 'Assessment details',
            'sort_order' => 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $form->load('sections'),
        ], 201);
    }

    public function update(Request $request, AssessmentForm $form): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'target_type' => ['nullable', 'string', 'in:'.implode(',', OdkTarget::types())],
            'requires_scoring' => ['boolean'],
            'scoring_max_score' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!($data['requires_scoring'] ?? false)) {
            $data['scoring_max_score'] = null;
        }

        $form->fill($data);
        if ($form->status === AssessmentForm::STATUS_PUBLISHED) {
            $form->status = AssessmentForm::STATUS_DRAFT;
        }
        $form->save();

        return response()->json([
            'success' => true,
            'data' => $form->fresh()->load(['currentVersion', 'versions', 'sections.fields.options', 'fields.options', 'builderRules.field']),
        ]);
    }

    public function clone(AssessmentForm $form, OdkSubscriptionService $subscriptions): JsonResponse
    {
        $subscriptions->assertCurrentWithinLimit('forms');
        $clone = DB::transaction(function () use ($form): AssessmentForm {
            $form->load(['sections.fields.options', 'builderRules']);

            $clone = AssessmentForm::create([
                'uuid' => (string) Str::uuid(),
                'slug' => $this->uniqueSlug($form->title.' copy'),
                'title' => $form->title.' copy',
                'description' => $form->description,
                'target_audience' => $form->target_audience,
                'requires_scoring' => $form->requires_scoring,
                'scoring_max_score' => $form->scoring_max_score,
                'status' => AssessmentForm::STATUS_DRAFT,
            ]);

            $sectionMap = [];
            foreach ($form->sections as $section) {
                $newSection = $clone->sections()->create($section->only([
                    'key',
                    'label',
                    'description',
                    'sort_order',
                    'is_repeatable',
                    'min_repeats',
                    'max_repeats',
                    'settings',
                ]));
                $sectionMap[$section->id] = $newSection;
            }

            $fieldMap = [];
            foreach ($form->fields as $field) {
                $newField = $clone->fields()->create([
                    'assessment_form_section_id' => $field->assessment_form_section_id ? ($sectionMap[$field->assessment_form_section_id]->id ?? null) : null,
                    ...$field->only([
                        'key',
                        'label',
                        'type',
                        'help_text',
                        'placeholder',
                        'required',
                        'readonly',
                        'sort_order',
                        'group',
                        'default_value',
                        'validation_rules',
                        'display_logic',
                        'required_logic',
                        'settings',
                    ]),
                ]);
                $fieldMap[$field->id] = $newField;

                foreach ($field->options as $option) {
                    $newField->options()->create($option->only([
                        'label',
                        'value',
                        'score',
                        'sort_order',
                        'requires_review',
                        'settings',
                    ]));
                }
            }

            foreach ($form->builderRules as $rule) {
                $clone->builderRules()->create([
                    'assessment_form_field_id' => $rule->assessment_form_field_id ? ($fieldMap[$rule->assessment_form_field_id]->id ?? null) : null,
                    ...$rule->only([
                        'type',
                        'name',
                        'condition',
                        'action',
                        'message',
                        'severity',
                        'sort_order',
                        'enabled',
                        'requires_review',
                    ]),
                ]);
            }

            return $clone;
        });

        return response()->json([
            'success' => true,
            'data' => $clone->load(['sections.fields.options', 'fields.options', 'builderRules.field']),
        ], 201);
    }

    public function storeSection(Request $request, AssessmentForm $form): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_repeatable' => ['boolean'],
            'min_repeats' => ['nullable', 'integer', 'min:0'],
            'max_repeats' => ['nullable', 'integer', 'min:1'],
        ]);

        $section = $form->sections()->create([
            'key' => $this->uniqueSectionKey($form, $data['key'] ?? Str::slug($data['label'], '_')),
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'sort_order' => ((int) $form->sections()->max('sort_order')) + 1,
            'is_repeatable' => (bool) ($data['is_repeatable'] ?? false),
            'min_repeats' => $data['min_repeats'] ?? null,
            'max_repeats' => $data['max_repeats'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $section], 201);
    }

    public function storeField(Request $request, AssessmentForm $form): JsonResponse
    {
        $data = $this->validateField($request);
        $field = DB::transaction(fn () => $this->persistField($form, new AssessmentFormField, $data));

        return response()->json(['success' => true, 'data' => $field->load('options')], 201);
    }

    public function updateField(Request $request, AssessmentForm $form, AssessmentFormField $field): JsonResponse
    {
        abort_unless((int) $field->assessment_form_id === (int) $form->id, 404);

        $data = $this->validateField($request);
        $field = DB::transaction(fn () => $this->persistField($form, $field, $data));

        return response()->json(['success' => true, 'data' => $field->load('options')]);
    }

    public function destroyField(AssessmentForm $form, AssessmentFormField $field): JsonResponse
    {
        abort_unless((int) $field->assessment_form_id === (int) $form->id, 404);

        DB::transaction(function () use ($form, $field): void {
            $field->options()->delete();
            $field->rules()->delete();
            $field->delete();
            $this->markDraft($form);
        });

        return response()->json(['success' => true]);
    }

    public function destroySection(AssessmentForm $form, AssessmentFormSection $section): JsonResponse
    {
        abort_unless((int) $section->assessment_form_id === (int) $form->id, 404);

        DB::transaction(function () use ($form, $section): void {
            $section->fields()->get()->each(function (AssessmentFormField $field): void {
                $field->options()->delete();
                $field->rules()->delete();
                $field->delete();
            });
            $section->delete();
            $this->markDraft($form);
        });

        return response()->json(['success' => true]);
    }

    public function testRules(Request $request, AssessmentForm $form, FormRuleTester $tester): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['array'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $tester->test($form, $data['answers'] ?? []),
        ]);
    }

    public function publish(AssessmentForm $form, FormBuilderSnapshotService $snapshots): JsonResponse
    {
        $version = $snapshots->publish($form);

        return response()->json([
            'success' => true,
            'data' => $form->fresh()->load(['currentVersion', 'versions' => fn ($query) => $query->orderByDesc('version')]),
            'version' => $version,
        ]);
    }

    public function published(): JsonResponse
    {
        $forms = AssessmentForm::query()
            ->where('status', AssessmentForm::STATUS_PUBLISHED)
            ->with(['currentVersion' => fn ($query) => $query->where('status', AssessmentFormVersion::STATUS_PUBLISHED)])
            ->orderBy('title')
            ->get()
            ->filter(fn (AssessmentForm $form) => $form->currentVersion !== null)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $forms,
        ]);
    }

    private function persistField(AssessmentForm $form, AssessmentFormField $field, array $data): AssessmentFormField
    {
        $section = null;
        if (!empty($data['section_key'])) {
            $section = $form->sections()->where('key', $data['section_key'])->firstOrFail();
        }

        $isNew = !$field->exists;
        $field->fill([
            'assessment_form_id' => $form->id,
            'assessment_form_section_id' => $section?->id,
            'key' => $isNew ? $this->uniqueFieldKey($form, $data['key'] ?? Str::slug($data['label'], '_')) : $field->key,
            'label' => $data['label'],
            'type' => $data['type'],
            'help_text' => $data['help_text'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'required' => (bool) ($data['required'] ?? false),
            'readonly' => (bool) ($data['readonly'] ?? false),
            'sort_order' => $data['sort_order'] ?? ($isNew
                ? (((int) $form->fields()->max('sort_order')) + 1)
                : $field->sort_order),
            'group' => $data['group'] ?? null,
            'validation_rules' => $data['validation_rules'] ?? [],
            'display_logic' => $data['display_logic'] ?? null,
            'required_logic' => $data['required_logic'] ?? null,
            'settings' => array_key_exists('settings', $data) ? ($data['settings'] ?? null) : ($field->settings ?? null),
        ])->save();

        $this->replaceOptions($field, $data['options'] ?? []);

        if ($form->status === AssessmentForm::STATUS_PUBLISHED) {
            $form->forceFill(['status' => AssessmentForm::STATUS_DRAFT])->save();
        }

        return $field;
    }

    private function replaceOptions(AssessmentFormField $field, array $options): void
    {
        // Options are soft-deletable, but their field/value pair is permanently
        // unique. Remove the old rows before recreating the current snapshot.
        AssessmentFormFieldOption::withTrashed()
            ->where('assessment_form_field_id', $field->id)
            ->forceDelete();

        $seenValues = [];
        $normalizedOptions = [];

        foreach ($options as $option) {
            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));
            $value = $value !== '' ? $value : Str::slug($label, '_');
            $valueKey = strtolower($value);

            if ($label === '' || $value === '' || isset($seenValues[$valueKey])) {
                continue;
            }

            $seenValues[$valueKey] = true;
            $normalizedOptions[] = [
                'label' => $label,
                'value' => $value,
                'score' => $option['score'] ?? null,
                'requires_review' => (bool) ($option['requires_review'] ?? $option['needs_review'] ?? false),
                'settings' => $option['settings'] ?? null,
            ];
        }

        foreach ($normalizedOptions as $index => $option) {
            AssessmentFormFieldOption::create([
                'assessment_form_field_id' => $field->id,
                'label' => $option['label'],
                'value' => $option['value'],
                'score' => $option['score'] ?? null,
                'sort_order' => $index + 1,
                'requires_review' => $option['requires_review'],
                'settings' => $option['settings'] ?? null,
            ]);
        }
    }

    private function validateField(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:60'],
            'section_key' => ['nullable', 'string', 'max:120'],
            'help_text' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'required' => ['boolean'],
            'readonly' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'group' => ['nullable', 'string', 'max:255'],
            'validation_rules' => ['array'],
            'display_logic' => ['nullable', 'array'],
            'required_logic' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'options' => ['array'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:255'],
            'options.*.score' => ['nullable', 'numeric'],
            'options.*.requires_review' => ['boolean'],
        ]);

        abort_if(($data['type'] ?? null) === 'signature', 422, 'Signature fields are currently disabled.');

        return $data;
    }

    private function markDraft(AssessmentForm $form): void
    {
        if ($form->status === AssessmentForm::STATUS_PUBLISHED) {
            $form->forceFill(['status' => AssessmentForm::STATUS_DRAFT])->save();
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $suffix = 2;
        while (AssessmentForm::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueSectionKey(AssessmentForm $form, string $key): string
    {
        $base = Str::slug($key, '_') ?: 'section';
        $candidate = $base;
        $suffix = 2;
        while ($form->sections()->where('key', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }

    private function uniqueFieldKey(AssessmentForm $form, string $key): string
    {
        $base = Str::slug($key, '_') ?: 'field';
        $candidate = $base;
        $suffix = 2;
        while ($form->fields()->where('key', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }
}

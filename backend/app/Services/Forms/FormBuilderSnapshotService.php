<?php

namespace App\Services\Forms;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormRule;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentExercise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FormBuilderSnapshotService
{
    public function build(AssessmentForm $form): array
    {
        $form->loadMissing([
            'sections.fields.options',
            'fields.options',
            'builderRules.field',
        ]);

        $fields = $form->fields
            ->reject(fn ($field) => $field->type === 'signature')
            ->sortBy(fn ($field) => [$field->section?->sort_order ?? 0, $field->sort_order, $field->id])
            ->map(fn ($field) => $this->fieldPayload($field))
            ->values()
            ->all();

        return [
            'metadata_schema' => [
                'facility_source' => 'new-web',
                'requires_facility_snapshot' => true,
                'requires_assessor_snapshot' => true,
                'requires_device_snapshot' => true,
                'requires_scoring' => (bool) $form->requires_scoring,
                'scoring_max_score' => $form->scoring_max_score === null ? null : (float) $form->scoring_max_score,
            ],
            'sections' => $form->sections
                ->map(fn ($section) => [
                    'key' => $section->key,
                    'label' => $section->label,
                    'description' => $section->description,
                    'order' => $section->sort_order,
                    'is_repeatable' => $section->is_repeatable,
                    'min_repeats' => $section->min_repeats,
                    'max_repeats' => $section->max_repeats,
                    'settings' => $section->settings ?? [],
                ])
                ->values()
                ->all(),
            'fields' => $fields,
            'validation_rules' => $this->validationRules($form, $fields),
            'visibility_rules' => $this->rulesOfType($form, AssessmentFormRule::TYPE_VISIBILITY),
            'scoring_rules' => $this->scoringRules($form, $fields),
            'required_evidence_rules' => $this->rulesOfType($form, AssessmentFormRule::TYPE_EVIDENCE),
            'signature_rules' => [],
            'review_flags' => $this->rulesOfType($form, AssessmentFormRule::TYPE_REVIEW_FLAG),
        ];
    }

    public function publish(AssessmentForm $form): AssessmentFormVersion
    {
        return DB::transaction(function () use ($form): AssessmentFormVersion {
            $form->refresh();
            $snapshot = $this->build($form);
            $nextVersion = ((int) $form->versions()->max('version')) + 1;

            $version = AssessmentFormVersion::create([
                'assessment_form_id' => $form->id,
                'version' => $nextVersion,
                'status' => AssessmentFormVersion::STATUS_PUBLISHED,
                'offline_enabled' => true,
                'requires_scoring' => (bool) $form->requires_scoring,
                'scoring_max_score' => $form->scoring_max_score,
                'metadata_schema' => $snapshot['metadata_schema'],
                'sections' => $snapshot['sections'],
                'fields' => $snapshot['fields'],
                'validation_rules' => $snapshot['validation_rules'],
                'visibility_rules' => $snapshot['visibility_rules'],
                'scoring_rules' => $snapshot['scoring_rules'],
                'required_evidence_rules' => $snapshot['required_evidence_rules'],
                'signature_rules' => $snapshot['signature_rules'],
                'review_flags' => $snapshot['review_flags'],
                'published_at' => now(),
            ]);

            $form->forceFill([
                'current_version_id' => $version->id,
                'status' => AssessmentForm::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->save();

            // Keep active work aligned with the newly published schema. Closed
            // and archived exercises remain pinned for historical accuracy.
            AssessmentExercise::query()
                ->where('assessment_form_id', $form->id)
                ->whereIn('status', [
                    AssessmentExercise::STATUS_DRAFT,
                    AssessmentExercise::STATUS_SCHEDULED,
                    AssessmentExercise::STATUS_ACTIVE,
                ])
                ->update([
                    'assessment_form_version_id' => $version->id,
                    'updated_at' => now(),
                ]);

            return $version;
        });
    }

    public function repairFieldOrder(AssessmentForm $form): int
    {
        return DB::transaction(fn (): int => $this->resequenceFieldOrder($form));
    }

    private function resequenceFieldOrder(AssessmentForm $form): int
    {
        $fields = $form->fields()
            ->reorder('id')
            ->get();
        $repaired = 0;

        foreach ($fields->groupBy(fn ($field) => $field->assessment_form_section_id ?? 0) as $sectionFields) {
            foreach ($sectionFields->values() as $index => $field) {
                $sortOrder = $index + 1;
                if ((int) $field->sort_order !== $sortOrder) {
                    $field->updateQuietly(['sort_order' => $sortOrder]);
                    $repaired++;
                }
            }
        }

        if ($repaired > 0 && $form->status === AssessmentForm::STATUS_PUBLISHED) {
            $form->forceFill(['status' => AssessmentForm::STATUS_DRAFT])->save();
        }

        return $repaired;
    }

    private function fieldPayload($field): array
    {
        $payload = [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type,
            'order' => (int) $field->sort_order,
            'section' => $field->section?->key,
            'required' => $field->required,
            'readonly' => $field->readonly,
            'rules' => $field->validation_rules ?? [],
            'group' => $field->group,
            'help_text' => $field->help_text,
            'placeholder' => $field->placeholder,
        ];

        if ($field->display_logic) {
            $payload['visible_if'] = $field->display_logic;
        }

        if ($field->required_logic) {
            $payload['required_if'] = $field->required_logic;
        }

        if ($field->settings) {
            $payload = array_merge($payload, $field->settings);
        }

        if ($field->options->isNotEmpty()) {
            $payload['options'] = $field->options
                ->map(fn ($option) => [
                    'label' => $option->label,
                    'value' => $option->value,
                    'score' => $option->score,
                    'needs_review' => $option->requires_review,
                ] + ($option->settings ?? []))
                ->values()
                ->all();
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }

    private function validationRules(AssessmentForm $form, array $fields): array
    {
        $explicit = collect($this->rulesOfType($form, AssessmentFormRule::TYPE_VALIDATION))
            ->keyBy('field');

        return collect($fields)
            ->map(function (array $field) use ($explicit): array {
                $rules = Arr::wrap($field['rules'] ?? []);
                array_unshift($rules, ($field['required'] ?? false) ? 'required' : 'nullable');

                $rule = [
                    'field' => $field['key'],
                    'rules' => array_values(array_unique($rules)),
                ];

                if (isset($field['required_if'])) {
                    $rule['required_if'] = $field['required_if'];
                }

                if ($explicit->has($field['key'])) {
                    $rule['message'] = $explicit[$field['key']]['message'] ?? null;
                }

                return array_filter($rule, fn ($value) => $value !== null);
            })
            ->values()
            ->all();
    }

    private function scoringRules(AssessmentForm $form, array $fields): array
    {
        if (!$form->requires_scoring) {
            return [];
        }

        $scoredFields = collect($fields)
            ->filter(fn (array $field) => isset($field['options']) && $this->fieldIsScorable($field))
            ->map(fn (array $field) => [
                'field' => $field['key'],
                'type' => 'option_score',
                'options' => collect($field['options'])
                    ->map(fn (array $option) => [
                        'value' => $option['value'],
                        'score' => $option['score'] ?? null,
                        'needs_review' => (bool) ($option['needs_review'] ?? false),
                    ])
                    ->values()
                    ->all(),
            ]);

        return $scoredFields
            ->merge($this->rulesOfType($form, AssessmentFormRule::TYPE_SCORING))
            ->values()
            ->all();
    }

    private function fieldIsScorable(array $field): bool
    {
        if (array_key_exists('scorable', $field)) {
            return (bool) $field['scorable'];
        }

        return collect($field['options'] ?? [])
            ->contains(fn (array $option) => array_key_exists('score', $option) && $option['score'] !== null);
    }

    private function rulesOfType(AssessmentForm $form, string $type): array
    {
        return $form->builderRules
            ->where('type', $type)
            ->where('enabled', true)
            ->map(fn ($rule) => array_filter([
                'id' => $rule->id,
                'field' => $rule->field?->key,
                'type' => $rule->type,
                'name' => $rule->name,
                'condition' => $rule->condition,
                'action' => $rule->action,
                'message' => $rule->message,
                'severity' => $rule->severity,
                'requires_review' => $rule->requires_review,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }
}

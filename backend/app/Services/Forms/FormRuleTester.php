<?php

namespace App\Services\Forms;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;

class FormRuleTester
{
    public function __construct(private readonly FormBuilderSnapshotService $snapshots)
    {
    }

    public function test(AssessmentForm $form, array $answers): array
    {
        return $this->testSnapshot($this->snapshots->build($form), $answers);
    }

    public function testVersion(AssessmentFormVersion $version, array $answers): array
    {
        return $this->testSnapshot([
            'fields' => $version->fields ?? [],
            'scoring_rules' => $version->scoring_rules ?? [],
        ], $answers);
    }

    private function testSnapshot(array $snapshot, array $answers): array
    {
        $visibleFields = collect($snapshot['fields'])
            ->reject(fn (array $field) => ($field['type'] ?? null) === 'signature')
            ->filter(fn (array $field) => $this->isVisible($field, $answers));

        $errors = [];
        foreach ($visibleFields as $field) {
            $value = $answers[$field['key']] ?? null;
            $required = (bool) ($field['required'] ?? false) || $this->isConditionallyRequired($field, $answers);

            if ($required && $this->isBlank($value)) {
                $errors[] = [
                    'field' => $field['key'],
                    'message' => $field['label'].' is required.',
                ];
                continue;
            }

            if (($field['type'] ?? null) === 'yes_no_with_count') {
                foreach ($this->checkCompoundCountRules($field, $value) as $message) {
                    $errors[] = ['field' => $field['key'], 'message' => $message];
                }
            }

            foreach ($field['rules'] ?? [] as $rule) {
                $message = $this->checkRule((string) $rule, $field, $value);
                if ($message !== null) {
                    $errors[] = ['field' => $field['key'], 'message' => $message];
                }
            }
        }

        $score = $this->score($snapshot['scoring_rules'], $answers);

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'visible_fields' => $visibleFields->pluck('key')->values()->all(),
            'score' => $score,
        ];
    }

    private function isVisible(array $field, array $answers): bool
    {
        if (!isset($field['visible_if'])) {
            return true;
        }

        return $this->conditionMatches($field['visible_if'], $answers);
    }

    private function isConditionallyRequired(array $field, array $answers): bool
    {
        if (!isset($field['required_if']) || !is_array($field['required_if'])) {
            return false;
        }

        foreach ($field['required_if'] as $key => $expected) {
            if (($answers[$key] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(array $condition, array $answers): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;
        $actual = $field ? ($answers[$field] ?? null) : null;

        return match ($operator) {
            'not_equals' => $actual !== $expected,
            default => $actual === $expected,
        };
    }

    private function checkRule(string $rule, array $field, mixed $value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        $textValue = is_string($value) ? $value : (string) $value;

        if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $field['label'].' must be a whole number.';
        }

        if ($rule === 'numeric' && !is_numeric($value)) {
            return $field['label'].' must be numeric.';
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (float) substr($rule, 4);
            if ($this->isNumericField($field) && is_numeric($value)) {
                if ((float) $value < $min) {
                    return $field['label'].' must be at least '.$min.'.';
                }
            } elseif (!$this->isNumericField($field) && strlen($textValue) < $min) {
                return $field['label'].' must be at least '.$min.' characters.';
            }
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (float) substr($rule, 4);
            if ($this->isNumericField($field) && is_numeric($value)) {
                if ((float) $value > $max) {
                    return $field['label'].' must be '.$max.' or less.';
                }
            } elseif (!$this->isNumericField($field) && strlen($textValue) > $max) {
                return $field['label'].' must be '.$max.' characters or fewer.';
            }
        }

        return null;
    }

    private function isNumericField(array $field): bool
    {
        return in_array($field['type'] ?? null, ['number', 'currency'], true)
            || in_array('numeric', $field['rules'] ?? [], true)
            || in_array('integer', $field['rules'] ?? [], true);
    }

    private function score(array $scoringRules, array $answers): array
    {
        $total = 0.0;
        $scored = [];

        foreach ($scoringRules as $rule) {
            if (($rule['type'] ?? null) !== 'option_score' || !isset($rule['field'])) {
                continue;
            }

            $answer = $answers[$rule['field']] ?? null;
            if (is_array($answer)) {
                $answer = $answer['answer'] ?? $answer['value'] ?? null;
            }

            $option = collect($rule['options'] ?? [])->firstWhere('value', $answer);
            if ($option === null || ($option['score'] ?? null) === null) {
                continue;
            }

            $value = (float) $option['score'];
            $total += $value;
            $scored[] = ['field' => $rule['field'], 'score' => $value];
        }

        return ['total' => $total, 'fields' => $scored];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function checkCompoundCountRules(array $field, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $answer = $value['answer'] ?? null;
        $count = $value['count'] ?? null;
        $requiredValue = $field['count_required_value'] ?? 'yes';
        if ($answer !== $requiredValue) {
            return [];
        }

        $label = $field['count_label'] ?? 'Total number';
        if ($this->isBlank($count)) {
            return [$label.' is required when '.$field['label'].' is yes.'];
        }

        $errors = [];
        foreach ($field['count_rules'] ?? ['integer', 'min:1'] as $rule) {
            $message = $this->checkRule((string) $rule, ['label' => $label, 'type' => 'number'], $count);
            if ($message !== null) {
                $errors[] = $message;
            }
        }

        return $errors;
    }
}

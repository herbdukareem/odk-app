<template>
  <section class="screen-stack settings-screen">
    <AppPageHeader title="Form settings" kicker="W04" compact>
      <template #actions>
        <div class="action-row">
          <QdsButton icon="save" variant="secondary" @click="saveOverview">Save draft</QdsButton>
          <QdsButton icon="submissions" @click="publishForm">Publish form</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <QdsCard class="builder-shell">
      <div class="builder-heading">
        <div class="settings-title">
          <QdsIconButton icon="arrowLeft" aria-label="Back to forms" @click="$emit('back')" />
          <div>
            <h2>{{ formModel.title }}</h2>
            <p>
              <span>Version {{ formModel.current_version?.version ?? 1 }}</span>
              <span> - Last updated {{ formatDate(formModel.updated_at) }}</span>
            </p>
          </div>
        </div>
        <QdsBadge :tone="statusTone(formModel.status)">{{ titleCase(formModel.status) }}</QdsBadge>
      </div>

      <p v-if="notice" class="builder-notice">{{ notice }}</p>
      <QdsTabs v-model="activeTab" :tabs="builderTabs" wide />

      <div v-if="activeTab === 'overview'" class="settings-grid">
        <section class="settings-column">
          <QdsField v-model="formModel.title" label="Form title" />
          <QdsField v-model="description" label="Description" textarea />
          <div class="two-column-fields">
            <QdsField v-model="targetAudience" label="Target audience" :options="['Facility assessment teams', 'National QA reviewers', 'State supervisors']" />
            <QdsField v-model="defaultAssignment" label="Default assignment" :options="['Any team member', 'Assigned assessor', 'Supervisor review']" />
          </div>
        </section>

        <section class="settings-side">
          <div class="settings-block">
            <h3><AppIcon name="signature" /><span>Signatures</span></h3>
            <p class="settings-disabled-note">Signature capture is currently disabled for web and mobile assessments.</p>
          </div>

          <div class="settings-block">
            <h3><AppIcon name="reports" /><span>Scoring</span></h3>
            <div class="toggle-row">
              <div>
                <strong>Require scoring</strong>
                <span>Enable scores for selected fields in this form.</span>
              </div>
              <QdsSwitch v-model="requiresScoring" />
            </div>
            <QdsField
              v-if="requiresScoring"
              v-model="scoringMaxScore"
              label="Maximum score"
              type="number"
            />
          </div>

          <div class="settings-block">
            <h3><AppIcon name="wifi" /><span>Offline availability</span></h3>
            <div class="toggle-row">
              <div>
                <strong>Allow offline use</strong>
                <span>Published snapshots can be downloaded by the mobile app.</span>
              </div>
              <QdsSwitch v-model="offlineEnabled" />
            </div>
          </div>
        </section>
      </div>

      <div v-else-if="activeTab === 'builder'" class="builder-workbench">
        <aside class="builder-sections">
          <div class="qds-card-header">
            <div>
              <h2>Sections</h2>
              <p>{{ builderSections.length }} configured sections</p>
            </div>
            <QdsButton variant="tiny" @click="showSectionCreator = !showSectionCreator">
              {{ showSectionCreator ? 'Close' : 'Add' }}
            </QdsButton>
          </div>
          <div v-if="showSectionCreator" class="section-creator">
            <QdsField v-model="sectionDraft.label" label="Section name" />
            <QdsField v-model="sectionDraft.key" label="Short key" />
            <QdsField v-model="sectionDraft.description" label="Description" textarea :rows="3" />
            <div class="field-editor-checks">
              <QdsCheckbox v-model="sectionDraft.is_repeatable" label="Repeatable section" />
            </div>
            <div v-if="sectionDraft.is_repeatable" class="two-column-fields">
              <QdsField v-model="sectionDraft.min_repeats" label="Minimum repeats" type="number" />
              <QdsField v-model="sectionDraft.max_repeats" label="Maximum repeats" type="number" />
            </div>
            <QdsButton icon="plus" block @click="saveSection">Create section</QdsButton>
          </div>
          <div
            v-for="section in builderSections"
            :key="section.key"
            :class="['section-pill', selectedSectionKey === section.key && 'active']"
          >
            <button class="section-pill__select" type="button" @click="selectedSectionKey = section.key">
              <strong>{{ section.label }}</strong>
              <span>{{ sectionFieldCount(section.key) }} fields</span>
            </button>
            <QdsButton v-if="section.id" variant="tiny" @click="removeSection(section)">Remove</QdsButton>
          </div>
        </aside>

        <section class="builder-panel">
          <div class="qds-card-header">
            <div>
              <h2>Form builder</h2>
              <p>Add fields, options, validation rules, and section grouping.</p>
            </div>
            <div class="action-row builder-actions">
              <QdsButton icon="plus" variant="secondary" @click="showSectionCreator = true">Add section</QdsButton>
              <QdsButton icon="plus" variant="secondary" @click="startAddField">Add field</QdsButton>
            </div>
          </div>

          <div class="builder-field-list">
            <div v-for="field in visibleBuilderFields" :key="field.key" class="builder-field-row">
              <span class="field-type-icon"><AppIcon name="forms" /></span>
              <div>
                <strong>{{ field.label }}</strong>
                <span>{{ sectionLabel(field) }} - {{ field.type }} - {{ field.required ? 'Required' : 'Optional' }}</span>
              </div>
              <QdsBadge v-if="field.options?.length" tone="green">{{ field.options.length }} options</QdsBadge>
              <QdsButton variant="tiny" @click="editField(field)">Edit</QdsButton>
              <QdsButton v-if="field.id" variant="tiny" @click="removeField(field)">Remove</QdsButton>
            </div>
          </div>
        </section>

        <Teleport to="body">
          <div v-if="showFieldEditor" class="field-editor-modal" @click.self="closeFieldEditor">
          <div class="field-editor field-editor-modal__panel" role="dialog" aria-modal="true" aria-labelledby="field-editor-title" @click.stop>
          <div class="qds-card-header">
            <div>
              <h2 id="field-editor-title">{{ fieldDraft.id ? 'Edit field' : 'New field' }}</h2>
              <p>Changes are saved to the builder source.</p>
            </div>
            <QdsButton variant="tiny" @click="closeFieldEditor">Close</QdsButton>
          </div>
          <QdsField v-model="fieldDraft.label" label="Field label" />
          <QdsField v-model="fieldDraft.type" label="Field type" :options="fieldTypes" />
          <label class="qds-field">
            <span>Section</span>
            <select v-model="fieldDraft.section_key">
              <option v-for="section in builderSections" :key="section.key" :value="section.key">
                {{ section.label }}
              </option>
            </select>
          </label>
          <QdsField v-model="fieldDraft.group" label="Group" />

          <div class="editor-block">
            <div class="editor-block__header">
              <div>
                <h3>Validation rules</h3>
                <p>Select the checks that apply to this field.</p>
              </div>
            </div>
            <div class="validation-grid">
              <label v-for="rule in validationRuleChoices" :key="rule.value" class="rule-choice">
                <input
                  type="checkbox"
                  :checked="fieldDraft.validation_rules.includes(rule.value)"
                  @change="toggleValidationRule(rule.value)"
                >
                <span>{{ rule.label }}</span>
              </label>
            </div>
            <div class="inline-adder">
              <QdsField v-model="customValidationRule" label="Custom rule" />
              <QdsButton variant="secondary" @click="addCustomValidationRule">Add</QdsButton>
            </div>
            <div v-if="fieldDraft.validation_rules.length" class="chip-list">
              <span v-for="rule in fieldDraft.validation_rules" :key="rule" class="rule-chip">
                {{ readableRule(rule) }}
                <button type="button" @click="removeValidationRule(rule)">Remove</button>
              </span>
            </div>
          </div>

          <div v-if="fieldSupportsOptions" class="editor-block">
            <div v-if="requiresScoring" class="scoring-toggle">
              <QdsCheckbox v-model="fieldDraft.scorable" label="This field contributes to the assessment score" />
            </div>
            <div class="editor-block__header">
              <div>
                <h3>Answer options</h3>
                <p>Add labels, stored values, scores, and review flags without JSON.</p>
              </div>
              <QdsButton variant="tiny" @click="addOptionRow">Add option</QdsButton>
            </div>
            <div class="option-editor">
              <div v-for="(option, index) in fieldDraft.options" :key="option.local_id" class="option-row">
                <QdsField v-model="option.label" label="Label" />
                <QdsField v-model="option.value" label="Stored value" />
                <QdsField v-if="requiresScoring && fieldDraft.scorable" v-model="option.score_text" label="Score" type="number" />
                <QdsCheckbox v-model="option.requires_review" label="Review" />
                <button type="button" class="remove-row" @click="removeOptionRow(index)">Remove</button>
              </div>
            </div>
          </div>

          <div v-if="fieldDraft.type === 'yes_no_with_count'" class="editor-block">
            <div class="editor-block__header">
              <div>
                <h3>Count requirement</h3>
                <p>Show and require a number inside this same question when the selected answer matches.</p>
              </div>
            </div>
            <div class="condition-grid">
              <QdsField v-model="fieldDraft.count_label" label="Number input label" />
              <label class="qds-field">
                <span>Require number when answer is</span>
                <select v-model="fieldDraft.count_required_value">
                  <option v-for="option in fieldDraft.options" :key="option.local_id" :value="option.value">
                    {{ option.label || option.value }}
                  </option>
                </select>
              </label>
              <QdsField v-model="fieldDraft.count_minimum" label="Minimum number" type="number" />
            </div>
          </div>

          <div class="editor-block">
            <div class="editor-block__header">
              <div>
                <h3>Conditional display</h3>
                <p>Use this for yes/no follow-up values and "Other, specify" fields.</p>
              </div>
            </div>
            <QdsCheckbox v-model="fieldDraft.show_when_enabled" label="Only show this field when another answer matches" />
            <div v-if="fieldDraft.show_when_enabled" class="condition-grid">
              <label class="qds-field">
                <span>Question</span>
                <select v-model="fieldDraft.show_when_field">
                  <option value="">Choose field</option>
                  <option v-for="field in conditionFieldOptions" :key="field.key" :value="field.key">
                    {{ field.label }}
                  </option>
                </select>
              </label>
              <QdsField v-model="fieldDraft.show_when_operator" label="Condition" :options="conditionOperators" />
              <label v-if="showWhenOptionValues.length" class="qds-field">
                <span>Answer</span>
                <select v-model="fieldDraft.show_when_value">
                  <option v-for="option in showWhenOptionValues" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <QdsField v-else v-model="fieldDraft.show_when_value" label="Answer" />
            </div>
            <QdsCheckbox v-model="fieldDraft.require_when_enabled" label="Make this field required when another answer matches" />
            <div v-if="fieldDraft.require_when_enabled" class="condition-grid">
              <label class="qds-field">
                <span>Question</span>
                <select v-model="fieldDraft.require_when_field">
                  <option value="">Choose field</option>
                  <option v-for="field in conditionFieldOptions" :key="field.key" :value="field.key">
                    {{ field.label }}
                  </option>
                </select>
              </label>
              <label v-if="requireWhenOptionValues.length" class="qds-field">
                <span>Answer</span>
                <select v-model="fieldDraft.require_when_value">
                  <option v-for="option in requireWhenOptionValues" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <QdsField v-else v-model="fieldDraft.require_when_value" label="Answer" />
            </div>
          </div>

          <div class="field-editor-checks">
            <QdsCheckbox v-model="fieldDraft.required" label="Required" />
            <QdsCheckbox v-model="fieldDraft.readonly" label="Read only" />
          </div>
          <QdsButton icon="save" block @click="saveField">Save field</QdsButton>
          </div>
          </div>
        </Teleport>
      </div>

      <div v-else-if="activeTab === 'rules'" class="builder-panel">
        <div class="qds-card-header">
          <div>
            <h2>Rules and validation</h2>
            <p>Review generated validation, visibility, scoring, evidence, and checklist flags.</p>
          </div>
          <QdsButton icon="submissions" variant="secondary" @click="runRuleTest">Test rules</QdsButton>
        </div>
        <div class="rule-grid">
          <div v-for="rule in ruleSummary" :key="rule.label" class="rule-card">
            <strong>{{ rule.value }}</strong>
            <span>{{ rule.label }}</span>
          </div>
        </div>
        <div class="rule-test-grid">
          <QdsField v-model="ruleTestInput" label="Sample answers JSON" textarea :rows="9" />
          <pre class="rule-test-output">{{ ruleTestOutput }}</pre>
        </div>
        <div class="review-flags">
          <h3>Checklist review flags</h3>
          <p v-for="flag in reviewFlags" :key="flag">{{ flag }}</p>
        </div>
      </div>

      <div v-else-if="activeTab === 'preview'" class="builder-panel">
        <div class="qds-card-header">
          <div>
            <h2>Mobile preview</h2>
            <p>Representative ODK/mobile assessment flow from builder source.</p>
          </div>
        </div>
        <div class="mobile-preview">
          <div class="mobile-preview__chrome">
            <strong>{{ formModel.title }}</strong>
            <span>{{ builderFields.length }} fields available offline after publish</span>
          </div>
          <label v-for="field in previewFields" :key="field.key" class="preview-field">
            <span>{{ field.label }}</span>
            <input :placeholder="field.type" readonly>
          </label>
        </div>
      </div>

      <div v-else class="builder-panel">
        <div class="qds-card-header">
          <div>
            <h2>Versions</h2>
            <p>Track generated mobile snapshots from published builder records.</p>
          </div>
        </div>
        <div class="version-list">
          <div v-for="version in versionRows" :key="version.label" class="version-row">
            <div>
              <strong>{{ version.label }}</strong>
              <span>{{ version.updated }}</span>
            </div>
            <QdsBadge :tone="version.tone">{{ version.status }}</QdsBadge>
          </div>
        </div>
      </div>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsCheckbox from '../components/qds/QdsCheckbox.vue';
import QdsField from '../components/qds/QdsField.vue';
import QdsIconButton from '../components/qds/QdsIconButton.vue';
import QdsSwitch from '../components/qds/QdsSwitch.vue';
import QdsTabs from '../components/qds/QdsTabs.vue';
import {
  createFormField,
  deleteFormField,
  deleteFormSection,
  type AssessmentForm,
  type BuilderField,
  type BuilderOption,
  type BuilderSection,
  createFormSection,
  formatDate,
  getAssessmentForm,
  publishAssessmentForm,
  statusTone,
  testFormRules,
  titleCase,
  updateAssessmentForm,
  updateFormField,
} from '../services/forms';

const props = defineProps<{
  slug: string;
}>();

defineEmits<{
  back: [];
}>();

const builderTabs = [
  { label: 'Overview', value: 'overview' },
  { label: 'Builder', value: 'builder' },
  { label: 'Rules', value: 'rules' },
  { label: 'Preview', value: 'preview' },
  { label: 'Versions', value: 'versions' },
];

const fieldTypes = [
  { label: 'Text', value: 'text' },
  { label: 'Long text', value: 'textarea' },
  { label: 'Number', value: 'number' },
  { label: 'Currency', value: 'currency' },
  { label: 'Date', value: 'date' },
  { label: 'Yes/No', value: 'yes_no' },
  { label: 'Yes/No with count', value: 'yes_no_with_count' },
  { label: 'Option list', value: 'select' },
  { label: 'Reference lookup', value: 'reference_select' },
  { label: 'GPS location', value: 'gps' },
  { label: 'Photos', value: 'photo_collection' },
  { label: 'Files/documents', value: 'file_collection' },
  { label: 'Checkbox', value: 'checkbox' },
  { label: 'Read only', value: 'readonly' },
];
const optionFieldTypes = ['yes_no', 'yes_no_with_count', 'select'];
const conditionOperators = ['equals', 'not_equals'];
const validationRuleChoices = [
  { label: 'Text', value: 'string' },
  { label: 'Whole number', value: 'integer' },
  { label: 'Number', value: 'numeric' },
  { label: 'Date', value: 'date' },
  { label: 'Today or earlier', value: 'before_or_equal:today' },
  { label: 'Minimum 0', value: 'min:0' },
  { label: 'Minimum 1', value: 'min:1' },
  { label: 'Maximum 30 characters', value: 'max:30' },
  { label: 'Maximum 255 characters', value: 'max:255' },
  { label: 'Must be accepted/checked', value: 'accepted' },
  { label: 'List / file collection', value: 'array' },
];

const activeTab = ref('overview');
const description = ref('');
const targetAudience = ref('Facility assessment teams');
const defaultAssignment = ref('Any team member');
const offlineEnabled = ref(true);
const requiresScoring = ref(false);
const scoringMaxScore = ref('');
const selectedSectionKey = ref('assessment_metadata');
const showSectionCreator = ref(false);
const showFieldEditor = ref(false);
const notice = ref('');
const ruleTestInput = ref('{"doctors_available":"yes","total_doctors":2,"gps_coordinates":{"latitude":9.61,"longitude":6.55}}');
const ruleTestOutput = ref('Run a rule test to see validation output.');
const customValidationRule = ref('');

const emptyFieldDraft = () => ({
  id: undefined as number | undefined,
  label: '',
  type: 'text',
  section_key: selectedSectionKey.value,
  group: '',
  required: false,
  readonly: false,
  scorable: false,
  validation_rules: [] as string[],
  options: [] as OptionDraft[],
  show_when_enabled: false,
  show_when_field: '',
  show_when_operator: 'equals',
  show_when_value: '',
  require_when_enabled: false,
  require_when_field: '',
  require_when_value: '',
  count_label: 'Total number',
  count_required_value: 'yes',
  count_minimum: '1',
});

const fieldDraft = reactive(emptyFieldDraft());
type OptionDraft = BuilderOption & {
  local_id: string;
  score_text?: string;
  requires_review?: boolean;
};

const sectionDraft = reactive({
  label: '',
  key: '',
  description: '',
  is_repeatable: false,
  min_repeats: '',
  max_repeats: '',
});

const formModel = reactive<AssessmentForm>({
  slug: '',
  title: 'Loading form...',
  status: 'draft',
  current_version: null,
  sections: [],
  fields: [],
  builder_rules: [],
});

const builderSections = computed<BuilderSection[]>(() => {
  if (Array.isArray(formModel.sections) && formModel.sections.length > 0) {
    return [...formModel.sections].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  const sections = formModel.current_version?.sections;
  if (!Array.isArray(sections)) return [];

  return sections.map((section) => {
    const record = section as Record<string, unknown>;
    return {
      key: String(record.key),
      label: String(record.label),
      sort_order: Number(record.order ?? 0),
    };
  });
});

const builderFields = computed<BuilderField[]>(() => {
  if (Array.isArray(formModel.fields) && formModel.fields.length > 0) {
    return [...formModel.fields]
      .filter((field) => field.type !== 'signature')
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  const fields = formModel.current_version?.fields;
  if (!Array.isArray(fields)) return [];

  return fields.filter((field) => (field as Record<string, unknown>).type !== 'signature').map((field, index) => {
    const record = field as Record<string, unknown>;
    return {
      key: String(record.key ?? `field-${index}`),
      label: String(record.label ?? 'Untitled field'),
      type: String(record.type ?? 'text'),
      section_key: String(record.section ?? ''),
      required: Boolean(record.required),
      validation_rules: Array.isArray(record.rules) ? record.rules.map(String) : [],
      options: Array.isArray(record.options) ? record.options as BuilderField['options'] : [],
      sort_order: index + 1,
    };
  });
});

const visibleBuilderFields = computed(() => {
  if (!selectedSectionKey.value) return builderFields.value;
  return builderFields.value.filter((field) => sectionKey(field) === selectedSectionKey.value);
});

const fieldSupportsOptions = computed(() => optionFieldTypes.includes(fieldDraft.type));
const conditionFieldOptions = computed(() => builderFields.value.filter((field) => field.id !== fieldDraft.id));
const showWhenOptionValues = computed(() => optionsForField(fieldDraft.show_when_field));
const requireWhenOptionValues = computed(() => optionsForField(fieldDraft.require_when_field));

const previewFields = computed(() => builderFields.value.slice(0, 8));

const ruleSummary = computed(() => {
  const currentVersion = formModel.current_version;
  const count = (value: unknown) => (Array.isArray(value) ? value.length : 0);
  const builderRules = formModel.builder_rules ?? [];

  return [
    { label: 'Builder fields', value: builderFields.value.length },
    { label: 'Validation rules', value: builderRules.filter((rule) => rule.type === 'validation').length || count(currentVersion?.validation_rules) },
    { label: 'Visibility rules', value: builderRules.filter((rule) => rule.type === 'visibility').length || count(currentVersion?.visibility_rules) },
    { label: 'Scoring fields', value: builderFields.value.filter((field) => isFieldScorable(field)).length || count(currentVersion?.scoring_rules) },
  ];
});

const reviewFlags = computed(() => {
  const builderFlags = (formModel.builder_rules ?? []).filter((rule) => rule.type === 'review_flag');
  if (builderFlags.length > 0) {
    return builderFlags.map((rule) => rule.message ?? rule.name ?? 'Review needed');
  }

  const flags = formModel.current_version?.review_flags;
  if (!Array.isArray(flags) || flags.length === 0) return ['No checklist review flags recorded.'];

  return flags.slice(0, 8).map((flag) => {
    const record = flag as Record<string, unknown>;
    return String(record.reason ?? record.field ?? 'Review needed');
  });
});

const versionRows = computed(() => {
  const versions = formModel.versions;
  if (!Array.isArray(versions) || versions.length === 0) {
    return [{
      label: `Version ${formModel.current_version?.version ?? 1}`,
      updated: formatDate(formModel.current_version?.updated_at ?? formModel.updated_at),
      status: titleCase(formModel.current_version?.status ?? formModel.status),
      tone: statusTone(formModel.current_version?.status ?? formModel.status),
    }];
  }

  return versions.map((version) => ({
    label: `Version ${version.version ?? 1}`,
    updated: formatDate(version.updated_at ?? version.published_at),
    status: titleCase(version.status),
    tone: statusTone(version.status),
  }));
});

const sectionKey = (field: BuilderField) => field.section?.key ?? field.section_key ?? builderSections.value.find((section) => section.id === field.assessment_form_section_id)?.key ?? '';
const sectionLabel = (field: BuilderField) => builderSections.value.find((section) => section.key === sectionKey(field))?.label ?? sectionKey(field) ?? 'General';
const sectionFieldCount = (key: string) => builderFields.value.filter((field) => sectionKey(field) === key).length;
const slugifyKey = (value: string) => value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
const hasScoredOptions = (field: BuilderField) => Boolean(field.options?.some((option) => option.score !== null && option.score !== undefined));
const isFieldScorable = (field: BuilderField) => requiresScoring.value && Boolean(field.settings?.scorable ?? hasScoredOptions(field));

const optionsForField = (fieldKey: string): BuilderOption[] => {
  if (!fieldKey) return [];
  const field = builderFields.value.find((item) => item.key === fieldKey);
  return field?.options ?? [];
};

const toOptionDraft = (option?: Partial<BuilderOption>): OptionDraft => ({
  local_id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
  id: option?.id,
  label: option?.label ?? '',
  value: option?.value ?? '',
  score: option?.score ?? null,
  score_text: option?.score === null || option?.score === undefined ? '' : String(option.score),
  requires_review: Boolean(option?.requires_review ?? option?.needs_review),
});

const optionDraftsToPayload = (): BuilderOption[] => {
  const seenValues = new Set<string>();

  return fieldDraft.options
    .map((option) => {
    const scoreText = String(option.score_text ?? '').trim();
    const score = requiresScoring.value && fieldDraft.scorable && scoreText !== '' ? Number(scoreText) : null;

    return {
      label: option.label.trim(),
      value: (option.value.trim() || slugifyKey(option.label)),
      score,
      requires_review: Boolean(option.requires_review),
    };
    })
    .filter((option) => option.label !== '' && option.value !== '')
    .filter((option) => {
      const valueKey = option.value.toLowerCase();
      if (seenValues.has(valueKey)) return false;
      seenValues.add(valueKey);
      return true;
    });
};

const readableRule = (value: string) => validationRuleChoices.find((rule) => rule.value === value)?.label ?? value;

const toggleValidationRule = (value: string) => {
  if (fieldDraft.validation_rules.includes(value)) {
    removeValidationRule(value);
    return;
  }

  fieldDraft.validation_rules.push(value);
};

const removeValidationRule = (value: string) => {
  fieldDraft.validation_rules = fieldDraft.validation_rules.filter((rule) => rule !== value);
};

const addCustomValidationRule = () => {
  const value = customValidationRule.value.trim();
  if (!value || fieldDraft.validation_rules.includes(value)) return;

  fieldDraft.validation_rules.push(value);
  customValidationRule.value = '';
};

const addOptionRow = () => {
  fieldDraft.options.push(toOptionDraft());
};

const removeOptionRow = (index: number) => {
  fieldDraft.options.splice(index, 1);
};

const defaultOptionsForType = (type: string): OptionDraft[] => {
  if (type === 'yes_no' || type === 'yes_no_with_count') {
    return [
      toOptionDraft({ label: 'Yes', value: 'yes', score: 2 }),
      toOptionDraft({ label: 'No', value: 'no', score: 0 }),
    ];
  }

  return [];
};

const resetSectionDraft = () => {
  Object.assign(sectionDraft, {
    label: '',
    key: '',
    description: '',
    is_repeatable: false,
    min_repeats: '',
    max_repeats: '',
  });
};

const applyForm = (form: AssessmentForm) => {
  Object.assign(formModel, form);
  description.value = form.description ?? '';
  targetAudience.value = form.target_audience ?? 'Facility assessment teams';
  offlineEnabled.value = form.current_version?.offline_enabled ?? true;
  requiresScoring.value = Boolean(form.requires_scoring ?? form.current_version?.requires_scoring);
  scoringMaxScore.value = form.scoring_max_score === null || form.scoring_max_score === undefined
    ? String(form.current_version?.scoring_max_score ?? '')
    : String(form.scoring_max_score);

  if (builderSections.value.length > 0 && !builderSections.value.some((section) => section.key === selectedSectionKey.value)) {
    selectedSectionKey.value = builderSections.value[0].key;
  }

};

const loadForm = async () => {
  applyForm(await getAssessmentForm(props.slug));
};

const saveOverview = async () => {
  applyForm(await updateAssessmentForm(formModel.slug, {
    title: formModel.title,
    description: description.value,
    target_audience: targetAudience.value,
    requires_scoring: requiresScoring.value,
    scoring_max_score: requiresScoring.value && scoringMaxScore.value !== '' ? Number(scoringMaxScore.value) : null,
  }));
  notice.value = 'Draft details saved.';
};

const publishForm = async () => {
  applyForm(await publishAssessmentForm(formModel.slug));
  notice.value = 'Published a new mobile snapshot from the builder source.';
};

const saveSection = async () => {
  if (!sectionDraft.label.trim()) {
    notice.value = 'Section name is required.';
    return;
  }

  const section = await createFormSection(formModel.slug, {
    label: sectionDraft.label.trim(),
    key: sectionDraft.key.trim() || undefined,
    description: sectionDraft.description.trim() || null,
    is_repeatable: sectionDraft.is_repeatable,
    min_repeats: sectionDraft.is_repeatable && sectionDraft.min_repeats !== '' ? Number(sectionDraft.min_repeats) : null,
    max_repeats: sectionDraft.is_repeatable && sectionDraft.max_repeats !== '' ? Number(sectionDraft.max_repeats) : null,
  });

  await loadForm();
  selectedSectionKey.value = section.key;
  showSectionCreator.value = false;
  resetSectionDraft();
  notice.value = 'Section created. You can now add fields to it.';
};

const startAddField = () => {
  Object.assign(fieldDraft, emptyFieldDraft(), {
    section_key: selectedSectionKey.value || builderSections.value[0]?.key || '',
  });
  showFieldEditor.value = true;
};

const editField = (field: BuilderField) => {
  Object.assign(fieldDraft, {
    id: field.id,
    label: field.label,
    type: field.type,
    section_key: sectionKey(field),
    group: field.group ?? '',
    required: Boolean(field.required),
    readonly: Boolean(field.readonly),
    scorable: isFieldScorable(field),
    validation_rules: [...(field.validation_rules ?? [])],
    options: (field.options ?? []).map((option) => toOptionDraft(option)),
    show_when_enabled: Boolean(field.display_logic?.field),
    show_when_field: String(field.display_logic?.field ?? ''),
    show_when_operator: String(field.display_logic?.operator ?? 'equals'),
    show_when_value: String(field.display_logic?.value ?? ''),
    require_when_enabled: Boolean(field.required_logic && Object.keys(field.required_logic).length > 0),
    require_when_field: field.required_logic ? String(Object.keys(field.required_logic)[0] ?? '') : '',
    require_when_value: field.required_logic ? String(Object.values(field.required_logic)[0] ?? '') : '',
    count_label: String(field.settings?.count_label ?? 'Total number'),
    count_required_value: String(field.settings?.count_required_value ?? 'yes'),
    count_minimum: String(field.settings?.count_minimum ?? '1'),
  });
  showFieldEditor.value = true;
};

const saveField = async () => {
  const options = fieldSupportsOptions.value ? optionDraftsToPayload() : [];
  const payload: Partial<BuilderField> = {
    label: fieldDraft.label,
    type: fieldDraft.type,
    section_key: fieldDraft.section_key,
    group: fieldDraft.group || null,
    required: fieldDraft.required,
    readonly: fieldDraft.readonly,
    validation_rules: [...fieldDraft.validation_rules],
    display_logic: fieldDraft.show_when_enabled && fieldDraft.show_when_field
      ? {
          field: fieldDraft.show_when_field,
          operator: fieldDraft.show_when_operator,
          value: fieldDraft.show_when_value,
        }
      : null,
    required_logic: fieldDraft.require_when_enabled && fieldDraft.require_when_field
      ? {
          [fieldDraft.require_when_field]: fieldDraft.require_when_value,
        }
      : null,
    options,
  };

  const settings: Record<string, unknown> = {};

  if (fieldSupportsOptions.value) {
    settings.scorable = requiresScoring.value && fieldDraft.scorable;
  }

  if (fieldDraft.type === 'yes_no_with_count') {
    Object.assign(settings, {
      count_label: fieldDraft.count_label || 'Total number',
      count_required_value: fieldDraft.count_required_value || 'yes',
      count_rules: ['integer', `min:${fieldDraft.count_minimum || 1}`],
      count_minimum: Number(fieldDraft.count_minimum || 1),
    });
  }

  if (Object.keys(settings).length > 0) {
    payload.settings = settings;
  }

  if (fieldDraft.id) {
    await updateFormField(formModel.slug, fieldDraft.id, payload);
  } else {
    await createFormField(formModel.slug, payload);
  }

  await loadForm();
  notice.value = 'Field saved to builder source.';
  showFieldEditor.value = false;
};

const removeField = async (field: BuilderField) => {
  if (!field.id || !window.confirm(`Remove "${field.label}" from this form?`)) return;

  await deleteFormField(formModel.slug, field.id);
  await loadForm();
  notice.value = 'Field removed from the builder.';
};

const removeSection = async (section: BuilderSection) => {
  if (!section.id || !window.confirm(`Remove "${section.label}" and all its fields?`)) return;

  await deleteFormSection(formModel.slug, section.id);
  await loadForm();
  notice.value = 'Section and its fields removed from the builder.';
};

const closeFieldEditor = () => {
  showFieldEditor.value = false;
};

const runRuleTest = async () => {
  const answers = JSON.parse(ruleTestInput.value || '{}') as Record<string, unknown>;
  const result = await testFormRules(formModel.slug, answers);
  ruleTestOutput.value = JSON.stringify(result, null, 2);
};

onMounted(loadForm);
watch(() => props.slug, loadForm);
watch(() => sectionDraft.label, (label) => {
  if (!sectionDraft.key.trim()) {
    sectionDraft.key = slugifyKey(label);
  }
});
watch(() => fieldDraft.type, (type) => {
  if ((type === 'yes_no' || type === 'yes_no_with_count') && fieldDraft.options.length === 0) {
    fieldDraft.options = defaultOptionsForType(type);
  }

  if (!optionFieldTypes.includes(type)) {
    fieldDraft.options = [];
  }
});
</script>

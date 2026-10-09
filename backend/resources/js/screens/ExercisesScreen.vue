<template>
  <section class="screen-stack">
    <AppPageHeader title="Assessment exercises" subtitle="Create quarterly, monthly, and custom assessment windows">
      <template #actions>
        <QdsButton variant="secondary" icon="clipboard" @click="$emit('manage-assignments')">Assignment manager</QdsButton>
        <QdsButton icon="calendar" @click="openModal()">New exercise</QdsButton>
      </template>
    </AppPageHeader>

    <div v-if="notice" class="builder-notice">{{ notice }}</div>

    <div class="metric-grid">
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="calendar" /></span>
        <div><strong>{{ stats.total }}</strong><span>Total exercises</span><small>All assessment windows</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>{{ stats.active }}</strong><span>Active</span><small>Accepting submissions</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="clock" /></span>
        <div><strong>{{ stats.scheduled }}</strong><span>Scheduled</span><small>Upcoming windows</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="clipboard" /></span>
        <div><strong>{{ stats.draft }}</strong><span>Draft</span><small>Still being prepared</small></div>
      </QdsCard>
    </div>

    <div class="qds-toolbar">
      <QdsSearch v-model="filters.q" compact placeholder="Search exercises..." />
      <QdsField v-model="filters.status" label="Status" :options="statusFilterOptions" />
      <QdsField v-model="filters.period_type" label="Period" :options="periodFilterOptions" />
      <QdsField v-model="filters.year" label="Year" type="number" />
      <QdsButton variant="secondary" @click="loadExercises">Refresh</QdsButton>
    </div>

    <QdsCard class="qds-table-card">
      <div class="qds-table exercises-table">
        <div class="qds-table__head">
          <span>Exercise</span>
          <span>Period</span>
          <span>Form version</span>
          <span>Window</span>
          <span>Status</span>
          <span>Actions</span>
        </div>
        <div v-for="exercise in exercises" :key="exercise.uuid" class="qds-table__row">
          <span class="table-title">
            {{ exercise.name }}
            <small>{{ exercise.code }}</small>
          </span>
          <span>
            {{ periodLabel(exercise) }}
            <small>{{ titleCase(exercise.period_type) }} {{ exercise.year }}</small>
          </span>
          <span>
            {{ exercise.form?.title || 'No form' }}
            <small>Version {{ exercise.form_version?.version || 'n/a' }}</small>
          </span>
          <span>
            {{ formatDate(exercise.starts_at) }}
            <small>to {{ formatDate(exercise.ends_at) }}</small>
          </span>
          <QdsBadge :tone="statusTone(exercise.status)">{{ titleCase(exercise.status) }}</QdsBadge>
          <span class="row-actions">
            <QdsButton variant="tiny" @click="$emit('manage-assignments', exercise.id)">Assign</QdsButton>
            <QdsButton variant="tiny" @click="openModal(exercise)">Edit</QdsButton>
          </span>
        </div>
        <div v-if="!loading && exercises.length === 0" class="table-empty">
          No assessment exercises match the current filters.
        </div>
        <div v-if="loading" class="table-empty">Loading assessment exercises...</div>
      </div>
    </QdsCard>

    <div class="pagination-row">
      <QdsButton variant="secondary" :disabled="page <= 1" @click="page -= 1">Previous</QdsButton>
      <span>Page {{ page }} of {{ lastPage }}</span>
      <QdsButton variant="secondary" :disabled="page >= lastPage" @click="page += 1">Next</QdsButton>
    </div>

    <div v-if="showModal" class="field-editor-modal" @click.self="closeModal">
      <div class="field-editor field-editor-modal__panel exercise-modal-panel" role="dialog" aria-modal="true" @click.stop>
        <div class="builder-heading">
          <div>
            <h2>{{ editingUuid ? 'Edit exercise' : 'New exercise' }}</h2>
            <p>Choose a published form version and define when submissions are accepted.</p>
          </div>
          <QdsButton variant="secondary" @click="closeModal">Close</QdsButton>
        </div>

        <div class="exercise-form-grid">
          <QdsField v-model="draft.name" label="Exercise name" />
          <QdsField v-model="draft.description" label="Description" textarea />
          <div class="two-column-fields">
            <QdsField v-model="draft.period_type" label="Period type" :options="periodOptions" />
            <QdsField v-model="draft.year" label="Year" type="number" />
          </div>
          <div v-if="draft.period_type === 'quarter'" class="two-column-fields">
            <QdsField v-model="draft.quarter" label="Quarter" :options="quarterOptions" />
            <QdsField v-model="draft.status" label="Status" :options="statusOptions" />
          </div>
          <div v-else-if="draft.period_type === 'month'" class="two-column-fields">
            <QdsField v-model="draft.month" label="Month" :options="monthOptions" />
            <QdsField v-model="draft.status" label="Status" :options="statusOptions" />
          </div>
          <QdsField v-else v-model="draft.status" label="Status" :options="statusOptions" />
          <div class="two-column-fields">
            <QdsField v-model="draft.starts_at" label="Start date" type="date" />
            <QdsField v-model="draft.ends_at" label="End date" type="date" />
          </div>
          <div class="two-column-fields">
            <QdsField v-model="draft.assessment_form_id" label="Published form" :options="formOptionsForSelect" />
            <QdsField v-model="draft.assessment_form_version_id" label="Form version" :options="versionOptionsForSelect" />
          </div>
        </div>

        <div v-if="formOptions.length === 0" class="table-empty">
          Publish a form before creating an assessment exercise.
        </div>

        <QdsButton icon="save" block :disabled="formOptions.length === 0" @click="saveExercise">
          {{ editingUuid ? 'Save exercise' : 'Create exercise' }}
        </QdsButton>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import QdsSearch from '../components/qds/QdsSearch.vue';
import {
  createAssessmentExercise,
  formatDate,
  loadAssessmentExercises,
  statusTone,
  titleCase,
  updateAssessmentExercise,
  type AssessmentExercise,
  type ExerciseFormOption,
  type ExercisePayload,
  type ExercisePeriodType,
} from '../services/exercises';

defineEmits<{
  'manage-assignments': [exerciseId?: number];
}>();

const currentYear = new Date().getFullYear();
const exercises = ref<AssessmentExercise[]>([]);
const formOptions = ref<ExerciseFormOption[]>([]);
const loading = ref(false);
const notice = ref('');
const page = ref(1);
const lastPage = ref(1);
const showModal = ref(false);
const editingUuid = ref<string | null>(null);
const stats = reactive({ total: 0, draft: 0, scheduled: 0, active: 0, closed: 0 });
const filters = reactive({
  q: '',
  status: '',
  period_type: '',
  year: '',
});

const draft = reactive({
  name: '',
  description: '',
  period_type: 'quarter' as ExercisePeriodType,
  year: String(currentYear),
  quarter: '1',
  month: '1',
  starts_at: `${currentYear}-01-01`,
  ends_at: `${currentYear}-03-31`,
  assessment_form_id: '',
  assessment_form_version_id: '',
  status: 'draft',
});

const statusFilterOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Scheduled', value: 'scheduled' },
  { label: 'Active', value: 'active' },
  { label: 'Closed', value: 'closed' },
  { label: 'Archived', value: 'archived' },
];

const statusOptions = statusFilterOptions.filter((option) => option.value !== '');

const periodFilterOptions = [
  { label: 'All periods', value: '' },
  { label: 'Quarter', value: 'quarter' },
  { label: 'Month', value: 'month' },
  { label: 'Custom', value: 'custom' },
];

const periodOptions = periodFilterOptions.filter((option) => option.value !== '');

const quarterOptions = [
  { label: 'Q1', value: '1' },
  { label: 'Q2', value: '2' },
  { label: 'Q3', value: '3' },
  { label: 'Q4', value: '4' },
];

const monthOptions = Array.from({ length: 12 }, (_, index) => {
  const value = String(index + 1);
  const label = new Intl.DateTimeFormat('en-GB', { month: 'long' }).format(new Date(2026, index, 1));
  return { label, value };
});

const formOptionsForSelect = computed(() => [
  { label: 'Select form', value: '' },
  ...formOptions.value.map((form) => ({ label: form.title, value: String(form.id) })),
]);

const selectedForm = computed(() => formOptions.value.find((form) => String(form.id) === draft.assessment_form_id));

const versionOptionsForSelect = computed(() => [
  { label: 'Select version', value: '' },
  ...(selectedForm.value?.versions || []).map((version) => ({ label: `Version ${version.version}`, value: String(version.id) })),
]);

const loadExercises = async () => {
  loading.value = true;
  notice.value = '';

  try {
    const payload = await loadAssessmentExercises({
      ...filters,
      page: page.value,
      per_page: 20,
    });

    exercises.value = payload.exercises.data;
    lastPage.value = payload.exercises.last_page || 1;
    formOptions.value = payload.form_options;
    Object.assign(stats, payload.stats);
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to load exercises.';
  } finally {
    loading.value = false;
  }
};

const resetDraft = () => {
  Object.assign(draft, {
    name: '',
    description: '',
    period_type: 'quarter',
    year: String(currentYear),
    quarter: '1',
    month: '1',
    starts_at: `${currentYear}-01-01`,
    ends_at: `${currentYear}-03-31`,
    assessment_form_id: formOptions.value[0] ? String(formOptions.value[0].id) : '',
    assessment_form_version_id: formOptions.value[0]?.versions[0] ? String(formOptions.value[0].versions[0].id) : '',
    status: 'draft',
  });
};

const openModal = (exercise?: AssessmentExercise) => {
  if (exercise) {
    editingUuid.value = exercise.uuid;
    Object.assign(draft, {
      name: exercise.name,
      description: exercise.description || '',
      period_type: exercise.period_type,
      year: String(exercise.year),
      quarter: exercise.quarter ? String(exercise.quarter) : '1',
      month: exercise.month ? String(exercise.month) : '1',
      starts_at: exercise.starts_at?.slice(0, 10),
      ends_at: exercise.ends_at?.slice(0, 10),
      assessment_form_id: String(exercise.assessment_form_id),
      assessment_form_version_id: String(exercise.assessment_form_version_id),
      status: exercise.status,
    });
  } else {
    editingUuid.value = null;
    resetDraft();
  }

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingUuid.value = null;
};

const saveExercise = async () => {
  if (!draft.name.trim() || !draft.assessment_form_id || !draft.assessment_form_version_id) {
    notice.value = 'Exercise name, published form, and form version are required.';
    return;
  }

  const payload: ExercisePayload = {
    name: draft.name.trim(),
    description: draft.description.trim() || null,
    period_type: draft.period_type,
    year: Number(draft.year),
    quarter: draft.period_type === 'quarter' ? Number(draft.quarter) : null,
    month: draft.period_type === 'month' ? Number(draft.month) : null,
    starts_at: draft.starts_at,
    ends_at: draft.ends_at,
    assessment_form_id: Number(draft.assessment_form_id),
    assessment_form_version_id: Number(draft.assessment_form_version_id),
    status: draft.status,
  };

  try {
    if (editingUuid.value) {
      await updateAssessmentExercise(editingUuid.value, payload);
      notice.value = 'Exercise updated.';
    } else {
      await createAssessmentExercise(payload);
      notice.value = 'Exercise created.';
    }

    closeModal();
    await loadExercises();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to save exercise.';
  }
};

const updatePeriodDates = () => {
  const year = Number(draft.year) || currentYear;
  if (draft.period_type === 'quarter') {
    const quarter = Number(draft.quarter) || 1;
    const startMonth = (quarter - 1) * 3 + 1;
    const endMonth = startMonth + 2;
    draft.starts_at = `${year}-${String(startMonth).padStart(2, '0')}-01`;
    draft.ends_at = `${year}-${String(endMonth).padStart(2, '0')}-${lastDay(year, endMonth)}`;
  }

  if (draft.period_type === 'month') {
    const month = Number(draft.month) || 1;
    draft.starts_at = `${year}-${String(month).padStart(2, '0')}-01`;
    draft.ends_at = `${year}-${String(month).padStart(2, '0')}-${lastDay(year, month)}`;
  }
};

const lastDay = (year: number, month: number) => String(new Date(year, month, 0).getDate()).padStart(2, '0');

const periodLabel = (exercise: AssessmentExercise): string => {
  if (exercise.period_type === 'quarter') return `Q${exercise.quarter}`;
  if (exercise.period_type === 'month' && exercise.month) return monthOptions[exercise.month - 1]?.label || `Month ${exercise.month}`;
  return 'Custom';
};

watch(() => ({ ...filters }), () => {
  page.value = 1;
  void loadExercises();
});

watch(page, () => {
  void loadExercises();
});

watch(() => [draft.period_type, draft.year, draft.quarter, draft.month], updatePeriodDates);

watch(selectedForm, (form) => {
  if (!form) {
    draft.assessment_form_version_id = '';
    return;
  }

  const currentVersionStillAvailable = form.versions.some((version) => String(version.id) === draft.assessment_form_version_id);
  if (!currentVersionStillAvailable) {
    draft.assessment_form_version_id = form.versions[0] ? String(form.versions[0].id) : '';
  }
});

onMounted(loadExercises);
</script>

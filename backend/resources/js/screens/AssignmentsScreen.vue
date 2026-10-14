<template>
  <section class="screen-stack">
    <AppPageHeader title="Assignment manager" subtitle="Assign assessment exercises to assessors, teams, and workspace targets">
      <template #actions>
        <QdsButton icon="plus" @click="openModal()">New assignment</QdsButton>
      </template>
    </AppPageHeader>

    <div v-if="notice" class="builder-notice">{{ notice }}</div>

    <div class="metric-grid">
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="clipboard" /></span>
        <div><strong>{{ stats.total }}</strong><span>Total assignments</span><small>Across all exercises</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="clock" /></span>
        <div><strong>{{ stats.in_progress }}</strong><span>In progress</span><small>Field work started</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="submissions" /></span>
        <div><strong>{{ stats.submitted }}</strong><span>Submitted</span><small>Awaiting review</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>{{ stats.reviewed }}</strong><span>Reviewed</span><small>Completed cycle</small></div>
      </QdsCard>
    </div>

    <div class="qds-toolbar">
      <QdsSearch v-model="filters.q" compact placeholder="Search facility, exercise, user, team..." />
      <QdsField v-model="filters.exercise_id" label="Exercise" :options="exerciseFilterOptions" />
      <QdsField v-model="filters.status" label="Status" :options="statusFilterOptions" />
      <QdsField v-model="filters.assignee_type" label="Assignee" :options="assigneeFilterOptions" />
      <QdsButton variant="secondary" @click="loadAssignments">Refresh</QdsButton>
    </div>

    <QdsCard class="qds-table-card">
      <div class="qds-table assignments-table">
        <div class="qds-table__head">
          <span>Facility</span>
          <span>Exercise</span>
          <span>Assignee</span>
          <span>Due</span>
          <span>Status</span>
          <span>Submissions</span>
          <span>Actions</span>
        </div>
        <div v-for="assignment in assignments" :key="assignment.uuid" class="qds-table__row">
          <span class="table-title">
            {{ assignment.facility?.name || assignment.target?.name || (assignment.target_type === 'no_target' ? 'No target' : 'Unknown target') }}
            <small>{{ assignment.facility?.source_code || assignment.target?.external_id || titleCase(assignment.target_type) }}</small>
          </span>
          <span>
            {{ assignment.exercise?.name || 'Unknown exercise' }}
            <small>{{ assignment.exercise?.code || 'No code' }}</small>
          </span>
          <span>
            {{ assigneeName(assignment) }}
            <small>{{ titleCase(assignment.assignee_type) }}</small>
          </span>
          <span>{{ formatDate(assignment.due_at) }}</span>
          <QdsBadge :tone="statusTone(assignment.status)">{{ titleCase(assignment.status) }}</QdsBadge>
          <span>{{ assignment.submissions_count || 0 }}</span>
          <span class="row-actions">
            <QdsButton variant="tiny" @click="openModal(assignment)">Edit</QdsButton>
            <QdsButton
              v-if="canCancel(assignment)"
              variant="tiny"
              @click="cancelAssignment(assignment)"
            >
              Cancel
            </QdsButton>
            <QdsButton
              v-if="canArchive(assignment)"
              variant="tiny"
              @click="archiveAssignment(assignment)"
            >
              Archive
            </QdsButton>
            <QdsButton
              v-if="canDelete(assignment)"
              variant="tiny"
              @click="deleteAssignment(assignment)"
            >
              Delete
            </QdsButton>
          </span>
        </div>
        <div v-if="!loading && assignments.length === 0" class="table-empty">
          No assignments match the current filters.
        </div>
        <div v-if="loading" class="table-empty">Loading assignments...</div>
      </div>
    </QdsCard>

    <div class="pagination-row">
      <QdsButton variant="secondary" :disabled="page <= 1" @click="page -= 1">Previous</QdsButton>
      <span>Page {{ page }} of {{ lastPage }}</span>
      <QdsButton variant="secondary" :disabled="page >= lastPage" @click="page += 1">Next</QdsButton>
    </div>

    <div v-if="showModal" class="field-editor-modal" @click.self="closeModal">
      <div class="field-editor field-editor-modal__panel assignment-modal-panel" role="dialog" aria-modal="true" @click.stop>
        <div class="builder-heading">
          <div>
            <h2>{{ editingUuid ? 'Edit assignment' : 'New assignment' }}</h2>
            <p>One assignment is created for each selected target.</p>
          </div>
          <QdsButton variant="secondary" @click="closeModal">Close</QdsButton>
        </div>

        <div class="assignment-form-grid">
          <QdsField v-model="draft.assessment_exercise_id" label="Exercise" :options="exerciseOptionsForSelect" :disabled="Boolean(editingUuid)" />
          <QdsField v-model="draft.target_type" label="Target type" :options="targetTypeOptions" :disabled="Boolean(editingUuid)" />
          <div class="two-column-fields">
            <QdsField v-model="draft.assignee_type" label="Assign to" :options="assigneeTypeOptions" />
            <QdsField v-model="draft.assignee_id" label="Assignee" :options="assigneeOptionsForSelect" />
          </div>
          <div class="two-column-fields">
            <QdsField v-model="draft.status" label="Status" :options="statusOptions" />
            <QdsField v-model="draft.due_at" label="Due date" type="date" />
          </div>
          <label class="rule-choice assignment-toggle">
            <input v-model="draft.allow_duplicate_submission" type="checkbox">
            <span>Allow duplicate facility submissions for this exercise</span>
          </label>
        </div>

        <div v-if="!editingUuid && draft.target_type === 'facility'" class="member-picker assignment-facility-picker">
          <div class="builder-heading">
            <div>
              <h3>Facilities</h3>
              <p>{{ draft.facility_ids.length }} selected</p>
            </div>
            <QdsSearch v-model="facilitySearch" compact placeholder="Search facilities..." />
          </div>
          <label v-for="facility in filteredFacilities" :key="facility.id" class="member-choice">
            <input
              type="checkbox"
              :checked="draft.facility_ids.includes(facility.id)"
              @change="toggleFacility(facility.id)"
            >
            <span>{{ facility.name }}</span>
            <small>{{ facility.lga?.name || 'No LGA' }} / {{ facility.ward?.name || 'No ward' }}</small>
          </label>
        </div>

        <div v-if="!editingUuid && draft.target_type && draft.target_type !== 'facility' && draft.target_type !== 'no_target'" class="member-picker assignment-facility-picker">
          <div class="builder-heading"><div><h3>{{ titleCase(draft.target_type) }} targets</h3><p>{{ draft.target_ids.length }} selected</p></div><QdsSearch v-model="facilitySearch" compact placeholder="Search targets..." /></div>
          <label v-for="target in filteredTargets" :key="target.id" class="member-choice">
            <input type="checkbox" :checked="draft.target_ids.includes(target.id)" @change="toggleTarget(target.id)">
            <span>{{ target.name }}</span><small>{{ target.external_id || 'No external ID' }}</small>
          </label>
        </div>

        <div v-if="editingUuid" class="submission-summary">
          <strong>{{ editingFacilityName }}</strong>
          <span>Target cannot be changed after assignment creation. Create a new assignment for another target.</span>
        </div>

        <QdsButton icon="save" block @click="saveAssignment">
          {{ editingUuid ? 'Save assignment' : 'Create assignments' }}
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
  createAssessmentAssignments,
  archiveAssessmentAssignment,
  cancelAssessmentAssignment,
  deleteAssessmentAssignment,
  formatDate,
  loadAssessmentAssignments,
  statusTone,
  titleCase,
  updateAssessmentAssignment,
  type AssessmentAssignment,
  type AssignmentAssigneeType,
  type AssignmentOptions,
} from '../services/assignments';

const props = withDefaults(defineProps<{
  initialExerciseId?: string | number;
}>(), {
  initialExerciseId: '',
});

const assignments = ref<AssessmentAssignment[]>([]);
const options = reactive<AssignmentOptions>({
  exercises: [],
  users: [],
  teams: [],
  facilities: [],
  target_types: [],
  targets: [],
});
const loading = ref(false);
const notice = ref('');
const page = ref(1);
const lastPage = ref(1);
const showModal = ref(false);
const editingUuid = ref<string | null>(null);
const editingFacilityName = ref('');
const facilitySearch = ref('');
const stats = reactive({ total: 0, assigned: 0, in_progress: 0, submitted: 0, reviewed: 0 });
const filters = reactive({
  q: '',
  status: '',
  exercise_id: props.initialExerciseId ? String(props.initialExerciseId) : '',
  assignee_type: '',
});

const draft = reactive({
  assessment_exercise_id: '',
  assignee_type: 'user' as AssignmentAssigneeType,
  assignee_id: '',
  facility_ids: [] as number[],
  target_type: 'facility',
  target_ids: [] as number[],
  status: 'assigned',
  allow_duplicate_submission: false,
  due_at: '',
});

const statusFilterOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Assigned', value: 'assigned' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Reviewed', value: 'reviewed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Expired', value: 'expired' },
];

const statusOptions = statusFilterOptions.filter((option) => option.value !== '');
const assigneeFilterOptions = [
  { label: 'All assignees', value: '' },
  { label: 'Users', value: 'user' },
  { label: 'Teams', value: 'team' },
];
const assigneeTypeOptions = assigneeFilterOptions.filter((option) => option.value !== '');

const exerciseFilterOptions = computed(() => [
  { label: 'All exercises', value: '' },
  ...options.exercises.map((exercise) => ({ label: exercise.name, value: String(exercise.id) })),
]);

const exerciseOptionsForSelect = computed(() => [
  { label: 'Select exercise', value: '' },
  ...options.exercises.map((exercise) => ({ label: `${exercise.name} (${exercise.code})`, value: String(exercise.id) })),
]);

const targetTypeOptions = computed(() => options.target_types.map((type) => ({ label: titleCase(type), value: type })));

const assigneeOptionsForSelect = computed(() => {
  const source = draft.assignee_type === 'team' ? options.teams : options.users;
  return [
    { label: `Select ${draft.assignee_type}`, value: '' },
    ...source.map((item) => ({ label: item.name, value: String(item.id) })),
  ];
});

const filteredFacilities = computed(() => {
  const needle = facilitySearch.value.trim().toLowerCase();
  if (!needle) return options.facilities;

  return options.facilities.filter((facility) => [
    facility.name,
    facility.source_code,
    facility.lga?.name,
    facility.ward?.name,
  ].some((value) => (value ?? '').toLowerCase().includes(needle)));
});

const filteredTargets = computed(() => {
  const needle = facilitySearch.value.trim().toLowerCase();
  return options.targets
    .filter((target) => target.type === draft.target_type)
    .filter((target) => !needle || [target.name, target.external_id].some((value) => (value ?? '').toLowerCase().includes(needle)));
});

const loadAssignments = async () => {
  loading.value = true;
  notice.value = '';

  try {
    const payload = await loadAssessmentAssignments({
      ...filters,
      page: page.value,
      per_page: 20,
    });
    assignments.value = payload.assignments.data;
    lastPage.value = payload.assignments.last_page || 1;
    Object.assign(options, payload.options);
    Object.assign(stats, payload.stats);
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to load assignments.';
  } finally {
    loading.value = false;
  }
};

const resetDraft = () => {
  Object.assign(draft, {
    assessment_exercise_id: options.exercises[0] ? String(options.exercises[0].id) : '',
    assignee_type: 'user',
    assignee_id: options.users[0] ? String(options.users[0].id) : '',
    facility_ids: [],
    target_type: options.exercises[0]?.target_type || 'facility',
    target_ids: [],
    status: 'assigned',
    allow_duplicate_submission: false,
    due_at: options.exercises[0]?.ends_at?.slice(0, 10) || '',
  });
  facilitySearch.value = '';
};

const openModal = (assignment?: AssessmentAssignment) => {
  if (assignment) {
    editingUuid.value = assignment.uuid;
    editingFacilityName.value = assignment.facility?.name || 'Selected facility';
    Object.assign(draft, {
      assessment_exercise_id: String(assignment.assessment_exercise_id),
      assignee_type: assignment.assignee_type,
      assignee_id: String(assignment.assignee_type === 'team' ? assignment.odk_team_id : assignment.odk_user_id),
      facility_ids: [assignment.reference_facility_id],
      target_type: assignment.target_type || 'facility',
      target_ids: assignment.odk_target_id ? [assignment.odk_target_id] : [],
      status: assignment.status,
      allow_duplicate_submission: assignment.allow_duplicate_submission,
      due_at: assignment.due_at?.slice(0, 10) || '',
    });
  } else {
    editingUuid.value = null;
    editingFacilityName.value = '';
    resetDraft();
  }

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingUuid.value = null;
};

const saveAssignment = async () => {
  if (!draft.assessment_exercise_id || !draft.assignee_id) {
    notice.value = 'Exercise and assignee are required.';
    return;
  }

  if (!editingUuid.value && draft.target_type === 'facility' && draft.facility_ids.length === 0) {
    notice.value = 'Select at least one facility.';
    return;
  }

  if (!editingUuid.value && draft.target_type !== 'facility' && draft.target_type !== 'no_target' && draft.target_ids.length === 0) {
    notice.value = `Select at least one ${titleCase(draft.target_type).toLowerCase()} target.`;
    return;
  }

  try {
    if (editingUuid.value) {
      await updateAssessmentAssignment(editingUuid.value, {
        assignee_type: draft.assignee_type,
        assignee_id: Number(draft.assignee_id),
        status: draft.status,
        allow_duplicate_submission: draft.allow_duplicate_submission,
        due_at: draft.due_at || null,
      });
      notice.value = 'Assignment updated.';
    } else {
      const created = await createAssessmentAssignments({
        assessment_exercise_id: Number(draft.assessment_exercise_id),
        assignee_type: draft.assignee_type,
        assignee_id: Number(draft.assignee_id),
        facility_ids: draft.facility_ids,
        target_type: draft.target_type,
        target_ids: draft.target_ids,
        status: draft.status,
        allow_duplicate_submission: draft.allow_duplicate_submission,
        due_at: draft.due_at || null,
      });
      notice.value = `${created.length} assignment${created.length === 1 ? '' : 's'} created.`;
    }

    closeModal();
    await loadAssignments();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to save assignment.';
  }
};

const toggleFacility = (facilityId: number) => {
  if (draft.facility_ids.includes(facilityId)) {
    draft.facility_ids = draft.facility_ids.filter((id) => id !== facilityId);
    return;
  }

  draft.facility_ids.push(facilityId);
};

const toggleTarget = (targetId: number) => {
  draft.target_ids = draft.target_ids.includes(targetId)
    ? draft.target_ids.filter((id) => id !== targetId)
    : [...draft.target_ids, targetId];
};

const assigneeName = (assignment: AssessmentAssignment): string => {
  if (assignment.assignee_type === 'team') return assignment.team?.name || 'Unknown team';
  return assignment.user?.name || 'Unknown user';
};

const submissionCount = (assignment: AssessmentAssignment): number => assignment.submissions_count || 0;

const canCancel = (assignment: AssessmentAssignment): boolean => (
  submissionCount(assignment) === 0
  && ['assigned', 'in_progress'].includes(assignment.status)
);

const canArchive = (_assignment: AssessmentAssignment): boolean => true;

const canDelete = (assignment: AssessmentAssignment): boolean => (
  submissionCount(assignment) === 0
);

const assignmentLabel = (assignment: AssessmentAssignment): string => (
  assignment.facility?.name
  || assignment.target?.name
  || (assignment.target_type === 'no_target' ? 'No target' : 'this assignment')
);

const cancelAssignment = async (assignment: AssessmentAssignment) => {
  if (!window.confirm('Cancel ' + assignmentLabel(assignment) + '? It will remain in the workspace as cancelled.')) return;

  try {
    await cancelAssessmentAssignment(assignment.uuid);
    notice.value = 'Assignment cancelled.';
    await loadAssignments();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to cancel assignment.';
  }
};

const archiveAssignment = async (assignment: AssessmentAssignment) => {
  if (!window.confirm('Archive ' + assignmentLabel(assignment) + '? Its submissions and history will be preserved.')) return;

  try {
    await archiveAssessmentAssignment(assignment.uuid);
    notice.value = 'Assignment archived.';
    await loadAssignments();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to archive assignment.';
  }
};

const deleteAssignment = async (assignment: AssessmentAssignment) => {
  if (!canDelete(assignment)) {
    notice.value = 'Assignments with submissions must be archived instead of deleted.';
    return;
  }
  if (!window.confirm('Permanently delete ' + assignmentLabel(assignment) + '? This cannot be undone.')) return;

  try {
    await deleteAssessmentAssignment(assignment.uuid);
    notice.value = 'Assignment deleted.';
    await loadAssignments();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to delete assignment.';
  }
};

watch(() => ({ ...filters }), () => {
  page.value = 1;
  void loadAssignments();
});

watch(() => props.initialExerciseId, (exerciseId) => {
  const next = exerciseId ? String(exerciseId) : '';
  if (filters.exercise_id !== next) {
    filters.exercise_id = next;
  }
});

watch(page, () => {
  void loadAssignments();
});

watch(() => draft.assignee_type, () => {
  draft.assignee_id = draft.assignee_type === 'team'
    ? options.teams[0] ? String(options.teams[0].id) : ''
    : options.users[0] ? String(options.users[0].id) : '';
});

watch(() => draft.assessment_exercise_id, () => {
  const exercise = options.exercises.find((item) => String(item.id) === draft.assessment_exercise_id);
  if (!editingUuid.value) {
    draft.target_type = exercise?.target_type || 'facility';
    draft.facility_ids = [];
    draft.target_ids = [];
  }
  if (!editingUuid.value) {
    draft.due_at = exercise?.ends_at?.slice(0, 10) || draft.due_at;
  }
});

onMounted(loadAssignments);
</script>

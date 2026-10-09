<template>
  <section class="screen-stack">
    <AppPageHeader title="Exercise reports" subtitle="Track completion, review status, assessor progress, and exports by exercise" compact>
      <template #actions>
        <div class="report-header-actions">
          <QdsField v-model="selectedExerciseId" label="Exercise" :options="exerciseOptions" />
          <QdsButton variant="secondary" @click="loadReport">Refresh</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>

    <div v-if="selectedExerciseId" class="report-export-row">
      <span>Exports</span>
      <QdsButton as="a" variant="secondary" icon="submissions" :href="exportUrl('submissions')">Submissions</QdsButton>
      <QdsButton as="a" variant="secondary" icon="facilities" :href="exportUrl('facility_completion')">Facility status</QdsButton>
      <QdsButton as="a" variant="secondary" icon="team" :href="exportUrl('assessor_progress')">Assessor progress</QdsButton>
      <QdsButton as="a" variant="secondary" icon="reports" :href="exportUrl('team_progress')">Team progress</QdsButton>
    </div>

    <QdsCard v-if="selectedExercise" class="report-exercise-strip">
      <div>
        <div class="report-strip-title">
          <QdsBadge :tone="statusTone(selectedExercise.status)">{{ titleCase(selectedExercise.status) }}</QdsBadge>
          <h2>{{ selectedExercise.name }}</h2>
        </div>
        <p>{{ selectedExercise.form_title || 'Assessment form' }}</p>
      </div>
      <div class="report-strip-meta">
        <div>
          <span>Exercise window</span>
          <strong>{{ formatDateOnly(selectedExercise.starts_at) }} to {{ formatDateOnly(selectedExercise.ends_at) }}</strong>
        </div>
        <div>
          <span>Assignments</span>
          <strong>{{ summary.total_assignments }} facilities</strong>
        </div>
      </div>
    </QdsCard>

    <div class="metric-grid">
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="submissions" /></span>
        <div><strong>{{ summary.completion_rate }}%</strong><span>Completion</span><small>{{ summary.submitted_assignments }} of {{ summary.total_assignments }} assigned</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>{{ summary.review_rate }}%</strong><span>Reviewed</span><small>{{ summary.reviewed_assignments }} reviewed assignments</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="clock" /></span>
        <div><strong>{{ summary.awaiting_review }}</strong><span>Awaiting review</span><small>{{ summary.returned }} returned</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="reports" /></span>
        <div><strong>{{ summary.average_score }}</strong><span>Average score</span><small>{{ summary.approved }} approved</small></div>
      </QdsCard>
    </div>

    <div class="report-grid">
      <QdsCard class="report-panel">
        <div class="qds-card-header">
          <div>
            <h2>Assessor progress</h2>
            <p>User assignments and completion rate</p>
          </div>
        </div>
        <div class="progress-list">
          <div v-for="row in report.assessor_progress" :key="`assessor-${row.id ?? row.name}`" class="progress-row">
            <div class="progress-row__main">
              <div>
                <strong>{{ row.name }}</strong>
                <small>{{ row.email || 'No email recorded' }}</small>
              </div>
              <QdsBadge :tone="progressTone(row)">{{ row.completion_rate }}%</QdsBadge>
            </div>
            <div class="progress-track"><span :style="{ width: progressWidth(row.completion_rate) }" /></div>
            <div class="progress-row__stats">
              <span><strong>{{ row.assignments }}</strong><small>Assigned</small></span>
              <span><strong>{{ row.submitted }}</strong><small>Submitted</small></span>
              <span><strong>{{ row.reviewed }}</strong><small>Reviewed</small></span>
              <span><strong>{{ row.pending }}</strong><small>Pending</small></span>
            </div>
          </div>
          <div v-if="report.assessor_progress.length === 0" class="report-empty">
            <span><AppIcon name="team" /></span>
            <strong>No assessor assignments yet</strong>
            <p>Assign this exercise to users or teams to start tracking assessor progress.</p>
          </div>
        </div>
      </QdsCard>

      <QdsCard class="report-panel">
        <div class="qds-card-header">
          <div>
            <h2>Team progress</h2>
            <p>Team assignments and completion rate</p>
          </div>
        </div>
        <div class="progress-list">
          <div v-for="row in report.team_progress" :key="`team-${row.id ?? row.name}`" class="progress-row">
            <div class="progress-row__main">
              <div>
                <strong>{{ row.name }}</strong>
                <small>{{ row.assignments }} assigned facilities</small>
              </div>
              <QdsBadge :tone="progressTone(row)">{{ row.completion_rate }}%</QdsBadge>
            </div>
            <div class="progress-track"><span :style="{ width: progressWidth(row.completion_rate) }" /></div>
            <div class="progress-row__stats">
              <span><strong>{{ row.assignments }}</strong><small>Assigned</small></span>
              <span><strong>{{ row.submitted }}</strong><small>Submitted</small></span>
              <span><strong>{{ row.reviewed }}</strong><small>Reviewed</small></span>
              <span><strong>{{ row.pending }}</strong><small>Pending</small></span>
            </div>
          </div>
          <div v-if="report.team_progress.length === 0" class="report-empty">
            <span><AppIcon name="team" /></span>
            <strong>No team assignments yet</strong>
            <p>Create assignments for teams to see team-level progress here.</p>
          </div>
        </div>
      </QdsCard>
    </div>

    <QdsCard class="report-panel">
      <div class="qds-card-header">
        <div>
          <h2>Facility completion status</h2>
          <p>{{ report.facility_completion.length }} assigned facilities</p>
        </div>
      </div>
      <div class="facility-list">
        <div v-for="row in report.facility_completion" :key="row.assignment_id" class="facility-row">
          <div class="facility-row__main">
            <strong>{{ row.facility_name }}</strong>
            <small>{{ row.facility_code || 'No code' }} | {{ row.lga || 'No LGA' }} | {{ row.ward || 'No ward' }}</small>
          </div>
          <div class="facility-row__meta">
            <span>Assignee</span>
            <strong>{{ row.assignee_name }}</strong>
            <small>{{ titleCase(row.assignee_type) }}</small>
          </div>
          <div class="facility-row__meta">
            <span>Submission</span>
            <strong>{{ titleCase(row.submission_status) }}</strong>
            <small>{{ formatDateTime(row.submitted_at) }}</small>
          </div>
          <div class="facility-row__status">
            <QdsBadge :tone="assignmentTone(row.assignment_status)">{{ titleCase(row.assignment_status) }}</QdsBadge>
            <strong>{{ row.score_total ?? '-' }}</strong>
            <small>Score</small>
          </div>
        </div>
        <div v-if="report.facility_completion.length === 0" class="report-empty">
          <span><AppIcon name="facilities" /></span>
          <strong>No assigned facilities</strong>
          <p>Facilities assigned to this exercise will appear here.</p>
        </div>
      </div>
    </QdsCard>

    <QdsCard class="report-panel">
      <div class="qds-card-header">
        <div>
          <h2>Review queue</h2>
          <p>Recent exercise submissions that still need action</p>
        </div>
      </div>
      <div class="review-list">
        <div v-for="row in report.review_queue" :key="row.uuid" class="review-row">
          <div>
            <strong>{{ row.assessment_id }}</strong>
            <small>{{ row.facility_name }} | {{ formatDateTime(row.submitted_at) }}</small>
          </div>
          <div>
            <span>Assessor</span>
            <strong>{{ row.assessor_name || 'Unknown assessor' }}</strong>
          </div>
          <div>
            <span>Score</span>
            <strong>{{ row.score_total ?? '-' }}</strong>
          </div>
          <QdsBadge :tone="reviewTone(row.status)">{{ titleCase(row.status) }}</QdsBadge>
        </div>
        <div v-if="report.review_queue.length === 0" class="report-empty">
          <span><AppIcon name="submissions" /></span>
          <strong>No submissions waiting</strong>
          <p>Submitted assessments that need review will appear here.</p>
        </div>
      </div>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import {
  exerciseReportExportUrl,
  formatDateTime,
  loadExerciseReport,
  titleCase,
  type ExerciseReportPayload,
  type ProgressRow,
} from '../services/reports';

type BadgeTone = 'green' | 'amber' | 'blue' | 'red' | 'slate';

const emptySummary = {
  total_assignments: 0,
  assigned: 0,
  in_progress: 0,
  submitted_assignments: 0,
  reviewed_assignments: 0,
  pending_facilities: 0,
  completion_rate: 0,
  review_rate: 0,
  submissions: 0,
  awaiting_review: 0,
  returned: 0,
  approved: 0,
  average_score: 0,
};

const report = ref<ExerciseReportPayload>({
  exercises: [],
  selected_exercise: null,
  summary: emptySummary,
  assessor_progress: [],
  team_progress: [],
  facility_completion: [],
  review_queue: [],
});
const selectedExerciseId = ref('');
const notice = ref('');
const initialized = ref(false);

const summary = computed(() => report.value.summary);
const selectedExercise = computed(() => report.value.selected_exercise);
const exerciseOptions = computed(() => [
  { label: 'Select exercise', value: '' },
  ...report.value.exercises.map((exercise) => ({
    label: `${exercise.name} (${exercise.code})`,
    value: String(exercise.id),
  })),
]);

const loadReport = async () => {
  notice.value = '';
  try {
    const payload = await loadExerciseReport(selectedExerciseId.value);
    report.value = payload;
    if (!selectedExerciseId.value && payload.selected_exercise?.id) {
      selectedExerciseId.value = String(payload.selected_exercise.id);
    }
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to load exercise report.';
  }
};

const exportUrl = (type: 'submissions' | 'facility_completion' | 'assessor_progress' | 'team_progress') => (
  selectedExerciseId.value ? exerciseReportExportUrl(selectedExerciseId.value, type) : '#'
);

const formatDateOnly = (value?: string | null): string => {
  if (!value) return 'Not set';
  const [datePart] = value.split('T');
  const [year, month, day] = datePart.split('-').map(Number);
  if (!year || !month || !day) return value;

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(year, month - 1, day));
};

const progressWidth = (value?: number | null): string => `${Math.min(Math.max(Number(value) || 0, 0), 100)}%`;

const statusTone = (status?: string | null): BadgeTone => {
  if (status === 'active') return 'green';
  if (status === 'scheduled') return 'blue';
  if (status === 'draft') return 'amber';
  if (status === 'archived') return 'red';
  return 'slate';
};

const progressTone = (row: ProgressRow): BadgeTone => {
  if (row.completion_rate >= 100) return 'green';
  if (row.submitted > 0) return 'blue';
  return 'amber';
};

const assignmentTone = (status?: string | null): BadgeTone => {
  if (status === 'reviewed' || status === 'submitted') return 'green';
  if (status === 'in_progress') return 'blue';
  if (status === 'assigned') return 'amber';
  if (status === 'cancelled' || status === 'expired') return 'red';
  return 'slate';
};

const reviewTone = (status?: string | null): BadgeTone => {
  if (status === 'approved' || status === 'reviewed') return 'green';
  if (status === 'submitted') return 'blue';
  if (status === 'under_review') return 'amber';
  if (status === 'returned') return 'red';
  return 'slate';
};

watch(selectedExerciseId, () => {
  if (initialized.value) void loadReport();
});

onMounted(async () => {
  await loadReport();
  initialized.value = true;
});
</script>

<style scoped>
.report-header-actions,
.report-export-row,
.report-exercise-strip,
.report-strip-title,
.report-strip-meta,
.report-grid,
.progress-row__main,
.progress-row__stats,
.facility-row,
.facility-row__status,
.review-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.report-header-actions {
  align-items: flex-end;
  justify-content: flex-end;
}

.report-header-actions :deep(.qds-field) {
  min-width: min(420px, 48vw);
}

.report-export-row {
  flex-wrap: wrap;
  padding: 11px 12px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: #fff;
}

.report-export-row > span {
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.report-exercise-strip {
  justify-content: space-between;
  padding: 14px 16px;
}

.report-exercise-strip h2 {
  margin: 0;
  color: var(--qds-ink);
  font-size: 19px;
}

.report-exercise-strip p {
  margin: 6px 0 0;
  color: var(--qds-muted);
  font-size: 12px;
  font-weight: 700;
}

.report-strip-meta {
  justify-content: flex-end;
  text-align: right;
}

.report-strip-meta div {
  display: grid;
  gap: 4px;
  min-width: 132px;
}

.report-strip-meta span,
.facility-row__meta span,
.review-row span {
  color: var(--qds-slate-500);
  font-size: 10px;
  font-weight: 900;
  text-transform: uppercase;
}

.report-strip-meta strong {
  color: var(--qds-slate-950);
  font-size: 13px;
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: stretch;
  gap: 16px;
}

.report-panel {
  padding: 16px;
}

.progress-list,
.facility-list,
.review-list {
  display: grid;
  gap: 10px;
  margin-top: 12px;
}

.progress-row,
.facility-row,
.review-row {
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: var(--qds-slate-50);
}

.progress-row {
  display: grid;
  gap: 10px;
  padding: 12px;
}

.progress-row__main,
.facility-row,
.review-row {
  justify-content: space-between;
}

.progress-row strong,
.facility-row strong,
.review-row strong {
  display: block;
  color: var(--qds-slate-950);
  font-size: 12px;
  font-weight: 900;
}

.progress-row small,
.facility-row small,
.review-row small {
  display: block;
  margin-top: 2px;
  color: var(--qds-slate-500);
  font-size: 10px;
  font-weight: 700;
}

.progress-track {
  overflow: hidden;
  height: 7px;
  border-radius: 999px;
  background: var(--qds-slate-200);
}

.progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--qds-green-700);
}

.progress-row__stats {
  justify-content: space-between;
}

.progress-row__stats span {
  min-width: 72px;
}

.facility-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.4fr) minmax(150px, 0.8fr) minmax(150px, 0.9fr) auto;
  padding: 12px;
}

.facility-row__main small {
  line-height: 1.5;
}

.facility-row__status {
  justify-content: flex-end;
  min-width: 132px;
  text-align: right;
}

.review-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.4fr) minmax(150px, 0.8fr) 80px auto;
  padding: 12px;
}

.report-empty {
  display: grid;
  place-items: center;
  min-height: 154px;
  padding: 24px;
  border: 1px dashed var(--qds-slate-200);
  border-radius: 8px;
  color: var(--qds-slate-600);
  background: var(--qds-slate-50);
  text-align: center;
}

.report-empty span {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  margin-bottom: 10px;
  border-radius: 50%;
  color: var(--qds-green-800);
  background: var(--qds-green-100);
}

.report-empty strong {
  color: var(--qds-slate-950);
  font-size: 13px;
}

.report-empty p {
  max-width: 340px;
  margin: 6px 0 0;
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 700;
}

@media (max-width: 1100px) {
  .report-grid {
    grid-template-columns: 1fr;
  }

  .report-exercise-strip,
  .facility-row,
  .review-row {
    align-items: flex-start;
    grid-template-columns: 1fr;
  }

  .report-exercise-strip {
    flex-direction: column;
  }

  .report-strip-meta,
  .facility-row__status {
    justify-content: flex-start;
    text-align: left;
  }
}

@media (max-width: 760px) {
  .report-header-actions,
  .report-strip-title,
  .report-strip-meta,
  .progress-row__main,
  .progress-row__stats {
    align-items: stretch;
    flex-direction: column;
  }

  .report-header-actions :deep(.qds-field) {
    min-width: 100%;
  }
}
</style>

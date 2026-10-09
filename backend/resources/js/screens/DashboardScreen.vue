<template>
  <section class="screen-stack">
    <AppPageHeader title="Dashboard" subtitle="Operational overview for exercises, assignments, reviews, and field activity" compact>
      <template #actions>
        <div class="dashboard-header-actions">
          <QdsField v-model="filters.exercise_id" label="Exercise" :options="exerciseOptions" />
          <QdsField v-model="filters.range" label="Date range" :options="rangeOptions" />
          <QdsButton variant="secondary" @click="load">Refresh</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>

    <div v-if="showAssignmentAlert" class="dashboard-alert">
      <span class="dashboard-alert__icon"><AppIcon name="clock" /></span>
      <div>
        <strong>{{ metrics.pending_facilities }} assignment{{ metrics.pending_facilities === 1 ? '' : 's' }} need attention</strong>
        <p>{{ exerciseWindowNote }} and {{ metrics.pending_facilities }} assigned facilit{{ metrics.pending_facilities === 1 ? 'y has' : 'ies have' }} not submitted yet.</p>
      </div>
      <button type="button" @click="$emit('navigate', 'Assignment Manager')">Review assignments</button>
    </div>

    <div class="metric-grid">
      <QdsCard v-for="metric in metricCards" :key="metric.label" :class="['metric-card', `metric-card--${metric.tone}`]">
        <span class="metric-icon"><AppIcon :name="metric.icon" /></span>
        <div>
          <strong>{{ metric.value }}</strong>
          <span>{{ metric.label }}</span>
          <small>{{ metric.detail }}</small>
        </div>
      </QdsCard>
    </div>

    <div class="dashboard-main-grid">
      <div class="dashboard-main-column">
      <QdsCard class="dashboard-panel dashboard-completion">
        <div class="qds-card-header">
          <div>
            <h2>Completion dashboard</h2>
            <p>{{ payload?.range.label || 'Selected period' }} - {{ payload?.range.date_from }} to {{ payload?.range.date_to }}</p>
          </div>
          <QdsBadge tone="green">{{ metrics.completion_rate }}%</QdsBadge>
        </div>
        <div class="completion-segments" aria-hidden="true">
          <span
            v-for="segment in completionSegments"
            :key="segment.index"
            :class="{ active: segment.active, pending: !segment.active && metrics.pending_facilities > 0 }"
          ></span>
        </div>
        <div class="dashboard-stat-row">
          <div><strong>{{ metrics.total_assignments }}</strong><span>Assigned facilities</span></div>
          <div><strong>{{ metrics.submitted_assignments }}</strong><span>Completed</span></div>
          <div><strong>{{ metrics.pending_facilities }}</strong><span>Pending</span></div>
          <div><strong>{{ metrics.reviewed_assignments }}</strong><span>Reviewed</span></div>
        </div>
      </QdsCard>

      <QdsCard class="chart-panel">
        <div class="qds-card-header">
          <div>
            <h2>Submissions over time</h2>
            <p>Synced assessments in the selected period</p>
          </div>
        </div>
        <div v-if="hasChartData" class="dashboard-chart-bars">
          <div v-for="(point, index) in chartPoints" :key="point.date" class="dashboard-chart-bar">
            <span :style="{ height: `${barHeight(point.count)}%` }"></span>
            <small>{{ chartLabel(index) }}</small>
          </div>
        </div>
        <div v-else class="dashboard-empty-chart">
          <strong>No submissions in this range</strong>
          <span>Synced assessments will appear here once field teams submit completed work.</span>
          <button type="button" @click="$emit('navigate', 'Assignment Manager')">Check pending assignments</button>
        </div>
      </QdsCard>

      <QdsCard class="submissions-panel">
        <div class="qds-card-header">
          <div>
            <h2>Recent submissions</h2>
            <p>Latest synced facility assessments</p>
          </div>
          <button class="qds-link-button" type="button" @click="$emit('navigate', 'Submissions')">View all</button>
        </div>
        <div v-if="recentSubmissions.length" class="mini-table dashboard-mini-table">
          <div class="mini-table__head">
            <span>Facility</span>
            <span>Submitted</span>
            <span>Status</span>
          </div>
          <div v-for="row in recentSubmissions" :key="row.uuid" class="mini-table__row">
            <span>{{ row.facility_name }}<small>{{ row.assessor_name || 'Unknown assessor' }}</small></span>
            <span>{{ formatDateTime(row.submitted_at) }}<small>{{ row.exercise_name || 'No exercise' }}</small></span>
            <QdsBadge :tone="statusTone(row.status)">{{ titleCase(row.status) }}</QdsBadge>
          </div>
        </div>
        <div v-else class="dashboard-empty-submissions">
          <span><AppIcon name="upload" /></span>
          <strong>No submissions yet</strong>
          <p>Completed assessments will appear here.</p>
        </div>
      </QdsCard>
      </div>

      <QdsCard class="dashboard-panel quick-actions">
        <h2>Quick actions</h2>
        <button type="button" class="quick-action-row primary" @click="$emit('navigate', 'Forms')">
          <AppIcon name="forms" />
          <span>Create or manage forms</span>
          <strong>&gt;</strong>
        </button>
        <button type="button" class="quick-action-row" @click="$emit('navigate', 'Exercises')">
          <AppIcon name="calendar" />
          <span>Create exercise</span>
          <strong>&gt;</strong>
        </button>
        <button type="button" class="quick-action-row" @click="$emit('navigate', 'Assignment Manager')">
          <AppIcon name="clipboard" />
          <span>Assign facilities</span>
          <strong>&gt;</strong>
        </button>
        <button type="button" class="quick-action-row" @click="$emit('navigate', 'Submissions')">
          <AppIcon name="submissions" />
          <span>Review submissions</span>
          <strong>&gt;</strong>
        </button>
        <button type="button" class="quick-action-row" @click="$emit('navigate', 'Reports')">
          <AppIcon name="reports" />
          <span>View reports</span>
          <strong>&gt;</strong>
        </button>
      </QdsCard>
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
import {
  formatDate,
  formatDateTime,
  loadDashboard,
  statusTone,
  titleCase,
  type DashboardPayload,
} from '../services/dashboard';

defineEmits<{
  navigate: [section: string];
}>();

const emptyMetrics = {
  total_exercises: 0,
  active_exercises: 0,
  total_assignments: 0,
  assigned: 0,
  in_progress: 0,
  submitted_assignments: 0,
  reviewed_assignments: 0,
  pending_facilities: 0,
  completion_rate: 0,
  review_rate: 0,
  submissions_in_range: 0,
  awaiting_review: 0,
  returned: 0,
  approved: 0,
  average_score: 0,
};

const payload = ref<DashboardPayload | null>(null);
const notice = ref('');
const filters = reactive({
  exercise_id: '',
  range: 'last_30_days',
});

const rangeOptions = [
  { label: 'Last 30 days', value: 'last_30_days' },
  { label: 'This month', value: 'this_month' },
  { label: 'This quarter', value: 'this_quarter' },
  { label: 'This year', value: 'this_year' },
  { label: 'All time', value: 'all_time' },
];

const metrics = computed(() => payload.value?.metrics ?? emptyMetrics);
const currentExercise = computed(() => payload.value?.current_exercise ?? null);
const recentSubmissions = computed(() => payload.value?.recent_submissions ?? []);
const chartPoints = computed(() => payload.value?.chart.points ?? []);
const chartMax = computed(() => Math.max(payload.value?.chart.max ?? 0, 1));
const hasChartData = computed(() => chartPoints.value.some((point) => point.count > 0));
const exerciseOptions = computed(() => [
  { label: 'Current exercise', value: '' },
  ...(payload.value?.exercise_options ?? []).map((exercise) => ({
    label: `${exercise.name} (${exercise.code})`,
    value: String(exercise.id),
  })),
]);

const metricCards = computed(() => [
  {
    icon: 'clipboard',
    value: `${metrics.value.completion_rate}%`,
    label: 'Facility completion',
    detail: `${metrics.value.submitted_assignments} of ${metrics.value.total_assignments} submitted`,
    tone: 'green',
  },
  {
    icon: 'submissions',
    value: String(metrics.value.awaiting_review),
    label: 'Awaiting review',
    detail: `${metrics.value.returned} returned`,
    tone: 'amber',
  },
  {
    icon: 'upload',
    value: String(metrics.value.submissions_in_range),
    label: 'Submissions',
    detail: payload.value?.range.label ?? 'Selected period',
    tone: 'blue',
  },
  {
    icon: 'reports',
    value: String(metrics.value.average_score),
    label: 'Average score',
    detail: `${metrics.value.approved} approved`,
    tone: 'green',
  },
] as const);

const showAssignmentAlert = computed(() => metrics.value.pending_facilities > 0 && Boolean(currentExercise.value));

const daysBetween = (from: Date, to: Date): number => {
  const day = 24 * 60 * 60 * 1000;
  const start = Date.UTC(from.getFullYear(), from.getMonth(), from.getDate());
  const end = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate());
  return Math.ceil((end - start) / day);
};

const exerciseWindowState = computed(() => {
  const exercise = currentExercise.value;
  if (!exercise?.starts_at || !exercise.ends_at) {
    return {
      label: 'No window',
      tone: 'slate' as const,
      note: 'No submission window configured',
      progress: 0,
    };
  }

  const today = new Date();
  const startsAt = new Date(`${exercise.starts_at}T00:00:00`);
  const endsAt = new Date(`${exercise.ends_at}T23:59:59`);
  const totalDays = Math.max(daysBetween(startsAt, endsAt), 1);

  if (today < startsAt) {
    const days = Math.max(daysBetween(today, startsAt), 0);
    return {
      label: 'Window pending',
      tone: 'blue' as const,
      note: `Exercise window opens in ${days} day${days === 1 ? '' : 's'}`,
      progress: 0,
    };
  }

  if (today > endsAt) {
    const days = Math.max(daysBetween(endsAt, today), 0);
    return {
      label: 'Window closed',
      tone: 'red' as const,
      note: `Exercise window ended ${days} day${days === 1 ? '' : 's'} ago`,
      progress: 100,
    };
  }

  const elapsed = Math.max(daysBetween(startsAt, today), 0);
  const remaining = Math.max(daysBetween(today, endsAt), 0);

  return {
    label: 'Window open',
    tone: 'green' as const,
    note: `Exercise window open, ${remaining} day${remaining === 1 ? '' : 's'} left`,
    progress: Math.min(100, Math.max(4, Math.round((elapsed / totalDays) * 100))),
  };
});

const exerciseWindowNote = computed(() => exerciseWindowState.value.note);
const completionSegments = computed(() => {
  const active = Math.round((Math.min(metrics.value.completion_rate, 100) / 100) * 12);
  return Array.from({ length: 12 }, (_, index) => ({
    index,
    active: index < active,
  }));
});

const barHeight = (count: number): number => {
  if (count <= 0) return 0;
  return Math.max(8, Math.round((count / chartMax.value) * 100));
};

const chartLabel = (index: number): string => {
  const point = chartPoints.value[index];
  if (!point) return '';

  const interval = Math.max(1, Math.ceil(chartPoints.value.length / 8));
  if (index === 0 || index === chartPoints.value.length - 1 || index % interval === 0) {
    return point.label;
  }

  return '';
};

const load = async () => {
  notice.value = '';
  try {
    payload.value = await loadDashboard(filters);
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to load dashboard.';
  }
};

watch(() => ({ ...filters }), () => {
  void load();
});

onMounted(load);
</script>

<style scoped>
.dashboard-header-actions {
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}

.dashboard-header-actions :deep(.qds-field) {
  min-width: 180px;
}

.dashboard-header-actions :deep(.qds-field:first-child) {
  min-width: 300px;
}

.dashboard-alert {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 13px 14px;
  border: 1px solid rgba(167, 93, 0, 0.22);
  border-radius: 8px;
  color: var(--qds-amber-700);
  background: #fff7e6;
}

.dashboard-alert__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: var(--qds-amber-100);
}

.dashboard-alert__icon .app-icon {
  width: 18px;
  height: 18px;
}

.dashboard-alert strong,
.dashboard-alert p {
  margin: 0;
}

.dashboard-alert strong {
  color: var(--qds-slate-950);
  font-size: 13px;
}

.dashboard-alert p {
  margin-top: 3px;
  color: var(--qds-amber-700);
  font-size: 11px;
  font-weight: 700;
}

.dashboard-alert button {
  min-height: 34px;
  padding: 0 14px;
  border: 0;
  border-radius: 7px;
  color: #fff;
  background: #7a3f00;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.dashboard-main-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 310px;
  gap: 13px;
  align-items: start;
}

.dashboard-main-column {
  display: grid;
  gap: 13px;
}

.dashboard-panel {
  padding: 15px;
}

.completion-segments {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 6px;
  margin: 14px 0 16px;
}

.completion-segments span {
  height: 10px;
  border-radius: 999px;
  background: var(--qds-slate-100);
}

.completion-segments span.active {
  background: var(--qds-green-700);
}

.completion-segments span.pending {
  background: #f4d49a;
}

.dashboard-stat-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.dashboard-stat-row div {
  padding: 12px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: var(--qds-slate-50);
}

.dashboard-stat-row strong,
.dashboard-stat-row span {
  display: block;
}

.dashboard-stat-row strong {
  color: var(--qds-ink);
  font-size: 20px;
}

.dashboard-stat-row span {
  color: var(--qds-muted);
  font-size: 11px;
  font-weight: 800;
}

.dashboard-grid--live {
  grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
}

.dashboard-chart-bars {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  align-items: flex-end;
  gap: 4px;
  height: 210px;
  padding-top: 18px;
  overflow: hidden;
}

.dashboard-chart-bar {
  display: grid;
  grid-template-rows: 1fr auto;
  align-items: end;
  min-width: 0;
  height: 210px;
}

.dashboard-chart-bar span {
  display: block;
  min-height: 0;
  border-radius: 7px 7px 0 0;
  background: var(--qds-green-700);
}

.dashboard-chart-bar small {
  margin-top: 8px;
  color: var(--qds-slate-500);
  font-size: 9px;
  font-weight: 800;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: clip;
}

.dashboard-empty-chart {
  display: grid;
  place-items: center;
  min-height: 210px;
  padding: 24px;
  border: 1px dashed var(--qds-slate-200);
  border-radius: 8px;
  color: var(--qds-slate-600);
  background: var(--qds-slate-50);
  text-align: center;
}

.dashboard-empty-chart strong,
.dashboard-empty-chart span {
  display: block;
}

.dashboard-empty-chart strong {
  color: var(--qds-slate-900);
  font-size: 15px;
}

.dashboard-empty-chart span {
  max-width: 320px;
  margin-top: 6px;
  font-size: 12px;
  line-height: 1.5;
}

.dashboard-empty-chart button {
  min-height: 36px;
  margin-top: 14px;
  padding: 0 14px;
  border: 1px solid var(--qds-green-700);
  border-radius: 7px;
  color: var(--qds-green-800);
  background: #fff;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.quick-action-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  padding: 0 12px;
  border: 1px solid rgba(8, 122, 74, 0.34);
  border-radius: 7px;
  color: var(--qds-green-800);
  background: #fff;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  text-align: left;
  cursor: pointer;
}

.quick-action-row.primary {
  color: #fff;
  background: var(--qds-green-800);
  border-color: var(--qds-green-800);
}

.quick-action-row .app-icon {
  width: 15px;
  height: 15px;
}

.quick-action-row strong {
  font-size: 16px;
  line-height: 1;
}

.dashboard-empty-submissions {
  display: grid;
  place-items: center;
  min-height: 210px;
  padding: 24px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: var(--qds-slate-50);
  text-align: center;
}

.dashboard-empty-submissions span {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  margin-bottom: 10px;
  border-radius: 999px;
  color: var(--qds-green-800);
  background: var(--qds-green-100);
}

.dashboard-empty-submissions .app-icon {
  width: 18px;
  height: 18px;
}

.dashboard-empty-submissions strong {
  color: var(--qds-slate-900);
  font-size: 14px;
}

.dashboard-empty-submissions p {
  margin: 5px 0 0;
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 800;
}

.dashboard-mini-table .mini-table__row {
  min-height: 48px;
}

.dashboard-mini-table small {
  display: block;
  margin-top: 2px;
  color: var(--qds-slate-500);
  font-size: 10px;
}

.qds-link-button {
  border: 0;
  color: var(--qds-green-800);
  background: transparent;
  font-weight: 900;
  cursor: pointer;
}

@media (max-width: 1200px) {
  .dashboard-main-grid,
  .dashboard-grid--live {
    grid-template-columns: 1fr;
  }

}
</style>


<template>
  <section class="screen-stack submissions-screen">
    <AppPageHeader title="Submission review" subtitle="Review, return, and approve synced facility assessments" compact>
      <template #actions>
        <div class="submission-header-actions">
          <QdsButton icon="submissions" @click="reviewNext">Review next</QdsButton>
          <QdsButton variant="secondary" @click="loadSubmissions">Refresh</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>

    <QdsCard class="review-filters">
      <QdsField v-model="filters.exercise_id" label="Exercise" :options="exerciseFilterOptions" />
      <QdsField v-model="filters.status" label="Status" :options="statusFilterOptions" />
      <QdsSearch v-model="filters.facility" compact placeholder="Search facility..." />
    </QdsCard>

    <div class="review-summary-grid">
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="submissions" /></span>
        <div><strong>{{ queue.length }}</strong><span>Total synced</span><small>{{ selectedExerciseName || 'All exercises' }}</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="clock" /></span>
        <div><strong>{{ actionableCount }}</strong><span>Needs review</span><small>Submitted or under review</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="reports" /></span>
        <div><strong>{{ averageScoreLabel }}</strong><span>Average score</span><small>Across visible submissions</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>{{ totalIssues }}</strong><span>Validation issues</span><small>{{ totalEvidence }} evidence files</small></div>
      </QdsCard>
    </div>

    <QdsCard class="queue-panel">
      <div class="qds-card-header">
        <div>
          <h2>Review queue</h2>
          <p>{{ queue.length }} synced submissions{{ selectedExerciseName ? ` for ${selectedExerciseName}` : '' }}</p>
        </div>
      </div>

      <div class="queue-list">
        <button
          v-for="submission in queue"
          :key="submission.uuid"
          type="button"
          :class="['submission-row', selectedUuid === submission.uuid ? 'submission-row--active' : '']"
          @click="openReview(submission.uuid)"
        >
          <span class="submission-row__status">
            <QdsBadge :tone="submissionTone(submission.status)">{{ formatSubmissionStatus(submission.status) }}</QdsBadge>
            <strong>{{ scoreLabel(submission) }}</strong>
          </span>
          <span class="submission-row__main">
            <strong>{{ submission.facility_name }}</strong>
            <small>{{ submission.lga_name || 'No LGA' }} / {{ submission.ward_name || 'No ward' }}</small>
            <small>{{ submission.exercise?.name || 'No exercise' }} | {{ submission.form?.title || 'Assessment form' }}</small>
          </span>
          <span class="submission-row__meta">
            <strong>{{ submission.assessment_id }}</strong>
            <small>{{ formatDateTime(submission.submitted_at) }}</small>
            <small>{{ submission.assessor_name || 'Unknown assessor' }}</small>
          </span>
          <span class="submission-row__counts">
            <span><strong>{{ issueCount(submission) }}</strong><small>Issues</small></span>
            <span><strong>{{ submission.attachments?.length ?? 0 }}</strong><small>Evidence</small></span>
          </span>
          <span class="submission-row__action">Open review</span>
        </button>

        <div v-if="queue.length === 0" class="review-empty">
          <span><AppIcon name="submissions" /></span>
          <strong>No submissions found</strong>
          <p>Synced assessments that match the current filters will appear here.</p>
        </div>
      </div>
    </QdsCard>

    <div v-if="reviewModalOpen" class="review-modal" role="dialog" aria-modal="true" aria-label="Review decision">
      <button class="review-modal__backdrop" type="button" aria-label="Close review decision" @click="closeReviewModal" />
      <QdsCard class="review-detail">
        <div class="review-detail__header">
          <div>
            <h2>Review decision</h2>
            <p>{{ selectedSubmission ? selectedSubmission.assessment_id : 'Select a submission to begin' }}</p>
          </div>
          <div class="review-detail__header-actions">
            <QdsBadge v-if="selectedSubmission" :tone="submissionTone(selectedSubmission.status)">
              {{ formatSubmissionStatus(selectedSubmission.status) }}
            </QdsBadge>
            <QdsButton variant="secondary" @click="closeReviewModal">Close</QdsButton>
          </div>
        </div>

        <div v-if="!selectedSubmission" class="review-empty review-empty--tall">
          <span><AppIcon name="clipboard" /></span>
          <strong>No submission selected</strong>
          <p>Choose a submission from the queue to inspect answers, evidence, validation issues, and review notes.</p>
        </div>

        <template v-else>
          <div class="submission-identity">
            <strong>{{ selectedSubmission.facility_name }}</strong>
            <span>{{ selectedSubmission.facility_type || 'Facility' }} | {{ selectedSubmission.lga_name || 'No LGA' }} / {{ selectedSubmission.ward_name || 'No ward' }}</span>
            <span>{{ selectedSubmission.assessor_name || 'Unknown assessor' }} | {{ formatDateTime(selectedSubmission.submitted_at) }}</span>
          </div>

          <div class="review-score-hero">
            <div>
              <span>Assessment score</span>
              <strong>{{ scoreLabel(selectedSubmission) }}</strong>
              <small>{{ scorePercentLabel }}</small>
            </div>
            <div class="review-score-track" aria-label="Assessment score percentage">
              <span :style="{ width: `${scorePercent}%` }" />
            </div>
          </div>

          <div class="review-metrics">
            <div>
              <strong>{{ scoreLabel(selectedSubmission) }}</strong>
              <span>Score</span>
            </div>
            <div>
              <strong>{{ validationIssues.length }}</strong>
              <span>Issues</span>
            </div>
            <div>
              <strong>{{ selectedSubmission.attachments?.length ?? 0 }}</strong>
              <span>Evidence</span>
            </div>
          </div>

          <div v-if="gpsLabel" class="review-block review-block--compact">
            <h3>Location</h3>
            <p>{{ gpsLabel }}</p>
          </div>

          <div v-if="validationIssues.length" class="review-block warning">
            <div class="review-block__heading">
              <h3>Validation issues</h3>
              <QdsBadge tone="red">{{ validationIssues.length }}</QdsBadge>
            </div>
            <p v-for="issue in validationIssues.slice(0, 8)" :key="`${issue.field}-${issue.message}`">
              <strong>{{ issue.field }}:</strong> {{ issue.message }}
            </p>
            <small v-if="validationIssues.length > 8">Showing first 8 of {{ validationIssues.length }} issues.</small>
          </div>

          <div v-if="reviewAnswerRows.length" class="review-block warning">
            <div class="review-block__heading">
              <h3>Answers requiring review</h3>
              <QdsBadge tone="amber">{{ reviewAnswerRows.length }}</QdsBadge>
            </div>
            <p>Flagged answers are marked inside their sections below.</p>
          </div>

          <div v-if="nonSignatureAnswerRows.length" class="review-block">
            <div class="review-block__heading">
              <h3>Submitted answers by section</h3>
              <QdsBadge tone="blue">{{ nonSignatureAnswerRows.length }}</QdsBadge>
            </div>
            <div v-for="section in answerSections" :key="section.key" class="answer-section">
              <button
                type="button"
                class="answer-section__header"
                :aria-expanded="isAnswerSectionExpanded(section.key)"
                @click="toggleAnswerSection(section.key)"
              >
                <span>
                  <strong>{{ section.label }}</strong>
                  <small>{{ section.answers.length }} answers</small>
                </span>
                <strong>{{ isAnswerSectionExpanded(section.key) ? '-' : '+' }}</strong>
              </button>
              <div v-if="isAnswerSectionExpanded(section.key)" class="answer-section__rows">
                <div v-for="answer in section.answers" :key="answer.key" :class="['answer-row', answer.requiresReview && 'flagged']">
                  <span>{{ answer.label }}</span>
                  <strong>{{ answer.value }}<small v-if="answer.score !== null">Score {{ answer.score }}</small></strong>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedSubmission.attachments?.length" class="evidence-list">
            <h3>Evidence</h3>
            <button
              v-for="attachment in selectedSubmission.attachments"
              :key="String(attachment.id ?? attachment.download_url ?? attachment.original_name ?? 'evidence')"
              type="button"
              :disabled="downloadingAttachmentId === Number(attachment.id)"
              @click="openEvidence(attachment.download_url, Number(attachment.id))"
            >
              <AppIcon name="upload" />
              <span>
                <strong>{{ attachment.original_name || attachment.type || 'Evidence file' }}</strong>
                <small>{{ downloadingAttachmentId === Number(attachment.id) ? 'Opening...' : (attachment.field_key || 'general') + ' | ' + formatBytes(attachment.size_bytes) }}</small>
              </span>
            </button>
          </div>

          <div v-if="selectedSubmission.sync_events?.length" class="review-block">
            <h3>Sync trail</h3>
            <div v-for="event in selectedSubmission.sync_events.slice(0, 5)" :key="String(event.id ?? event.attempted_at)" class="answer-row">
              <span>{{ formatSubmissionStatus(event.status) }}</span>
              <strong>{{ event.message || event.direction || 'Sync event' }}</strong>
            </div>
          </div>

          <div class="decision-card">
            <h3>Decision checklist</h3>
            <div class="decision-stack">
              <QdsCheckbox v-model="checks.completeness" label="Checklist is complete" />
              <QdsCheckbox v-model="checks.evidence" label="Required evidence is attached" />
            </div>

            <QdsField v-model="reviewNote" label="Review note" textarea :rows="4" />

            <div class="action-row">
              <QdsButton variant="secondary" @click="submitReview('start_review')">Start review</QdsButton>
              <QdsButton variant="secondary" @click="submitReview('return')">Return</QdsButton>
              <QdsButton @click="submitReview('approve')">Approve</QdsButton>
            </div>
          </div>
        </template>
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
import QdsCheckbox from '../components/qds/QdsCheckbox.vue';
import QdsField from '../components/qds/QdsField.vue';
import QdsSearch from '../components/qds/QdsSearch.vue';
import { authenticatedRequest } from '../services/auth';
import { loadExerciseReport, type ReportExerciseOption } from '../services/reports';
import {
  type AssessmentSubmission,
  formatDateTime,
  formatSubmissionStatus,
  getSubmission,
  listSubmissions,
  reviewSubmission,
  submissionTone,
} from '../services/submissions';

const queue = ref<AssessmentSubmission[]>([]);
const exercises = ref<ReportExerciseOption[]>([]);
const selectedUuid = ref('');
const reviewModalOpen = ref(false);
const reviewNote = ref('');
const notice = ref('');
const downloadingAttachmentId = ref<number | null>(null);
const filters = reactive({
  exercise_id: '',
  status: '',
  facility: '',
});
const checks = reactive({
  completeness: true,
  evidence: false,
});

const selectedSubmission = computed(() => queue.value.find((submission) => submission.uuid === selectedUuid.value));
const expandedAnswerSections = ref<Record<string, boolean>>({});
const selectedExerciseName = computed(() => exercises.value.find((exercise) => String(exercise.id) === filters.exercise_id)?.name ?? '');
const exerciseFilterOptions = computed(() => [
  { label: 'All exercises', value: '' },
  ...exercises.value.map((exercise) => ({ label: `${exercise.name} (${exercise.code})`, value: String(exercise.id) })),
]);
const statusFilterOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Under review', value: 'under_review' },
  { label: 'Returned', value: 'returned' },
  { label: 'Reviewed', value: 'reviewed' },
  { label: 'Approved', value: 'approved' },
];
const actionableCount = computed(() => queue.value.filter((submission) => ['submitted', 'under_review'].includes(submission.status)).length);
const totalIssues = computed(() => queue.value.reduce((total, submission) => total + issueCount(submission), 0));
const totalEvidence = computed(() => queue.value.reduce((total, submission) => total + (submission.attachments?.length ?? 0), 0));
const averageScoreLabel = computed(() => {
  const scores = queue.value
    .map((submission) => submission.score_total)
    .filter((score): score is number => score !== null && score !== undefined);

  if (!scores.length) return '-';
  return (scores.reduce((total, score) => total + score, 0) / scores.length).toFixed(1);
});
const validationIssues = computed(() => selectedSubmission.value?.validation_result?.errors ?? []);
const answerRows = computed(() => {
  const submission = selectedSubmission.value;
  if (!submission?.answers) return [];

  const formFields = submission.form_version?.fields ?? [];
  const formSections = submission.form_version?.sections ?? [];

  return submission.answers.map((answer) => {
    const key = String(answer.field_key ?? 'answer');
    const field = submission.form_version?.fields?.find((candidate) => candidate.key === key);
    const sectionKey = String(answer.section_key ?? field?.section ?? 'general');
    const section = submission.form_version?.sections?.find((candidate) => candidate.key === sectionKey);
    const sectionIndex = formSections.findIndex((candidate) => candidate.key === sectionKey);
    const fieldIndex = formFields.findIndex((candidate) => candidate.key === key);
    return {
      key,
      label: field?.label || titleCase(key),
      value: formatAnswerValue(extractAnswerValue(answer.value)),
      requiresReview: Boolean(answer.requires_review),
      score: answer.score ?? null,
      sectionKey,
      sectionLabel: section?.label || titleCase(sectionKey),
      sectionOrder: sectionIndex >= 0 ? sectionIndex : Number.MAX_SAFE_INTEGER,
      fieldOrder: fieldIndex >= 0 ? fieldIndex : Number.MAX_SAFE_INTEGER,
    };
  });
});
const reviewAnswerRows = computed(() => answerRows.value.filter((answer) => answer.requiresReview));
const nonSignatureAnswerRows = computed(() => answerRows.value.filter((answer) => !answer.key.includes('signature') && !answer.key.includes('attestation')));
const answerSections = computed(() => {
  const groups = new Map<string, { key: string; label: string; order: number; answers: typeof nonSignatureAnswerRows.value }>();
  nonSignatureAnswerRows.value.forEach((answer) => {
    const section = groups.get(answer.sectionKey) ?? {
      key: answer.sectionKey,
      label: answer.sectionLabel,
      order: answer.sectionOrder,
      answers: [],
    };
    section.answers.push(answer);
    groups.set(answer.sectionKey, section);
  });

  return [...groups.values()]
    .map((section) => ({
      ...section,
      answers: [...section.answers].sort((first, second) => (
        first.fieldOrder - second.fieldOrder || first.key.localeCompare(second.key)
      )),
    }))
    .sort((first, second) => first.order - second.order || first.key.localeCompare(second.key));
});
const isAnswerSectionExpanded = (key: string) => expandedAnswerSections.value[key] !== false;
const toggleAnswerSection = (key: string) => {
  expandedAnswerSections.value[key] = !isAnswerSectionExpanded(key);
};
const scorePercent = computed(() => {
  const submission = selectedSubmission.value;
  if (!submission) return 0;
  if (typeof submission.score_details?.percent === 'number') return Math.max(0, Math.min(100, submission.score_details.percent));
  const max = submission.score_details?.max ?? submission.form_version?.scoring_max_score ?? submission.form?.scoring_max_score;
  if (!max || submission.score_total === null || submission.score_total === undefined) return 0;
  return Math.max(0, Math.min(100, (submission.score_total / Number(max)) * 100));
});
const scorePercentLabel = computed(() => `${scorePercent.value.toFixed(0)}% of maximum score`);
const gpsLabel = computed(() => {
  const submission = selectedSubmission.value;
  if (!submission?.gps_latitude || !submission?.gps_longitude) return '';

  const accuracy = submission.gps_accuracy_meters === null || submission.gps_accuracy_meters === undefined
    ? ''
    : ` | accuracy ${Math.round(submission.gps_accuracy_meters)}m`;
  return `${submission.gps_latitude.toFixed(6)}, ${submission.gps_longitude.toFixed(6)}${accuracy}`;
});

const issueCount = (submission: AssessmentSubmission): number => submission.validation_result?.errors?.length ?? 0;

const loadSubmissions = async () => {
  queue.value = await listSubmissions({ ...filters, per_page: 100 });
  if (!selectedUuid.value && queue.value.length > 0) {
    selectedUuid.value = queue.value[0].uuid;
  } else if (selectedUuid.value && !queue.value.some((submission) => submission.uuid === selectedUuid.value)) {
    selectedUuid.value = queue.value[0]?.uuid ?? '';
    reviewModalOpen.value = false;
  }
};

const loadExercises = async () => {
  const payload = await loadExerciseReport();
  exercises.value = payload.exercises;
};

const openReview = async (uuid: string) => {
  selectedUuid.value = uuid;
  reviewModalOpen.value = true;
  const submission = await getSubmission(uuid);
  const index = queue.value.findIndex((item) => item.uuid === uuid);
  if (index >= 0) {
    queue.value[index] = submission;
  }
  selectedUuid.value = uuid;
};

const reviewNext = async () => {
  const next = queue.value.find((submission) => ['submitted', 'under_review'].includes(submission.status));
  if (next) await openReview(next.uuid);
};

const closeReviewModal = () => {
  reviewModalOpen.value = false;
};

const openEvidence = async (url: string | null | undefined, attachmentId: number) => {
  if (!url || downloadingAttachmentId.value !== null) return;

  downloadingAttachmentId.value = attachmentId;
  const popup = window.open('', '_blank');

  try {
    const response = await authenticatedRequest(url, { method: 'GET' });
    const objectUrl = URL.createObjectURL(await response.blob());

    if (popup) {
      popup.location.href = objectUrl;
    } else {
      window.open(objectUrl, '_blank', 'noopener,noreferrer');
    }

    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
  } catch (error) {
    popup?.close();
    notice.value = error instanceof Error ? error.message : 'Unable to open this evidence file.';
  } finally {
    downloadingAttachmentId.value = null;
  }
};

const submitReview = async (action: 'start_review' | 'return' | 'approve') => {
  if (!selectedUuid.value) return;
  const note = [
    reviewNote.value,
    `Completeness: ${checks.completeness ? 'yes' : 'no'}`,
    `Evidence: ${checks.evidence ? 'yes' : 'no'}`,
  ].filter(Boolean).join('\n');

  const updated = await reviewSubmission(selectedUuid.value, action, note);
  const index = queue.value.findIndex((item) => item.uuid === updated.uuid);
  if (index >= 0) queue.value[index] = updated;
  notice.value = `Submission ${formatSubmissionStatus(updated.status).toLowerCase()}.`;
};

const scoreLabel = (submission: AssessmentSubmission) => {
  if (submission.score_total === null || submission.score_total === undefined) return '-';
  const max = submission.score_details?.max ?? submission.form_version?.scoring_max_score ?? submission.form?.scoring_max_score;
  return max ? `${submission.score_total.toFixed(1)} / ${Number(max).toFixed(1)}` : submission.score_total.toFixed(1);
};

const extractAnswerValue = (value: unknown): unknown => {
  if (value && typeof value === 'object' && !Array.isArray(value) && 'value' in value) {
    return (value as { value?: unknown }).value;
  }

  return value;
};

const formatAnswerValue = (value: unknown): string => {
  if (value === null || value === undefined || value === '') return 'Not answered';
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (Array.isArray(value)) return `${value.length} item(s)`;
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>;
    if ('answer' in record || 'count' in record) {
      return [record.answer, record.count ? `count: ${record.count}` : null].filter(Boolean).join(' | ') || 'Not answered';
    }
    if ('latitude' in record && 'longitude' in record) {
      return `${record.latitude}, ${record.longitude}`;
    }
    return JSON.stringify(value);
  }
  return String(value);
};

const formatBytes = (value?: number | null): string => {
  if (!value) return 'size unknown';
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};

const titleCase = (value: string): string => value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

watch(() => ({ ...filters }), () => {
  void loadSubmissions();
});

onMounted(async () => {
  await loadExercises();
  await loadSubmissions();
});
</script>

<style scoped>
.submissions-screen {
  gap: 14px;
}

.submission-header-actions,
.review-filters,
.review-summary-grid,
.submission-row,
.submission-row__status,
.submission-row__counts,
.review-detail__header,
.review-detail__header-actions,
.review-metrics,
.review-block__heading,
.action-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.submission-header-actions {
  justify-content: flex-end;
}

.review-filters {
  flex-wrap: wrap;
  padding: 12px;
}

.review-filters :deep(.qds-field) {
  min-width: 220px;
}

.review-filters :deep(.qds-search) {
  min-width: min(340px, 100%);
}

.review-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 11px;
}

.queue-panel,
.review-detail {
  padding: 16px;
}

.queue-list,
.review-detail,
.decision-card,
.decision-stack,
.evidence-list {
  display: grid;
  gap: 12px;
}

.submission-row {
  width: 100%;
  justify-content: space-between;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  padding: 12px;
  color: inherit;
  background: #fff;
  text-align: left;
  cursor: pointer;
}

.submission-row:hover,
.submission-row--active {
  border-color: rgba(8, 122, 74, 0.45);
  background: var(--qds-green-50);
}

.submission-row__status {
  width: 118px;
  align-items: flex-start;
  flex-direction: column;
}

.submission-row__status strong {
  color: var(--qds-slate-950);
  font-size: 12px;
  font-weight: 900;
}

.submission-row__main {
  display: grid;
  gap: 3px;
  flex: 1;
  min-width: 220px;
}

.submission-row__main strong,
.submission-row__meta strong,
.submission-identity strong {
  color: var(--qds-slate-950);
  font-size: 13px;
  font-weight: 900;
}

.submission-row small,
.submission-identity span,
.review-block p,
.review-block small,
.evidence-list small {
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 700;
}

.submission-row__meta {
  display: grid;
  gap: 3px;
  min-width: 170px;
}

.submission-row__counts {
  justify-content: flex-end;
  min-width: 132px;
}

.submission-row__action {
  display: grid;
  place-items: center;
  min-width: 112px;
  min-height: 40px;
  border: 1px solid rgba(8, 122, 74, 0.35);
  border-radius: 7px;
  color: var(--qds-green-800);
  background: #fff;
  font-size: 11px;
  font-weight: 900;
}

.submission-row__counts span {
  display: grid;
  place-items: center;
  min-width: 54px;
  padding: 8px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 7px;
  background: var(--qds-slate-50);
}

.submission-row__counts strong,
.review-metrics strong {
  color: var(--qds-slate-950);
  font-size: 18px;
  font-weight: 900;
}

.submission-row__counts small,
.review-metrics span {
  color: var(--qds-slate-500);
  font-size: 10px;
  font-weight: 900;
}

.review-modal {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: 26px;
}

.review-modal__backdrop {
  position: absolute;
  inset: 0;
  border: 0;
  background: rgba(15, 23, 42, 0.46);
  cursor: pointer;
}

.review-detail {
  position: relative;
  z-index: 1;
  width: min(980px, 100%);
  max-height: min(86vh, 900px);
  overflow: auto;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}

.review-detail__header {
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--qds-slate-200);
}

.review-detail__header-actions {
  justify-content: flex-end;
  flex-wrap: wrap;
}

.review-detail__header h2 {
  margin: 0 0 4px;
}

.review-detail__header p {
  margin: 0;
  color: var(--qds-slate-500);
  font-size: 12px;
  font-weight: 700;
}

.submission-identity,
.review-block,
.decision-card {
  display: grid;
  gap: 7px;
  padding: 13px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: var(--qds-slate-50);
}

.review-score-hero {
  display: grid;
  gap: 10px;
  padding: 16px;
  border: 1px solid rgba(8, 122, 74, 0.25);
  border-radius: 8px;
  color: var(--qds-white);
  background: var(--qds-green-900);
}

.review-score-hero > div:first-child {
  display: grid;
  gap: 3px;
}

.review-score-hero span,
.review-score-hero small {
  color: var(--qds-green-100);
  font-size: 11px;
  font-weight: 800;
}

.review-score-hero strong {
  font-size: 28px;
  line-height: 1.1;
}

.review-score-track {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
}

.review-score-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #8be0b5;
}

.review-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.review-metrics div {
  display: grid;
  gap: 5px;
  min-height: 70px;
  padding: 12px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: #fff;
}

.review-block h3,
.evidence-list h3,
.decision-card h3 {
  margin: 0;
  color: var(--qds-slate-950);
  font-size: 13px;
}

.review-block p {
  margin: 0;
  line-height: 1.55;
}

.review-block--compact {
  gap: 3px;
}

.warning {
  border-color: rgba(180, 35, 24, 0.25);
  background: #fff8f7;
}

.answer-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(120px, 0.8fr);
  gap: 10px;
  padding: 9px 0;
  border-top: 1px solid var(--qds-slate-200);
}

.answer-row span {
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 800;
}

.answer-row strong {
  color: var(--qds-slate-950);
  font-size: 11px;
}

.answer-row strong small {
  display: block;
  margin-top: 3px;
  color: var(--qds-green-700);
  font-size: 10px;
}

.answer-row.flagged {
  color: var(--qds-amber-700);
}

.answer-section {
  border-top: 1px solid var(--qds-slate-200);
}

.answer-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 12px;
  border: 0;
  padding: 12px 0;
  color: var(--qds-slate-800);
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.answer-section__header span {
  display: grid;
  gap: 3px;
}

.answer-section__header small {
  color: var(--qds-slate-500);
  font-size: 11px;
  font-weight: 700;
}

.answer-section__rows {
  padding-bottom: 4px;
}

.signature-preview {
  display: grid;
  justify-items: start;
  gap: 5px;
}

.signature-preview img {
  display: block;
  width: min(220px, 100%);
  height: 74px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 6px;
  object-fit: contain;
  background: #fff;
}

.evidence-list {
  padding: 13px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  background: #fff;
}

.evidence-list a,
.evidence-list button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px;
  border: 1px solid var(--qds-slate-200);
  border-radius: 8px;
  color: var(--qds-slate-950);
  background: var(--qds-slate-50);
  text-decoration: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.evidence-list button:disabled {
  cursor: wait;
  opacity: 0.65;
}

.evidence-list .app-icon {
  width: 18px;
  height: 18px;
  color: var(--qds-green-800);
}

.evidence-list strong {
  display: block;
  font-size: 12px;
}

.decision-card {
  background: #fff;
}

.action-row {
  justify-content: flex-end;
  flex-wrap: wrap;
}

.review-empty {
  display: grid;
  place-items: center;
  min-height: 190px;
  padding: 24px;
  border: 1px dashed var(--qds-slate-200);
  border-radius: 8px;
  color: var(--qds-slate-600);
  background: var(--qds-slate-50);
  text-align: center;
}

.review-empty--tall {
  min-height: 360px;
}

.review-empty span {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  margin-bottom: 10px;
  border-radius: 50%;
  color: var(--qds-green-800);
  background: var(--qds-green-100);
}

.review-empty strong {
  color: var(--qds-slate-950);
  font-size: 14px;
}

.review-empty p {
  max-width: 360px;
  margin: 6px 0 0;
  color: var(--qds-slate-500);
  font-size: 12px;
  font-weight: 700;
}

@media (max-width: 1200px) {
  .review-summary-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 820px) {
  .submission-header-actions,
  .review-filters,
  .submission-row,
  .submission-row__counts,
  .review-detail__header,
  .review-detail__header-actions,
  .action-row {
    align-items: stretch;
    flex-direction: column;
  }

  .review-summary-grid,
  .review-metrics {
    grid-template-columns: 1fr;
  }

  .submission-row__status,
  .submission-row__meta,
  .submission-row__counts,
  .submission-row__action {
    width: 100%;
    min-width: 0;
  }

  .review-modal {
    align-items: stretch;
    padding: 12px;
  }

  .review-detail {
    max-height: calc(100vh - 24px);
  }

  .answer-row {
    grid-template-columns: 1fr;
  }
}
</style>

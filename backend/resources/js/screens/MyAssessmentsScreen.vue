<template>
  <section class="screen-stack online-assessments-screen">
    <AppPageHeader title="My assessments" subtitle="Complete facility assessments assigned to you or your team" />

    <p v-if="notice" :class="['builder-notice', noticeTone === 'error' ? 'builder-notice--error' : '']">{{ notice }}</p>

    <template v-if="!selectedAssignment">
      <QdsCard class="qds-table-card">
        <div class="qds-card-header online-list-header">
          <div>
            <h2>Assigned assessments</h2>
            <p>{{ assignments.length }} assessment{{ assignments.length === 1 ? '' : 's' }} available</p>
          </div>
          <QdsButton variant="secondary" icon="refresh" @click="loadAssignments">Refresh</QdsButton>
        </div>

        <div class="online-assignment-list">
          <button
            v-for="assignment in assignments"
            :key="assignment.uuid"
            type="button"
            class="online-assignment-row"
            @click="openAssignment(assignment.uuid)"
          >
            <span class="online-assignment-row__main">
              <strong>{{ assignment.facility?.name || 'Facility not available' }}</strong>
              <small>{{ assignment.facility?.lga || 'No LGA' }} / {{ assignment.facility?.ward || 'No ward' }}</small>
              <small>{{ assignment.form?.title || 'Assessment form' }} · {{ assignment.exercise?.name || 'Exercise' }}</small>
            </span>
            <span class="online-assignment-row__meta">
              <QdsBadge :tone="onlineStatusTone(assignment.status)">{{ formatStatus(assignment.status) }}</QdsBadge>
              <small>Due {{ formatOnlineDate(assignment.due_at) }}</small>
            </span>
            <span class="online-assignment-row__action">{{ assignment.can_submit ? 'Open form' : 'View' }}</span>
          </button>

          <div v-if="!loading && assignments.length === 0" class="review-empty">
            <span><AppIcon name="clipboard" /></span>
            <strong>No assigned assessments</strong>
            <p>Assignments for your account or team will appear here.</p>
          </div>
          <div v-if="loading" class="review-empty">
            <strong>Loading assignments...</strong>
          </div>
        </div>
      </QdsCard>
    </template>

    <template v-else>
      <QdsCard class="online-assessment-context" padded>
        <div>
          <button class="online-back-button" type="button" @click="closeAssessment">
            <AppIcon name="arrowLeft" />
            <span>Back to assignments</span>
          </button>
          <h2>{{ selectedAssignment.form?.title || 'Online assessment' }}</h2>
          <p>{{ selectedAssignment.facility?.name }} · {{ selectedAssignment.exercise?.name }}</p>
        </div>
        <div class="online-assessment-context__meta">
          <QdsBadge :tone="onlineStatusTone(selectedAssignment.status)">{{ formatStatus(selectedAssignment.status) }}</QdsBadge>
          <small>Due {{ formatOnlineDate(selectedAssignment.due_at) }}</small>
          <small v-if="selectedAssignment.form?.version">Version {{ selectedAssignment.form.version }}</small>
        </div>
      </QdsCard>

      <QdsCard v-if="!selectedAssignment.can_submit" class="builder-notice builder-notice--warning" padded>
        This assessment is read-only because its assignment has already been submitted or closed.
      </QdsCard>

      <form class="online-assessment-form" @submit.prevent="saveAssessment('submitted')">
        <QdsCard v-for="section in formSections" :key="section.key" class="online-section" padded>
          <div class="online-section__heading">
            <div>
              <h2>{{ section.label }}</h2>
              <p v-if="section.description">{{ section.description }}</p>
            </div>
            <span>{{ sectionFields(section.key).length }} fields</span>
          </div>

          <div class="online-fields">
            <div
              v-for="field in sectionFields(section.key)"
              v-show="isVisible(field)"
              :key="field.key"
              class="online-field"
            >
              <div class="online-field__label">
                <label :for="`online-${field.key}`">{{ field.label }}</label>
                <span v-if="isRequired(field)" class="online-required">Required</span>
              </div>
              <small v-if="field.help_text" class="online-field__help">{{ field.help_text }}</small>

              <div v-if="field.key === 'facility_id'" class="online-assigned-field">
                <input :id="`online-${field.key}`" class="online-input" :value="String(selectedAssignment.facility?.id ?? '')" readonly disabled>
                <small>Locked to the facility assigned to this assessment.</small>
              </div>

              <div v-else-if="field.type === 'yes_no'" class="online-choice-list">
                <label v-for="option in field.options || []" :key="option.value" class="online-choice">
                  <input
                    type="radio"
                    :name="field.key"
                    :value="option.value"
                    :checked="answers[field.key] === option.value"
                    :disabled="!selectedAssignment.can_submit"
                    @change="setAnswer(field.key, option.value)"
                  >
                  <span>{{ option.label }}</span>
                </label>
              </div>

              <div v-else-if="field.type === 'yes_no_with_count'" class="online-compound-field">
                <div class="online-choice-list">
                  <label v-for="option in field.options || []" :key="option.value" class="online-choice">
                    <input
                      type="radio"
                      :name="field.key"
                      :value="option.value"
                      :checked="compoundAnswer(field.key).answer === option.value"
                      :disabled="!selectedAssignment.can_submit"
                      @change="setCompoundAnswer(field.key, 'answer', option.value)"
                    >
                    <span>{{ option.label }}</span>
                  </label>
                </div>
                <label v-if="compoundAnswer(field.key).answer === 'yes'" class="qds-field">
                  <span>{{ String(field.count_label || 'Total number') }}</span>
                  <input
                    :id="`online-${field.key}-count`"
                    type="number"
                    min="1"
                    :value="compoundAnswer(field.key).count ?? ''"
                    :disabled="!selectedAssignment.can_submit"
                    @input="setCompoundAnswer(field.key, 'count', numberValue($event))"
                  >
                </label>
              </div>

              <label v-else-if="field.type === 'checkbox'" class="qds-checkbox online-checkbox">
                <input
                  :id="`online-${field.key}`"
                  type="checkbox"
                  :checked="Boolean(answers[field.key])"
                  :disabled="!selectedAssignment.can_submit"
                  @change="setAnswer(field.key, ($event.target as HTMLInputElement).checked)"
                >
                <span>{{ field.label }}</span>
              </label>

              <div v-else-if="field.type === 'gps'" class="online-gps-field">
                <label class="qds-field">
                  <span>Latitude</span>
                  <input type="number" step="any" :value="gpsValue('latitude')" :disabled="!selectedAssignment.can_submit" @input="setGpsValue('latitude', $event)">
                </label>
                <label class="qds-field">
                  <span>Longitude</span>
                  <input type="number" step="any" :value="gpsValue('longitude')" :disabled="!selectedAssignment.can_submit" @input="setGpsValue('longitude', $event)">
                </label>
                <label class="qds-field">
                  <span>Accuracy in metres</span>
                  <input type="number" min="0" step="any" :value="gpsValue('accuracy_meters')" :disabled="!selectedAssignment.can_submit" @input="setGpsValue('accuracy_meters', $event)">
                </label>
                <QdsButton v-if="selectedAssignment.can_submit" type="button" variant="secondary" icon="location" @click="captureLocation">Use current location</QdsButton>
              </div>

              <div v-else-if="field.type === 'photo_collection' || field.type === 'file_collection'" class="online-file-field">
                <input :id="`online-${field.key}`" type="file" :accept="field.type === 'photo_collection' ? 'image/*' : '.jpg,.jpeg,.png,.pdf,.webp'" multiple :disabled="!selectedAssignment.can_submit" @change="selectFiles(field.key, $event)">
                <small>{{ selectedFiles[field.key]?.map((file) => file.name).join(', ') || 'No files selected' }}</small>
              </div>

              <textarea
                v-else-if="field.type === 'textarea'"
                :id="`online-${field.key}`"
                class="online-textarea"
                :value="String(answers[field.key] ?? '')"
                :disabled="field.readonly || !selectedAssignment.can_submit"
                    :placeholder="''"
                @input="setAnswer(field.key, stringValue($event))"
              />

              <select
                v-else-if="field.type === 'select' || field.type === 'reference_select'"
                :id="`online-${field.key}`"
                class="online-input"
                :value="String(answers[field.key] ?? '')"
                :disabled="field.readonly || !selectedAssignment.can_submit"
                @change="setAnswer(field.key, stringValue($event))"
              >
                <option value="">Select an option</option>
                <option v-for="option in field.options || []" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>

              <input
                v-else
                :id="`online-${field.key}`"
                class="online-input"
                :type="inputType(field.type)"
                :value="String(answers[field.key] ?? '')"
                :readonly="field.readonly"
                :disabled="!selectedAssignment.can_submit"
                @input="setAnswer(field.key, inputValue($event, field.type))"
              >

              <small v-if="fieldErrors[field.key]" class="online-field__error">{{ fieldErrors[field.key] }}</small>
            </div>
          </div>
        </QdsCard>

        <QdsCard class="online-form-actions" padded>
          <p>Save a draft to continue later. Submit when every required field is complete.</p>
          <div>
            <QdsButton v-if="selectedAssignment.can_submit" type="button" variant="secondary" icon="save" :disabled="saving" @click="saveAssessment('draft')">{{ saving ? 'Saving...' : 'Save draft' }}</QdsButton>
            <QdsButton v-if="selectedAssignment.can_submit" type="submit" icon="check" :disabled="saving">{{ saving ? 'Submitting...' : 'Submit assessment' }}</QdsButton>
            <QdsButton v-else type="button" variant="secondary" @click="closeAssessment">Back to assignments</QdsButton>
          </div>
        </QdsCard>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import {
  formatOnlineDate,
  getOnlineAssignment,
  listOnlineAssignments,
  onlineStatusTone,
  saveOnlineAssessment,
  uploadOnlineEvidence,
  type OnlineAssignment,
  type OnlineField,
  type OnlineSection,
} from '../services/onlineAssessments';
import type { OdkUser } from '../services/teamAccess';

const props = defineProps<{ user: OdkUser | null }>();

const assignments = ref<OnlineAssignment[]>([]);
const selectedAssignment = ref<OnlineAssignment | null>(null);
const answers = reactive<Record<string, unknown>>({});
const selectedFiles = reactive<Record<string, File[]>>({});
const fieldErrors = reactive<Record<string, string>>({});
const notice = ref('');
const noticeTone = ref<'info' | 'error'>('info');
const loading = ref(false);
const saving = ref(false);
const startedAt = ref(new Date().toISOString());

const formVersion = computed(() => selectedAssignment.value?.form?.version_data);
const formSections = computed<OnlineSection[]>(() => (formVersion.value?.sections || []).filter((section) => sectionFields(section.key).length > 0));

const sectionFields = (sectionKey: string): OnlineField[] => (
  (formVersion.value?.fields || []).filter((field) => field.section === sectionKey && field.type !== 'signature' && !['lga_id', 'ward_id'].includes(field.key))
);

const loadAssignments = async () => {
  loading.value = true;
  notice.value = '';
  try {
    assignments.value = await listOnlineAssignments();
  } catch (error) {
    noticeTone.value = 'error';
    notice.value = error instanceof Error ? error.message : 'Unable to load assigned assessments.';
  } finally {
    loading.value = false;
  }
};

const openAssignment = async (uuid: string) => {
  loading.value = true;
  notice.value = '';
  try {
    const assignment = await getOnlineAssignment(uuid);
    selectedAssignment.value = assignment;
    resetAnswers(assignment);
  } catch (error) {
    noticeTone.value = 'error';
    notice.value = error instanceof Error ? error.message : 'Unable to open this assessment.';
  } finally {
    loading.value = false;
  }
};

const resetAnswers = (assignment: OnlineAssignment) => {
  Object.keys(answers).forEach((key) => delete answers[key]);
  Object.keys(selectedFiles).forEach((key) => delete selectedFiles[key]);
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

  Object.assign(answers, assignment.submission?.answers || {});
  const facility = assignment.facility;
  const user = props.user;
  const today = new Date().toISOString().slice(0, 10);
  const fields = assignment.form?.version_data?.fields || [];

  const defaults: Record<string, unknown> = {
    assessment_id: answers.assessment_id || `WEB-${assignment.uuid.slice(0, 8).toUpperCase()}`,
    assessment_date: answers.assessment_date || today,
    assessor_id: answers.assessor_id || user?.staff_code || String(user?.id || ''),
    facility_id: answers.facility_id || facility?.id || '',
    facility_name: answers.facility_name || facility?.name || '',
    lga_id: answers.lga_id || facility?.lga_id || '',
    ward_id: answers.ward_id || facility?.ward_id || '',
  };

  fields.forEach((field) => {
    if (answers[field.key] === undefined && defaults[field.key] !== undefined) {
      answers[field.key] = defaults[field.key];
    }
  });
  startedAt.value = new Date().toISOString();
};

const closeAssessment = () => {
  selectedAssignment.value = null;
  void loadAssignments();
};

const isVisible = (field: OnlineField): boolean => {
  const condition = field.visible_if;
  if (!condition?.field) return true;
  const actual = answers[condition.field];
  return condition.operator === 'not_equals' ? actual !== condition.value : actual === condition.value;
};

const isRequired = (field: OnlineField): boolean => {
  if (field.required) return true;
  if (!field.required_if) return false;
  return Object.entries(field.required_if).every(([key, value]) => answers[key] === value);
};

const setAnswer = (key: string, value: unknown) => {
  answers[key] = value;
  delete fieldErrors[key];
};

const compoundAnswer = (key: string): Record<string, unknown> => {
  const value = answers[key];
  return value && typeof value === 'object' ? value as Record<string, unknown> : {};
};

const setCompoundAnswer = (key: string, property: string, value: unknown) => {
  answers[key] = { ...compoundAnswer(key), [property]: value };
  delete fieldErrors[key];
};

const gpsValue = (key: string): string => String((answers.gps_coordinates as Record<string, unknown> | undefined)?.[key] ?? '');

const setGpsValue = (key: string, event: Event) => {
  const input = event.target as HTMLInputElement;
  answers.gps_coordinates = { ...(answers.gps_coordinates as Record<string, unknown> || {}), [key]: input.value === '' ? '' : Number(input.value) };
};

const captureLocation = () => {
  if (!navigator.geolocation) {
    noticeTone.value = 'error';
    notice.value = 'Location is not available in this browser.';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      answers.gps_coordinates = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy_meters: position.coords.accuracy,
      };
      noticeTone.value = 'info';
      notice.value = 'Current location captured.';
    },
    () => {
      noticeTone.value = 'error';
      notice.value = 'Unable to capture your location. Enter the coordinates manually.';
    },
  );
};

const selectFiles = (key: string, event: Event) => {
  const input = event.target as HTMLInputElement;
  selectedFiles[key] = Array.from(input.files || []);
};

const validateBeforeSubmit = (): boolean => {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
  let valid = true;

  for (const field of formVersion.value?.fields || []) {
    if (field.type === 'signature') continue;
    if (!isVisible(field) || !isRequired(field)) continue;
    const value = field.type === 'yes_no_with_count' ? compoundAnswer(field.key).answer : answers[field.key];
    if (value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
      fieldErrors[field.key] = `${field.label} is required.`;
      valid = false;
    }
    if (field.type === 'yes_no_with_count' && compoundAnswer(field.key).answer === 'yes' && !compoundAnswer(field.key).count) {
      fieldErrors[field.key] = `${String(field.count_label || 'Total number')} is required.`;
      valid = false;
    }
  }

  if (!valid) {
    noticeTone.value = 'error';
    notice.value = 'Complete the highlighted required fields before submitting.';
  }

  return valid;
};

const saveAssessment = async (status: 'draft' | 'submitted') => {
  if (!selectedAssignment.value || !selectedAssignment.value.can_submit) return;
  if (status === 'submitted' && !validateBeforeSubmit()) return;

  saving.value = true;
  notice.value = '';
  try {
    const result = await saveOnlineAssessment(selectedAssignment.value.uuid, {
      client_submission_id: `online-${selectedAssignment.value.uuid}`,
      status,
      assessment_date: typeof answers.assessment_date === 'string' ? answers.assessment_date : undefined,
      answers: { ...answers },
      gps: typeof answers.gps_coordinates === 'object' ? answers.gps_coordinates as Record<string, unknown> : undefined,
      started_at: startedAt.value,
      submitted_at: status === 'submitted' ? new Date().toISOString() : undefined,
    });

    if (result.uuid) {
      for (const [fieldKey, files] of Object.entries(selectedFiles)) {
        for (const file of files) {
          await uploadOnlineEvidence(String(result.uuid), file, fieldKey);
        }
      }
    }

    noticeTone.value = 'info';
    notice.value = status === 'draft' ? 'Draft saved. You can continue this assessment later.' : 'Assessment submitted successfully.';
    if (status === 'submitted') {
      closeAssessment();
      noticeTone.value = 'info';
      notice.value = 'Assessment submitted successfully.';
    } else {
      selectedAssignment.value = await getOnlineAssignment(selectedAssignment.value.uuid);
      resetAnswers(selectedAssignment.value);
    }
  } catch (error) {
    noticeTone.value = 'error';
    notice.value = error instanceof Error ? error.message : 'Unable to save this assessment.';
  } finally {
    saving.value = false;
  }
};

const inputType = (type: string): string => {
  if (type === 'date') return 'date';
  if (type === 'currency' || type === 'number') return 'number';
  return 'text';
};

const inputValue = (event: Event, type: string): string | number => {
  const value = (event.target as HTMLInputElement).value;
  return inputType(type) === 'number' && value !== '' ? Number(value) : value;
};

const stringValue = (event: Event): string => (event.target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement).value;
const numberValue = (event: Event): number | string => {
  const value = (event.target as HTMLInputElement).value;
  return value === '' ? '' : Number(value);
};
const formatStatus = (status: string): string => status.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

onMounted(loadAssignments);
</script>

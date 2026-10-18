import { authenticatedRequest, getOdkToken } from './auth';

export type FormStatus = 'draft' | 'published' | 'archived' | 'retired' | string;

export type AssessmentFormVersion = {
  id?: number;
  version?: number | string;
  status?: FormStatus;
  offline_enabled?: boolean;
  requires_scoring?: boolean;
  scoring_max_score?: number | string | null;
  sections?: unknown[];
  fields?: unknown[];
  validation_rules?: unknown[];
  visibility_rules?: unknown[];
  scoring_rules?: unknown[];
  required_evidence_rules?: unknown[];
  signature_rules?: unknown[];
  review_flags?: unknown[];
  published_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type BuilderOption = {
  id?: number;
  label: string;
  value: string;
  score?: number | null;
  requires_review?: boolean;
  needs_review?: boolean;
};

export type BuilderSection = {
  id?: number;
  key: string;
  label: string;
  description?: string | null;
  sort_order?: number;
  is_repeatable?: boolean;
  min_repeats?: number | null;
  max_repeats?: number | null;
  fields?: BuilderField[];
};

export type BuilderField = {
  id?: number;
  key: string;
  label: string;
  type: string;
  section?: BuilderSection | null;
  section_key?: string | null;
  assessment_form_section_id?: number | null;
  required?: boolean;
  readonly?: boolean;
  sort_order?: number;
  group?: string | null;
  validation_rules?: string[];
  display_logic?: Record<string, unknown> | null;
  required_logic?: Record<string, unknown> | null;
  settings?: Record<string, unknown> | null;
  options?: BuilderOption[];
};

export type BuilderRule = {
  id?: number;
  type: string;
  name?: string | null;
  field?: BuilderField | null;
  condition?: Record<string, unknown> | null;
  action?: Record<string, unknown> | null;
  message?: string | null;
  severity?: string;
  requires_review?: boolean;
};

export type AssessmentForm = {
  id?: number;
  slug: string;
  title: string;
  description?: string | null;
  target_audience?: string | null;
  requires_scoring?: boolean;
  scoring_max_score?: number | string | null;
  status: FormStatus;
  published_at?: string | null;
  updated_at?: string | null;
  current_version?: AssessmentFormVersion | null;
  versions?: AssessmentFormVersion[];
  sections?: BuilderSection[];
  fields?: BuilderField[];
  builder_rules?: BuilderRule[];
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

const fallbackForms: AssessmentForm[] = [
  {
    slug: 'nicare-qa-checklist',
    title: 'DataKit QA Checklist',
    description: 'Comprehensive facility assessment checklist',
    target_audience: 'Facility assessment teams',
    requires_scoring: true,
    scoring_max_score: 100,
    status: 'draft',
    updated_at: '2025-01-10T09:30:00Z',
    current_version: {
      version: 1,
      status: 'draft',
      offline_enabled: true,
      signature_rules: [
        { label: 'Facility representative signature', required: true },
        { label: 'Assessor signature', required: true },
        { label: 'Additional signatory', required: false },
      ],
    },
  },
  {
    slug: 'facility-follow-up',
    title: 'Facility follow-up',
    description: 'Follow-up assessment form for previously visited facilities',
    target_audience: 'Facility assessment teams',
    status: 'published',
    updated_at: '2025-01-05T11:15:00Z',
    current_version: {
      version: 2,
      status: 'published',
      offline_enabled: true,
      published_at: '2025-01-05T11:15:00Z',
    },
  },
  {
    slug: 'infection-control',
    title: 'Infection control',
    description: 'Infection control services assessment',
    target_audience: 'Facility assessment teams',
    status: 'draft',
    updated_at: '2025-01-03T08:00:00Z',
    current_version: { version: 1, status: 'draft', offline_enabled: false },
  },
  {
    slug: 'laboratory-services',
    title: 'Laboratory services',
    description: 'Laboratory capacity and services',
    target_audience: 'Facility assessment teams',
    status: 'published',
    updated_at: '2025-01-02T13:20:00Z',
    current_version: { version: 1, status: 'published', offline_enabled: true },
  },
];

const readJson = async <T>(url: string): Promise<T> => {
  const response = await authenticatedRequest(url, { method: 'GET' });
  if (!response.ok) {
    throw new Error(`Request failed with status ${response.status}.`);
  }

  return response.json() as Promise<T>;
};

const writeJson = async <T>(url: string, method: string, body?: unknown): Promise<T> => {
  const response = await authenticatedRequest(url, {
    method,
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  return response.json() as Promise<T>;
};

export const listAssessmentForms = async (): Promise<AssessmentForm[]> => {
  try {
    const payload = await readJson<ApiEnvelope<AssessmentForm[]>>('/api/forms');
    return payload.data.length > 0 || getOdkToken() ? payload.data : fallbackForms;
  } catch (error) {
    if (getOdkToken()) throw error;
    return fallbackForms;
  }
};

export const getAssessmentForm = async (slug: string): Promise<AssessmentForm> => {
  try {
    const payload = await readJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}`);
    return payload.data;
  } catch (error) {
    if (getOdkToken()) throw error;
    return fallbackForms.find((form) => form.slug === slug) ?? fallbackForms[0];
  }
};

type AssessmentFormSettingsPayload = Pick<AssessmentForm, 'title' | 'description' | 'target_audience' | 'requires_scoring' | 'scoring_max_score'>;

export const createAssessmentForm = async (payload: AssessmentFormSettingsPayload): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>('/api/forms', 'POST', payload);
  return response.data;
};

export const updateAssessmentForm = async (slug: string, payload: AssessmentFormSettingsPayload): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}`, 'PATCH', payload);
  return response.data;
};

export const cloneAssessmentForm = async (slug: string): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}/clone`, 'POST');
  return response.data;
};

export const createFormField = async (slug: string, payload: Partial<BuilderField>): Promise<BuilderField> => {
  const response = await writeJson<ApiEnvelope<BuilderField>>(`/api/forms/${slug}/fields`, 'POST', payload);
  return response.data;
};

export const createFormSection = async (slug: string, payload: Partial<BuilderSection>): Promise<BuilderSection> => {
  const response = await writeJson<ApiEnvelope<BuilderSection>>(`/api/forms/${slug}/sections`, 'POST', payload);
  return response.data;
};

export const reorderFormSections = async (slug: string, sectionIds: number[]): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}/sections/order`, 'PUT', {
    section_ids: sectionIds,
  });
  return response.data;
};

export const updateFormField = async (slug: string, fieldId: number, payload: Partial<BuilderField>): Promise<BuilderField> => {
  const response = await writeJson<ApiEnvelope<BuilderField>>(`/api/forms/${slug}/fields/${fieldId}`, 'PATCH', payload);
  return response.data;
};

export const reorderFormFields = async (
  slug: string,
  payload: { section_key?: string | null; field_ids: number[] },
): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}/fields/order`, 'PUT', payload);
  return response.data;
};

export const deleteFormField = async (slug: string, fieldId: number): Promise<void> => {
  await writeJson<ApiEnvelope<{ success: boolean }>>(`/api/forms/${slug}/fields/${fieldId}`, 'DELETE');
};

export const deleteFormSection = async (slug: string, sectionId: number): Promise<void> => {
  await writeJson<ApiEnvelope<{ success: boolean }>>(`/api/forms/${slug}/sections/${sectionId}`, 'DELETE');
};

export const publishAssessmentForm = async (slug: string): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}/publish`, 'POST');
  return response.data;
};

export const repairAssessmentFormFieldOrder = async (slug: string): Promise<AssessmentForm> => {
  const response = await writeJson<ApiEnvelope<AssessmentForm>>(`/api/forms/${slug}/repair-field-order`, 'POST');
  return response.data;
};

export const testFormRules = async (slug: string, answers: Record<string, unknown>): Promise<Record<string, unknown>> => {
  const response = await writeJson<ApiEnvelope<Record<string, unknown>>>(`/api/forms/${slug}/rules/test`, 'POST', { answers });
  return response.data;
};

export const formatDate = (value?: string | null): string => {
  if (!value) return 'Not updated';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
};

export const statusTone = (status?: FormStatus): 'green' | 'amber' | 'slate' | 'red' => {
  if (status === 'published') return 'green';
  if (status === 'draft') return 'amber';
  if (status === 'retired') return 'red';
  return 'slate';
};

export const titleCase = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

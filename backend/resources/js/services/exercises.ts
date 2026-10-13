import { authenticatedRequest } from './auth';

export type ExerciseStatus = 'draft' | 'scheduled' | 'active' | 'closed' | 'archived' | string;
export type ExercisePeriodType = 'quarter' | 'month' | 'custom';

export type ExerciseFormOption = {
  id: number;
  slug: string;
  title: string;
  versions: Array<{
    id: number;
    version: number | string;
    published_at?: string | null;
  }>;
};

export type AssessmentExercise = {
  id: number;
  uuid: string;
  name: string;
  code: string;
  description?: string | null;
  period_type: ExercisePeriodType;
  year: number;
  quarter?: number | null;
  month?: number | null;
  starts_at: string;
  ends_at: string;
  assessment_form_id: number;
  assessment_form_version_id: number;
  status: ExerciseStatus;
  target_type?: string;
  form?: {
    id: number;
    slug: string;
    title: string;
  } | null;
  form_version?: {
    id: number;
    version: number | string;
    status: string;
    published_at?: string | null;
  } | null;
};

export type ExercisePayload = {
  name: string;
  description?: string | null;
  period_type: ExercisePeriodType;
  year: number;
  quarter?: number | null;
  month?: number | null;
  starts_at: string;
  ends_at: string;
  assessment_form_id: number;
  assessment_form_version_id: number;
  status: ExerciseStatus;
  target_type?: string;
};

export type ExercisesPayload = {
  exercises: {
    data: AssessmentExercise[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  form_options: ExerciseFormOption[];
  stats: {
    total: number;
    draft: number;
    scheduled: number;
    active: number;
    closed: number;
  };
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await authenticatedRequest(url, { method: 'GET' });
  if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
  return response.json() as Promise<T>;
};

const writeJson = async <T>(url: string, method: 'POST' | 'PATCH', body: unknown): Promise<T> => {
  const response = await authenticatedRequest(url, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) throw new Error(await response.text());
  return response.json() as Promise<T>;
};

export type ExerciseQuery = {
  q?: string;
  status?: string;
  period_type?: string;
  year?: string;
  page?: number;
  per_page?: number;
};

export const loadAssessmentExercises = async (query: ExerciseQuery = {}): Promise<ExercisesPayload> => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') params.set(key, String(value));
  });

  const payload = await readJson<ApiEnvelope<ExercisesPayload>>(`/api/exercises?${params.toString()}`);
  return payload.data;
};

export const createAssessmentExercise = async (payload: ExercisePayload): Promise<AssessmentExercise> => {
  const response = await writeJson<ApiEnvelope<AssessmentExercise>>('/api/exercises', 'POST', payload);
  return response.data;
};

export const updateAssessmentExercise = async (uuid: string, payload: ExercisePayload): Promise<AssessmentExercise> => {
  const response = await writeJson<ApiEnvelope<AssessmentExercise>>(`/api/exercises/${uuid}`, 'PATCH', payload);
  return response.data;
};

export const statusTone = (status?: ExerciseStatus): 'green' | 'amber' | 'blue' | 'red' | 'slate' => {
  if (status === 'active') return 'green';
  if (status === 'scheduled') return 'blue';
  if (status === 'draft') return 'amber';
  if (status === 'closed') return 'slate';
  if (status === 'archived') return 'red';
  return 'slate';
};

export const titleCase = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const formatDate = (value?: string | null): string => {
  if (!value) return 'Not set';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
};

import { authenticatedRequest } from './auth';

export type ReportExerciseOption = {
  id: number;
  uuid: string;
  name: string;
  code: string;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  form_title?: string | null;
  assignments_count: number;
  submissions_count: number;
};

export type ExerciseReportSummary = {
  total_assignments: number;
  assigned: number;
  in_progress: number;
  submitted_assignments: number;
  reviewed_assignments: number;
  pending_facilities: number;
  completion_rate: number;
  review_rate: number;
  submissions: number;
  awaiting_review: number;
  returned: number;
  approved: number;
  average_score: number;
};

export type ProgressRow = {
  id?: number | null;
  name: string;
  email?: string | null;
  assignments: number;
  submitted: number;
  reviewed: number;
  pending: number;
  completion_rate: number;
};

export type FacilityCompletionRow = {
  assignment_id: number;
  facility_name: string;
  facility_code?: string | null;
  lga?: string | null;
  ward?: string | null;
  assignee_type: string;
  assignee_name: string;
  assignment_status: string;
  submitted_at?: string | null;
  submission_status?: string | null;
  score_total?: number | null;
  reviewed_at?: string | null;
};

export type ReviewQueueRow = {
  uuid: string;
  assessment_id: string;
  facility_name: string;
  assessor_name?: string | null;
  submitted_at?: string | null;
  status: string;
  score_total?: number | null;
  form_title?: string | null;
};

export type ExerciseReportPayload = {
  exercises: ReportExerciseOption[];
  selected_exercise?: ReportExerciseOption | null;
  summary: ExerciseReportSummary;
  assessor_progress: ProgressRow[];
  team_progress: ProgressRow[];
  facility_completion: FacilityCompletionRow[];
  review_queue: ReviewQueueRow[];
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

export const loadExerciseReport = async (exerciseId?: string | number): Promise<ExerciseReportPayload> => {
  const params = new URLSearchParams();
  if (exerciseId) params.set('exercise_id', String(exerciseId));

  const payload = await readJson<ApiEnvelope<ExerciseReportPayload>>(`/api/reports/exercises?${params.toString()}`);
  return payload.data;
};

export const exerciseReportExportUrl = (exerciseId: string | number, type: 'submissions' | 'facility_completion' | 'assessor_progress' | 'team_progress'): string => {
  const params = new URLSearchParams({
    exercise_id: String(exerciseId),
    type,
  });

  return `/api/reports/exercises/export?${params.toString()}`;
};

export const titleCase = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const formatDateTime = (value?: string | null): string => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

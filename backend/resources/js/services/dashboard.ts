export type DashboardTone = 'green' | 'amber' | 'blue' | 'slate' | 'red';

export type DashboardExercise = {
  id: number;
  uuid: string;
  name: string;
  code: string;
  status: string;
  period_type?: string;
  starts_at?: string | null;
  ends_at?: string | null;
  form_title?: string | null;
  form_slug?: string | null;
  form_version?: number | string | null;
};

export type DashboardMetrics = {
  total_exercises: number;
  active_exercises: number;
  total_assignments: number;
  assigned: number;
  in_progress: number;
  submitted_assignments: number;
  reviewed_assignments: number;
  pending_facilities: number;
  completion_rate: number;
  review_rate: number;
  submissions_in_range: number;
  awaiting_review: number;
  returned: number;
  approved: number;
  average_score: number;
};

export type DashboardChartPoint = {
  label: string;
  date: string;
  count: number;
};

export type DashboardRecentSubmission = {
  uuid: string;
  assessment_id: string;
  facility_name: string;
  assessor_name?: string | null;
  exercise_name?: string | null;
  submitted_at?: string | null;
  status: string;
  score_total?: number | null;
};

export type DashboardRecentAssignment = {
  uuid: string;
  facility_name: string;
  exercise_name?: string | null;
  assignee_name: string;
  assignee_type: string;
  status: string;
  due_at?: string | null;
  updated_at?: string | null;
};

export type DashboardPayload = {
  range: {
    key: string;
    label: string;
    date_from: string;
    date_to: string;
  };
  current_exercise?: DashboardExercise | null;
  metrics: DashboardMetrics;
  chart: {
    points: DashboardChartPoint[];
    max: number;
  };
  recent_submissions: DashboardRecentSubmission[];
  recent_assignments: DashboardRecentAssignment[];
  exercise_options: DashboardExercise[];
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
  return response.json() as Promise<T>;
};

export const loadDashboard = async (query: { range?: string; exercise_id?: string | number } = {}): Promise<DashboardPayload> => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') params.set(key, String(value));
  });

  const payload = await readJson<ApiEnvelope<DashboardPayload>>(`/api/dashboard?${params.toString()}`);
  return payload.data;
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

export const statusTone = (status?: string | null): DashboardTone => {
  if (status === 'active' || status === 'approved' || status === 'reviewed') return 'green';
  if (status === 'scheduled' || status === 'submitted') return 'blue';
  if (status === 'draft' || status === 'assigned' || status === 'in_progress' || status === 'under_review') return 'amber';
  if (status === 'returned' || status === 'cancelled' || status === 'expired' || status === 'archived') return 'red';
  return 'slate';
};

export type SubmissionStatus = 'draft' | 'submitted' | 'under_review' | 'returned' | 'reviewed' | 'approved' | string;

export type AssessmentSubmission = {
  uuid: string;
  assessment_id: string;
  client_submission_id?: string | null;
  facility_name: string;
  facility_code?: string | null;
  facility_type?: string | null;
  lga_name?: string | null;
  ward_name?: string | null;
  assessor_name?: string | null;
  assessor_id?: string | null;
  status: SubmissionStatus;
  sync_status?: string | null;
  score_total?: number | null;
  score_details?: {
    total?: number;
    max?: number | null;
    percent?: number | null;
    fields?: Array<{ field?: string; score?: number; label?: string; max_score?: number | null }>;
  } | null;
  gps_latitude?: number | null;
  gps_longitude?: number | null;
  gps_accuracy_meters?: number | null;
  facility_snapshot?: Record<string, unknown> | null;
  assessor_snapshot?: Record<string, unknown> | null;
  device_snapshot?: Record<string, unknown> | null;
  answers_snapshot?: Record<string, unknown> | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;
  review_note?: string | null;
  assessment_exercise_id?: number | null;
  assessment_assignment_id?: number | null;
  validation_result?: {
    valid?: boolean;
    errors?: Array<{ field: string; message: string }>;
  } | null;
  form?: {
    title: string;
    slug: string;
    requires_scoring?: boolean;
    scoring_max_score?: number | string | null;
  } | null;
  form_version?: {
    version: number;
    requires_scoring?: boolean;
    scoring_max_score?: number | string | null;
    fields?: Array<{ key: string; label?: string; type?: string; section?: string }>;
    sections?: Array<{ key: string; label?: string }>;
  } | null;
  exercise?: {
    id: number;
    uuid: string;
    name: string;
    code: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
  } | null;
  assignment?: {
    id: number;
    uuid: string;
    status: string;
    assignee_type: string;
    user?: { id: number; name: string; email?: string | null } | null;
    team?: { id: number; name: string } | null;
  } | null;
  answers?: Array<{
    id?: number;
    field_key?: string;
    section_key?: string | null;
    value?: { value?: unknown } | unknown;
    score?: number | null;
    requires_review?: boolean;
    metadata?: Record<string, unknown> | null;
  }>;
  attachments?: Array<{
    id?: number;
    field_key?: string | null;
    type?: string | null;
    original_name?: string | null;
    mime_type?: string | null;
    size_bytes?: number | null;
    captured_at?: string | null;
    download_url?: string | null;
  }>;
  reviews?: Array<Record<string, unknown>>;
  sync_events?: Array<{
    id?: number;
    status?: string | null;
    direction?: string | null;
    message?: string | null;
    attempted_at?: string | null;
    payload?: Record<string, unknown> | null;
  }>;
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

type Paginated<T> = {
  data: T[];
};

export type SubmissionQuery = {
  exercise_id?: string | number;
  status?: string;
  facility?: string;
  per_page?: number;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
  return response.json() as Promise<T>;
};

const writeJson = async <T>(url: string, body: unknown): Promise<T> => {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });
  if (!response.ok) throw new Error(await response.text());
  return response.json() as Promise<T>;
};

export const listSubmissions = async (query: SubmissionQuery = {}): Promise<AssessmentSubmission[]> => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') params.set(key, String(value));
  });

  const payload = await readJson<ApiEnvelope<Paginated<AssessmentSubmission>>>(`/api/submissions?${params.toString()}`);
  return payload.data.data;
};

export const getSubmission = async (uuid: string): Promise<AssessmentSubmission> => {
  const payload = await readJson<ApiEnvelope<AssessmentSubmission>>(`/api/submissions/${uuid}`);
  return payload.data;
};

export const reviewSubmission = async (uuid: string, action: 'start_review' | 'return' | 'review' | 'approve', note: string): Promise<AssessmentSubmission> => {
  const payload = await writeJson<ApiEnvelope<AssessmentSubmission>>(`/api/submissions/${uuid}/review`, {
    action,
    note,
    reviewer_name: 'Portal reviewer',
  });
  return payload.data;
};

export const submissionTone = (status?: SubmissionStatus): 'green' | 'amber' | 'blue' | 'red' | 'slate' => {
  if (status === 'approved' || status === 'reviewed') return 'green';
  if (status === 'submitted') return 'blue';
  if (status === 'under_review') return 'amber';
  if (status === 'returned') return 'red';
  return 'slate';
};

export const formatSubmissionStatus = (status?: string | null): string => {
  if (!status) return 'Unknown';
  return status.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const formatDateTime = (value?: string | null): string => {
  if (!value) return 'Not submitted';
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

import { authenticatedRequest } from './auth';

export type OnlineFieldOption = {
  label: string;
  value: string;
  score?: number | null;
  needs_review?: boolean;
};

export type OnlineField = {
  key: string;
  label: string;
  type: string;
  section?: string;
  required?: boolean;
  readonly?: boolean;
  rules?: string[];
  options?: OnlineFieldOption[];
  visible_if?: { field?: string; operator?: string; value?: unknown };
  required_if?: Record<string, unknown>;
  [key: string]: unknown;
};

export type OnlineSection = {
  key: string;
  label: string;
  description?: string | null;
  order?: number;
};

export type OnlineFormVersion = {
  id: number;
  version: number;
  sections: OnlineSection[];
  fields: OnlineField[];
  validation_rules?: unknown[];
  visibility_rules?: unknown[];
  scoring_rules?: unknown[];
  required_evidence_rules?: unknown[];
  signature_rules?: unknown[];
};

export type OnlineAssignment = {
  id: number;
  uuid: string;
  status: string;
  can_submit: boolean;
  allow_duplicate_submission: boolean;
  assigned_at?: string | null;
  due_at?: string | null;
  exercise?: {
    id: number;
    uuid: string;
    name: string;
    code: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
  } | null;
  facility?: {
    id?: number | null;
    local_id?: number | null;
    code?: string | null;
    name: string;
    lga_id?: number | null;
    lga?: string | null;
    ward_id?: number | null;
    ward?: string | null;
  } | null;
  form?: {
    slug: string;
    title: string;
    description?: string | null;
    version?: number | null;
    version_data?: OnlineFormVersion;
  } | null;
  submission?: {
    uuid: string;
    status: string;
    answers?: Record<string, unknown> | null;
    validation_result?: { errors?: Array<{ field: string; message: string }> } | null;
    submitted_at?: string | null;
    attachments?: Array<{
      id?: number;
      field_key?: string | null;
      type?: string | null;
      original_name?: string | null;
      mime_type?: string | null;
      download_url?: string | null;
    }>;
  } | null;
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await authenticatedRequest(url, { method: 'GET' });
  const payload = await response.json() as ApiEnvelope<T>;
  return payload.data;
};

export const listOnlineAssignments = async (): Promise<OnlineAssignment[]> => (
  readJson<OnlineAssignment[]>('/api/my/assignments')
);

export const getOnlineAssignment = async (uuid: string): Promise<OnlineAssignment> => (
  readJson<OnlineAssignment>(`/api/my/assignments/${uuid}`)
);

export const saveOnlineAssessment = async (
  uuid: string,
  payload: {
    client_submission_id: string;
    status: 'draft' | 'submitted';
    assessment_date?: string;
    answers: Record<string, unknown>;
    gps?: Record<string, unknown>;
    started_at?: string;
    submitted_at?: string;
  },
): Promise<Record<string, unknown>> => {
  const response = await authenticatedRequest(`/api/my/assignments/${uuid}/submissions`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  const result = await response.json() as ApiEnvelope<Record<string, unknown>>;
  return result.data;
};

export const uploadOnlineEvidence = async (
  submissionUuid: string,
  file: File,
  fieldKey: string,
): Promise<Record<string, unknown>> => {
  const body = new FormData();
  body.append('file', file);
  body.append('field_key', fieldKey);
  body.append('type', file.type.startsWith('image/') ? 'photo' : 'document');

  const response = await authenticatedRequest(`/api/my/submissions/${submissionUuid}/attachments`, {
    method: 'POST',
    body,
  });
  const result = await response.json() as ApiEnvelope<Record<string, unknown>>;
  return result.data;
};

export const onlineStatusTone = (status?: string): 'green' | 'amber' | 'blue' | 'red' | 'slate' => {
  if (status === 'reviewed' || status === 'approved') return 'green';
  if (status === 'submitted') return 'blue';
  if (status === 'in_progress') return 'amber';
  if (status === 'cancelled' || status === 'expired') return 'red';
  return 'slate';
};

export const formatOnlineDate = (value?: string | null): string => {
  if (!value) return 'Not set';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
};

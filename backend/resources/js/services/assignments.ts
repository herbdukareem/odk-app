import { authenticatedRequest } from './auth';

export type AssignmentStatus = 'assigned' | 'in_progress' | 'submitted' | 'reviewed' | 'cancelled' | 'expired' | string;
export type AssignmentAssigneeType = 'user' | 'team';

export type AssignmentExerciseOption = {
  id: number;
  uuid: string;
  name: string;
  code: string;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  target_type?: string;
};

export type AssignmentUserOption = {
  id: number;
  uuid: string;
  name: string;
  email: string;
};

export type AssignmentTeamOption = {
  id: number;
  uuid: string;
  name: string;
};

export type AssignmentFacilityOption = {
  id: number;
  source_id: number;
  source_code?: string | null;
  name: string;
  lga?: { id: number; name: string } | null;
  ward?: { id: number; name: string } | null;
};

export type AssignmentTargetOption = {
  id: number;
  uuid: string;
  type: string;
  external_id?: string | null;
  name: string;
};

export type AssessmentAssignment = {
  id: number;
  uuid: string;
  assessment_exercise_id: number;
  reference_facility_id?: number | null;
  target_type?: string;
  odk_target_id?: number | null;
  assignee_type: AssignmentAssigneeType;
  odk_user_id?: number | null;
  odk_team_id?: number | null;
  status: AssignmentStatus;
  allow_duplicate_submission: boolean;
  due_at?: string | null;
  assigned_at?: string | null;
  submissions_count?: number;
  exercise?: AssignmentExerciseOption | null;
  facility?: AssignmentFacilityOption | null;
  target?: AssignmentTargetOption | null;
  user?: AssignmentUserOption | null;
  team?: AssignmentTeamOption | null;
};

export type AssignmentOptions = {
  exercises: AssignmentExerciseOption[];
  users: AssignmentUserOption[];
  teams: AssignmentTeamOption[];
  facilities: AssignmentFacilityOption[];
  target_types: string[];
  targets: AssignmentTargetOption[];
};

export type AssignmentsPayload = {
  assignments: {
    data: AssessmentAssignment[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  options: AssignmentOptions;
  stats: {
    total: number;
    assigned: number;
    in_progress: number;
    submitted: number;
    reviewed: number;
  };
};

export type AssignmentCreatePayload = {
  assessment_exercise_id?: number;
  assessment_exercise_ids?: number[];
  assignee_type: AssignmentAssigneeType;
  assignee_id: number;
  facility_ids?: number[];
  target_type?: string;
  target_ids?: number[];
  status?: AssignmentStatus;
  allow_duplicate_submission?: boolean;
  due_at?: string | null;
};

export type AssignmentUpdatePayload = {
  assignee_type: AssignmentAssigneeType;
  assignee_id: number;
  status: AssignmentStatus;
  allow_duplicate_submission?: boolean;
  due_at?: string | null;
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

export type AssignmentQuery = {
  q?: string;
  status?: string;
  exercise_id?: string;
  assignee_type?: string;
  page?: number;
  per_page?: number;
};

export const loadAssessmentAssignments = async (query: AssignmentQuery = {}): Promise<AssignmentsPayload> => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') params.set(key, String(value));
  });

  const payload = await readJson<ApiEnvelope<AssignmentsPayload>>(`/api/assignments?${params.toString()}`);
  return payload.data;
};

export const createAssessmentAssignments = async (payload: AssignmentCreatePayload): Promise<AssessmentAssignment[]> => {
  const response = await writeJson<ApiEnvelope<AssessmentAssignment[]>>('/api/assignments', 'POST', payload);
  return response.data;
};

export const updateAssessmentAssignment = async (uuid: string, payload: AssignmentUpdatePayload): Promise<AssessmentAssignment> => {
  const response = await writeJson<ApiEnvelope<AssessmentAssignment>>(`/api/assignments/${uuid}`, 'PATCH', payload);
  return response.data;
};

const assignmentAction = async (uuid: string, action: 'cancel' | 'archive'): Promise<AssessmentAssignment | null> => {
  const response = await writeJson<ApiEnvelope<{ uuid: string; archived: boolean } | AssessmentAssignment>>(
    `/api/assignments/${uuid}/${action}`,
    'POST',
    {},
  );
  return response.data as AssessmentAssignment | null;
};

export const cancelAssessmentAssignment = async (uuid: string): Promise<AssessmentAssignment | null> => (
  assignmentAction(uuid, 'cancel')
);

export const archiveAssessmentAssignment = async (uuid: string): Promise<AssessmentAssignment | null> => (
  assignmentAction(uuid, 'archive')
);

export const deleteAssessmentAssignment = async (uuid: string): Promise<void> => {
  const response = await authenticatedRequest(`/api/assignments/${uuid}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) throw new Error(await response.text());
};

export const statusTone = (status?: AssignmentStatus): 'green' | 'amber' | 'blue' | 'red' | 'slate' => {
  if (status === 'reviewed') return 'green';
  if (status === 'submitted') return 'blue';
  if (status === 'in_progress') return 'amber';
  if (status === 'assigned') return 'slate';
  if (status === 'cancelled' || status === 'expired') return 'red';
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

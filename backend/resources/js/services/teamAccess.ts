import { authenticatedRequest } from './auth';

export type OdkRole = {
  id: number;
  key: string;
  name: string;
  description?: string | null;
  permissions?: string[] | null;
};

export type OdkUser = {
  id: number;
  uuid: string;
  name: string;
  email: string;
  phone?: string | null;
  staff_code?: string | null;
  designation?: string | null;
  status: string;
  assigned_lga?: string | null;
  assigned_ward?: string | null;
  role?: OdkRole | null;
  teams?: Array<Pick<OdkTeam, 'id' | 'name' | 'status'>>;
};

export type OdkTeam = {
  id: number;
  uuid: string;
  name: string;
  description?: string | null;
  status: string;
  assigned_lga?: string | null;
  lead_user_id?: number | null;
  lead?: Pick<OdkUser, 'id' | 'name' | 'email'> | null;
  members_count?: number;
  members?: OdkUser[];
};

export type TeamAccessPayload = {
  roles: OdkRole[];
  users: OdkUser[];
  teams: OdkTeam[];
};

export type UserPayload = {
  name?: string | null;
  email: string;
  password?: string | null;
  phone?: string | null;
  role_key: string;
  staff_code?: string | null;
  designation?: string | null;
  assigned_lga?: string | null;
  assigned_ward?: string | null;
  status?: string;
};

export type TeamPayload = {
  name: string;
  description?: string | null;
  lead_user_id?: number | null;
  assigned_lga?: string | null;
  status?: string;
  member_ids?: number[];
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await authenticatedRequest(url, { method: 'GET' });
  return response.json() as Promise<T>;
};

const writeJson = async <T>(url: string, method: 'POST' | 'PATCH', body: unknown): Promise<T> => {
  const response = await authenticatedRequest(url, {
    method,
    body: JSON.stringify(body),
  });

  return response.json() as Promise<T>;
};

export const loadTeamAccess = async (): Promise<TeamAccessPayload> => {
  const payload = await readJson<ApiEnvelope<TeamAccessPayload>>('/api/team-access');
  return payload.data;
};

export const createOdkUser = async (payload: UserPayload): Promise<OdkUser> => {
  const response = await writeJson<ApiEnvelope<OdkUser>>('/api/team-access/users', 'POST', payload);
  return response.data;
};

export const updateOdkUser = async (id: number, payload: UserPayload): Promise<OdkUser> => {
  const response = await writeJson<ApiEnvelope<OdkUser>>(`/api/team-access/users/${id}`, 'PATCH', payload);
  return response.data;
};

export const resetOdkUserPassword = async (id: number, password: string, passwordConfirmation: string): Promise<void> => {
  await writeJson<ApiEnvelope<null>>(`/api/team-access/users/${id}/password`, 'PATCH', {
    password,
    password_confirmation: passwordConfirmation,
  });
};

export const createOdkTeam = async (payload: TeamPayload): Promise<OdkTeam> => {
  const response = await writeJson<ApiEnvelope<OdkTeam>>('/api/team-access/teams', 'POST', payload);
  return response.data;
};

export const updateOdkTeam = async (id: number, payload: TeamPayload): Promise<OdkTeam> => {
  const response = await writeJson<ApiEnvelope<OdkTeam>>(`/api/team-access/teams/${id}`, 'PATCH', payload);
  return response.data;
};
export const statusTone = (status?: string): 'green' | 'amber' | 'red' | 'slate' => {
  if (status === 'active') return 'green';
  if (status === 'inactive') return 'amber';
  if (status === 'suspended') return 'red';
  return 'slate';
};

export const titleCase = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

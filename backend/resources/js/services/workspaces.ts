import { authenticatedRequest } from './auth';
import { trackApiRequest } from './requestState';
import type { OdkUser, OdkWorkspaceSummary } from './teamAccess';

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
  message?: string;
};

export type Workspace = OdkWorkspaceSummary & {
  owner_user_id?: number | null;
  settings?: Record<string, unknown> | null;
  users_count?: number;
};

export type WorkspaceMember = OdkUser & {
  pivot?: {
    role_key: string;
    status: string;
    joined_at?: string | null;
  };
};

export type WorkspaceInvitation = {
  id: number;
  uuid: string;
  workspace_id: number;
  email: string;
  role_key: string;
  status: string;
  expires_at: string;
  invite_url?: string;
};

export type WorkspaceRegistration = {
  workspace_name: string;
  workspace_type: 'individual' | 'organization';
  workspace_slug?: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

const readJson = async <T>(response: Response): Promise<T> => response.json() as Promise<T>;

export const registerOdkWorkspace = async (input: WorkspaceRegistration): Promise<{
  user: OdkUser;
  workspace: Workspace;
}> => {
  const response = await trackApiRequest(() => fetch('/api/auth/register', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(input),
  }));

  if (!response.ok) {
    const payload = await response.json().catch(() => ({})) as { message?: string; errors?: Record<string, string[]> };
    const firstError = Object.values(payload.errors ?? {}).flat()[0];
    throw new Error(firstError || payload.message || `Registration failed with status ${response.status}.`);
  }

  const payload = await readJson<ApiEnvelope<{ user: OdkUser; workspace: Workspace; token: string }>>(response);
  localStorage.setItem('odk_auth_token', payload.data.token);
  localStorage.removeItem('odk_original_auth');

  return {
    user: payload.data.user,
    workspace: payload.data.workspace,
  };
};

export const loadOdkWorkspaces = async (): Promise<{
  current: Workspace | null;
  items: Workspace[];
}> => {
  const response = await authenticatedRequest('/api/auth/workspaces', { method: 'GET' });
  return (await readJson<ApiEnvelope<{ current: Workspace | null; items: Workspace[] }>>(response)).data;
};

export const loadCurrentOdkWorkspace = async (): Promise<Workspace> => {
  const response = await authenticatedRequest('/api/workspaces/current', { method: 'GET' });
  return (await readJson<ApiEnvelope<Workspace>>(response)).data;
};

export const updateCurrentOdkWorkspace = async (input: {
  name: string;
  slug: string;
}): Promise<Workspace> => {
  const response = await authenticatedRequest('/api/workspaces/current', {
    method: 'PATCH',
    body: JSON.stringify(input),
  });
  return (await readJson<ApiEnvelope<Workspace>>(response)).data;
};

export const switchOdkWorkspace = async (workspaceId: number): Promise<Workspace> => {
  const response = await authenticatedRequest(`/api/auth/workspaces/${workspaceId}/switch`, { method: 'POST' });
  return (await readJson<ApiEnvelope<Workspace>>(response)).data;
};

export const loadWorkspaceMembers = async (): Promise<WorkspaceMember[]> => {
  const response = await authenticatedRequest('/api/workspaces/current/members', { method: 'GET' });
  return (await readJson<ApiEnvelope<WorkspaceMember[]>>(response)).data;
};

export const updateWorkspaceMember = async (id: number, roleKey: 'admin' | 'member'): Promise<WorkspaceMember> => {
  const response = await authenticatedRequest(`/api/workspaces/current/members/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ role_key: roleKey }),
  });
  return (await readJson<ApiEnvelope<WorkspaceMember>>(response)).data;
};

export const removeWorkspaceMember = async (id: number): Promise<void> => {
  await authenticatedRequest(`/api/workspaces/current/members/${id}`, { method: 'DELETE' });
};

export const loadWorkspaceInvitations = async (): Promise<WorkspaceInvitation[]> => {
  const response = await authenticatedRequest('/api/workspaces/current/invitations', { method: 'GET' });
  return (await readJson<ApiEnvelope<WorkspaceInvitation[]>>(response)).data;
};

export const inviteWorkspaceMember = async (email: string, roleKey: 'admin' | 'member'): Promise<{
  invitation: WorkspaceInvitation;
  token: string;
  invite_url: string;
  email_sent: boolean;
}> => {
  const response = await authenticatedRequest('/api/workspaces/current/invitations', {
    method: 'POST',
    body: JSON.stringify({ email, role_key: roleKey }),
  });
  return (await readJson<ApiEnvelope<{ invitation: WorkspaceInvitation; token: string; invite_url: string; email_sent: boolean }>>(response)).data;
};

export const revokeWorkspaceInvitation = async (uuid: string): Promise<void> => {
  await authenticatedRequest(`/api/workspaces/current/invitations/${uuid}`, { method: 'DELETE' });
};

export const acceptOdkInvitation = async (uuid: string, token: string): Promise<Workspace> => {
  const response = await authenticatedRequest(`/api/auth/invitations/${uuid}/accept`, {
    method: 'POST',
    body: JSON.stringify({ token }),
  });
  return (await readJson<ApiEnvelope<Workspace>>(response)).data;
};


import { authenticatedRequest } from './auth';

export type PlatformWorkspace = {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  type: string;
  status: 'active' | 'suspended' | 'archived' | string;
  owner?: { id: number; name: string; email: string } | null;
  users_count?: number;
  forms_count?: number;
  exercises_count?: number;
  assignments_count?: number;
  submissions_count?: number;
};

export type PlatformUser = {
  id: number;
  name: string;
  email: string;
  status: string;
  role?: { name?: string; key?: string } | null;
  workspaces?: Array<{ id: number; name: string; pivot?: { role_key?: string } }>;
};

export type PlatformAuditLog = {
  id: number;
  action: string;
  metadata?: Record<string, unknown> | null;
  created_at: string;
  actor?: { id: number; name: string; email: string } | null;
  workspace?: { id: number; name: string; slug: string } | null;
};

export type PlatformPlan = {
  id: number;
  code: string;
  provider_plan_code?: string | null;
  name: string;
  description?: string | null;
  price_amount: string | number;
  currency: string;
  billing_interval: 'monthly' | 'yearly' | string;
  limits: Record<string, number | null>;
  features: string[];
  active: boolean;
  sort_order: number;
  subscriptions_count?: number;
  invoices_count?: number;
};

type Envelope<T> = { success: boolean; data: T };

const get = async <T>(url: string): Promise<T> => {
  const response = await authenticatedRequest(url, { method: 'GET' });
  return (await response.json() as Envelope<T>).data;
};

export const loadPlatformWorkspaces = () => get<PlatformWorkspace[]>('/api/platform/workspaces');
export const loadPlatformUsers = () => get<PlatformUser[]>('/api/platform/users');
export const loadPlatformAuditLogs = () => get<PlatformAuditLog[]>('/api/platform/audit-logs');
export const loadPlatformPlans = () => get<PlatformPlan[]>('/api/platform/plans');

export type PlatformPlanUpdate = Pick<PlatformPlan, 'name' | 'description' | 'price_amount' | 'currency' | 'billing_interval' | 'provider_plan_code' | 'limits' | 'features' | 'active' | 'sort_order'>;

export const updatePlatformPlan = async (id: number, payload: PlatformPlanUpdate): Promise<PlatformPlan> => {
  const response = await authenticatedRequest(`/api/platform/plans/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return (await response.json() as Envelope<PlatformPlan>).data;
};

export const createPlatformPlan = async (payload: PlatformPlanUpdate & { code: string }): Promise<PlatformPlan> => {
  const response = await authenticatedRequest('/api/platform/plans', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return (await response.json() as Envelope<PlatformPlan>).data;
};

export const archivePlatformPlan = async (id: number): Promise<PlatformPlan> => {
  const response = await authenticatedRequest(`/api/platform/plans/${id}/archive`, { method: 'POST' });
  return (await response.json() as Envelope<PlatformPlan>).data;
};

export const restorePlatformPlan = async (id: number): Promise<PlatformPlan> => {
  const response = await authenticatedRequest(`/api/platform/plans/${id}/restore`, { method: 'POST' });
  return (await response.json() as Envelope<PlatformPlan>).data;
};

export type PlatformPlanHistory = {
  id: number;
  action: string;
  snapshot: PlatformPlan;
  created_at: string;
  changed_by?: { id: number; name: string; email: string } | null;
};

export const loadPlatformPlanHistory = (id: number) => get<PlatformPlanHistory[]>(`/api/platform/plans/${id}/history`);

export const updatePlatformWorkspaceStatus = async (id: number, status: string): Promise<PlatformWorkspace> => {
  const response = await authenticatedRequest(`/api/platform/workspaces/${id}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
  return (await response.json() as Envelope<PlatformWorkspace>).data;
};

export const updatePlatformUserStatus = async (id: number, status: string): Promise<PlatformUser> => {
  const response = await authenticatedRequest(`/api/platform/users/${id}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
  return (await response.json() as Envelope<PlatformUser>).data;
};

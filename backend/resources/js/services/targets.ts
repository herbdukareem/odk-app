import { authenticatedRequest } from './auth';

export type TargetType = { key: string; label: string; requires_record: boolean };
export type OdkTarget = {
  id: number;
  uuid: string;
  type: string;
  external_id?: string | null;
  name: string;
  description?: string | null;
  active: boolean;
  metadata?: Record<string, unknown> | null;
};

type Envelope<T> = { success: boolean; data: T };

const request = async <T>(url: string, init: RequestInit = {}): Promise<T> => {
  const response = await authenticatedRequest(url, init);
  return (await response.json() as Envelope<T>).data;
};

export const loadTargetTypes = () => request<TargetType[]>('/api/target-types');
export const loadTargets = (type = '') => request<OdkTarget[]>(`/api/targets${type ? `?type=${encodeURIComponent(type)}` : ''}`);
export const createTarget = (payload: Pick<OdkTarget, 'type' | 'name' | 'external_id' | 'description'>) => request<OdkTarget>('/api/targets', { method: 'POST', body: JSON.stringify(payload) });
export const updateTarget = (target: OdkTarget, payload: Pick<OdkTarget, 'name' | 'external_id' | 'description'>) => request<OdkTarget>(`/api/targets/${target.uuid}`, { method: 'PATCH', body: JSON.stringify(payload) });
export const archiveTarget = (target: OdkTarget) => request<null>(`/api/targets/${target.uuid}`, { method: 'DELETE' });

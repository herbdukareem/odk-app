import { authenticatedRequest } from './auth';

export type SubscriptionUsage = {
  metric: string;
  used: number;
  limit: number | null;
  unlimited: boolean;
};

export type WorkspaceSubscriptionSnapshot = {
  subscription: {
    id: number;
    status: string;
    current_period_start?: string | null;
    current_period_end?: string | null;
    cancel_at_period_end?: boolean;
  };
  plan: {
    code: string;
    name: string;
    description?: string | null;
    price_amount: string | number;
    currency: string;
    billing_interval: string;
    features?: string[] | null;
  };
  usage: SubscriptionUsage[];
};

export const loadCurrentWorkspaceSubscription = async (): Promise<WorkspaceSubscriptionSnapshot> => {
  const response = await authenticatedRequest('/api/subscription/current', { method: 'GET' });
  const payload = await response.json() as { success: boolean; data: WorkspaceSubscriptionSnapshot };
  return payload.data;
};

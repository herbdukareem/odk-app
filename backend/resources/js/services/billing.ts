import { authenticatedRequest } from './auth';

export type BillingPlan = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  price_amount: string | number;
  currency: string;
  billing_interval: string;
  limits?: Record<string, number> | null;
  features?: string[] | null;
};

export type BillingInvoice = {
  id: number;
  uuid: string;
  reference: string;
  provider_reference?: string | null;
  amount: string | number;
  currency: string;
  status: string;
  paid_at?: string | null;
  created_at: string;
  plan?: Pick<BillingPlan, 'code' | 'name'> | null;
};

type Envelope<T> = { success: boolean; data: T };

const read = async <T>(response: Response): Promise<T> => (await response.json() as Envelope<T>).data;

export const loadBillingPlans = async (): Promise<BillingPlan[]> => {
  const response = await authenticatedRequest('/api/subscription/plans', { method: 'GET' });
  return read<BillingPlan[]>(response);
};

export const loadBillingInvoices = async (): Promise<BillingInvoice[]> => {
  const response = await authenticatedRequest('/api/subscription/invoices', { method: 'GET' });
  return read<BillingInvoice[]>(response);
};

export const startBillingCheckout = async (planCode: string): Promise<{
  invoice: BillingInvoice;
  checkout: { authorization_url?: string | null; reference?: string | null };
}> => {
  const response = await authenticatedRequest('/api/subscription/checkout', {
    method: 'POST',
    body: JSON.stringify({ plan_code: planCode }),
  });
  return read(response);
};

export const verifyBillingPayment = async (reference: string) => {
  const response = await authenticatedRequest('/api/subscription/verify', {
    method: 'POST',
    body: JSON.stringify({ reference }),
  });
  return read(response);
};

export const cancelBillingSubscription = async () => {
  const response = await authenticatedRequest('/api/subscription/cancel', { method: 'POST' });
  return read(response);
};

export const resumeBillingSubscription = async () => {
  const response = await authenticatedRequest('/api/subscription/resume', { method: 'POST' });
  return read(response);
};

import type { OdkUser } from './teamAccess';

export type { OdkUser } from './teamAccess';

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
  message?: string;
};

type AuthResponse = {
  user: OdkUser;
  token: string;
};

type ImpersonationResponse = AuthResponse & {
  original_user: OdkUser;
  expires_at: string;
};

const TOKEN_KEY = 'odk_auth_token';
const ORIGINAL_TOKEN_KEY = 'odk_original_auth';

const jsonHeaders = (headers?: HeadersInit): Headers => {
  const merged = new Headers(headers);
  merged.set('Accept', 'application/json');
  merged.set('Content-Type', 'application/json');
  return merged;
};

const readError = async (response: Response): Promise<string> => {
  try {
    const payload = await response.json() as { message?: string; errors?: Record<string, string[]> };
    const firstValidationError = Object.values(payload.errors ?? {}).flat()[0];
    return firstValidationError || payload.message || `Request failed with status ${response.status}.`;
  } catch {
    return `Request failed with status ${response.status}.`;
  }
};

export const getOdkToken = (): string | null => localStorage.getItem(TOKEN_KEY);

export const authenticatedRequest = async (url: string, init: RequestInit = {}): Promise<Response> => {
  const headers = jsonHeaders(init.headers);
  if (init.body instanceof FormData) {
    headers.delete('Content-Type');
  }
  const token = getOdkToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const response = await fetch(url, { ...init, headers });
  if (!response.ok) {
    throw new Error(await readError(response));
  }

  return response;
};

export const loginOdkUser = async (email: string, password: string): Promise<OdkUser> => {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: jsonHeaders(),
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) throw new Error(await readError(response));

  const payload = await response.json() as ApiEnvelope<AuthResponse>;
  localStorage.setItem(TOKEN_KEY, payload.data.token);
  localStorage.removeItem(ORIGINAL_TOKEN_KEY);
  return payload.data.user;
};

export const getCurrentOdkUser = async (): Promise<OdkUser> => {
  const response = await authenticatedRequest('/api/auth/me', { method: 'GET' });
  const payload = await response.json() as ApiEnvelope<OdkUser>;
  return payload.data;
};

export const logoutOdkUser = async (): Promise<void> => {
  if (getOdkToken()) {
    await authenticatedRequest('/api/auth/logout', { method: 'POST' });
  }
  clearOdkSession();
};

export const clearOdkSession = (): void => {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(ORIGINAL_TOKEN_KEY);
};

export const impersonateOdkUser = async (id: number): Promise<ImpersonationResponse> => {
  const currentToken = getOdkToken();
  if (!currentToken) throw new Error('Your session has expired. Please sign in again.');

  const response = await authenticatedRequest(`/api/team-access/users/${id}/impersonate`, { method: 'POST' });
  const payload = await response.json() as ApiEnvelope<ImpersonationResponse>;
  localStorage.setItem(ORIGINAL_TOKEN_KEY, currentToken);
  localStorage.setItem(TOKEN_KEY, payload.data.token);
  return payload.data;
};

export const stopOdkImpersonation = async (): Promise<OdkUser> => {
  await authenticatedRequest('/api/auth/stop-impersonation', { method: 'POST' });
  const originalToken = localStorage.getItem(ORIGINAL_TOKEN_KEY);
  if (!originalToken) {
    clearOdkSession();
    throw new Error('The original admin session was not found. Please sign in again.');
  }

  localStorage.setItem(TOKEN_KEY, originalToken);
  localStorage.removeItem(ORIGINAL_TOKEN_KEY);
  return getCurrentOdkUser();
};

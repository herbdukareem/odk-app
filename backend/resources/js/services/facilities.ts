export type ReferenceLga = {
  id: number;
  source_id?: number | null;
  code?: string | null;
  name: string;
};

export type ReferenceWard = {
  id: number;
  source_id?: number | null;
  source_lga_id?: number | null;
  reference_lga_id?: number | null;
  name: string;
};

export type ReferenceFacility = {
  id: number;
  source_id: number;
  source_code?: string | null;
  name: string;
  ownership?: string | null;
  type?: string | null;
  level_of_care?: string | null;
  address?: string | null;
  phone?: string | null;
  email?: string | null;
  active: boolean;
  accreditation_status?: string | null;
  last_synced_at?: string | null;
  lga?: ReferenceLga | null;
  ward?: ReferenceWard | null;
};

export type IntegrationSyncLog = {
  id?: number;
  status?: string;
  counts?: Record<string, number> | null;
  message?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  created_at?: string | null;
};

export type FacilitiesPayload = {
  facilities: {
    data: ReferenceFacility[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  stats: {
    total: number;
    active: number;
    lgas: number;
    wards: number;
  };
  lgas: ReferenceLga[];
  wards: ReferenceWard[];
  last_sync?: IntegrationSyncLog | null;
};

type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

const readJson = async <T>(url: string): Promise<T> => {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
  return response.json() as Promise<T>;
};

const writeJson = async <T>(url: string): Promise<T> => {
  const response = await fetch(url, {
    method: 'POST',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) throw new Error(await response.text());
  return response.json() as Promise<T>;
};

export type FacilityQuery = {
  q?: string;
  active?: '1' | '0' | 'all';
  lga_id?: string;
  ward_id?: string;
  page?: number;
  per_page?: number;
};

export const loadReferenceFacilities = async (query: FacilityQuery = {}): Promise<FacilitiesPayload> => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') params.set(key, String(value));
  });

  const payload = await readJson<ApiEnvelope<FacilitiesPayload>>(`/api/facilities/reference?${params.toString()}`);
  return payload.data;
};

export const syncNicareReferenceData = async (): Promise<Record<string, number>> => {
  const payload = await writeJson<ApiEnvelope<{ counts: Record<string, number> }>>('/api/integrations/nicare/sync');
  return payload.data.counts;
};

export const facilityStatusTone = (facility: ReferenceFacility): 'green' | 'amber' | 'red' | 'slate' => {
  if (!facility.active) return 'slate';
  if (facility.accreditation_status === 'active' || !facility.accreditation_status) return 'green';
  if (facility.accreditation_status === 'suspended') return 'amber';
  if (facility.accreditation_status === 'revoked') return 'red';
  return 'slate';
};

export const formatDateTime = (value?: string | null): string => {
  if (!value) return 'Never';
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

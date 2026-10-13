import NetInfo from '@react-native-community/netinfo';
import Constants from 'expo-constants';
import * as DocumentPicker from 'expo-document-picker';
import { File, Paths } from 'expo-file-system';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import Storage from 'expo-sqlite/kv-store';
import { StatusBar } from 'expo-status-bar';
import { Feather } from '@expo/vector-icons';
import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  GestureResponderEvent,
  Image,
  PanResponder,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import DataKitLogo from './components/DataKitLogo';

type Screen = 'Home' | 'Forms' | 'Facilities' | 'Assessment' | 'Review' | 'Sync' | 'Assessments';

type FieldOption = {
  label: string;
  value: string;
  score?: number | null;
  needs_review?: boolean;
};

type ScoringRule = {
  field?: string;
  type?: string;
  options?: Array<{
    value: string;
    score?: number | null;
    needs_review?: boolean;
  }>;
};

type FormField = {
  key: string;
  label: string;
  type: string;
  section: string;
  required?: boolean;
  readonly?: boolean;
  rules?: string[];
  visible_if?: LogicCondition;
  required_if?: Record<string, unknown>;
  options?: FieldOption[];
  group?: string;
  help_text?: string;
  placeholder?: string;
  min_items?: number;
  max_items?: number;
  count_label?: string;
  count_required_value?: string;
  count_rules?: string[];
  count_minimum?: number;
  scorable?: boolean;
};

type FormSection = {
  key: string;
  label: string;
  description?: string | null;
  order?: number;
};

type FormSchema = {
  id?: number;
  slug: string;
  title: string;
  description?: string;
  version: number;
  version_id?: number;
  metadata_schema?: Record<string, unknown> | null;
  requires_scoring?: boolean;
  scoring_max_score?: number | null;
  sections: FormSection[];
  fields: FormField[];
  validation_rules?: unknown[];
  visibility_rules?: unknown[];
  scoring_rules?: ScoringRule[];
  required_evidence_rules?: Array<{ field: string; required_on_submit?: boolean; label?: string }>;
  signature_rules?: Array<{ field: string; required_on_submit?: boolean }>;
  downloaded_at?: string;
};

type FormCacheMeta = {
  downloaded_at: string;
  api_base_url: string;
  forms_count: number;
  facilities_count: number;
  assignments_count?: number;
  exercises_count?: number;
  form_versions: Array<{
    slug: string;
    title: string;
    version: number;
    version_id?: number;
    fields_count: number;
    sections_count: number;
  }>;
};

type Facility = {
  id: number | string;
  local_id?: number;
  code?: string | null;
  name: string;
  type?: string | null;
  level_of_care?: string | null;
  lga?: string | null;
  ward?: string | null;
  lga_id?: number | string | null;
  ward_id?: number | string | null;
  active?: boolean;
  accreditation_status?: string | null;
  assignment_id?: number;
  assignment_uuid?: string;
  assignment_status?: string;
  assignment_due_at?: string | null;
  allow_duplicate_submission?: boolean;
  exercise_id?: number;
  exercise_uuid?: string;
  exercise_name?: string | null;
  exercise_code?: string | null;
  exercise_status?: string | null;
  exercise_starts_at?: string | null;
  exercise_ends_at?: string | null;
  form_slug?: string | null;
  form_version_id?: number | null;
  target_type?: string;
};

type MobileTarget = {
  id: number | string;
  local_id?: number;
  type: string;
  external_id?: string | null;
  name: string;
  description?: string | null;
  metadata?: Record<string, unknown> | null;
};

type MobileExercise = {
  id: number;
  uuid: string;
  name: string;
  code?: string | null;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  form_slug?: string | null;
  form_version_id?: number | null;
};

type MobileAssignment = {
  id: number;
  uuid: string;
  status: string;
  due_at?: string | null;
  allow_duplicate_submission?: boolean;
  target_type?: string;
  target?: MobileTarget | null;
  exercise?: MobileExercise | null;
  facility?: Facility | null;
  submission?: MobileAssignmentSubmission | null;
};

type MobileAssignmentSubmission = {
  uuid: string;
  client_submission_id?: string | null;
  assessment_id?: string | null;
  status: string;
  answers?: Record<string, unknown> | null;
  validation_result?: ValidationResult | null;
  review_note?: string | null;
  reviewed_at?: string | null;
  submitted_at?: string | null;
  attachments?: Array<{
    id: number;
    field_key?: string | null;
    type?: string | null;
    original_name?: string | null;
    mime_type?: string | null;
    download_url?: string | null;
  }>;
};

type EvidenceAttachment = {
  id: string;
  fieldKey: string;
  type: 'photo' | 'document' | 'signature';
  uri: string;
  name: string;
  mimeType: string;
  capturedAt: string;
};

type GpsValue = {
  latitude: number;
  longitude: number;
  accuracy_meters?: number | null;
  captured_at?: string;
};

type DraftAssessment = {
  id: string;
  clientSubmissionId?: string;
  reviewNote?: string | null;
  assessmentId: string;
  formSlug: string;
  formTitle: string;
  formVersion: number;
  formVersionId?: number;
  assessmentExerciseId?: number;
  assessmentExerciseUuid?: string;
  assessmentAssignmentId?: number;
  assignmentUuid?: string;
  exerciseName?: string | null;
  exerciseStatus?: string | null;
  exerciseStartsAt?: string | null;
  exerciseEndsAt?: string | null;
  formSchema?: FormSchema;
  facility: Facility;
  assessor: {
    id: string;
    name: string;
    email: string;
  };
  answers: Record<string, unknown>;
  attachments: EvidenceAttachment[];
  currentSectionIndex: number;
  startedAt: string;
  updatedAt: string;
  submittedAt?: string;
};

type QueueItem = {
  id: string;
  draft: DraftAssessment;
  status: 'pending' | 'syncing' | 'failed';
  attempts: number;
  lastError?: string;
  lastAttemptAt?: string;
  remoteUuid?: string;
  attachmentStatus?: Record<string, {
    status: 'pending' | 'uploaded' | 'failed';
    error?: string;
    uploadedAt?: string;
  }>;
  queuedAt: string;
};

type HistoryItem = {
  id: string;
  assessmentId: string;
  facilityName: string;
  syncedAt: string;
  remoteUuid?: string;
  attachmentsUploaded?: number;
};

type LogicCondition = {
  field?: string;
  operator?: 'equals' | 'not_equals' | 'in' | 'not_in';
  value?: unknown;
  values?: unknown[];
};

type ScoreResult = {
  total: number;
  max: number | null;
  percent: number | null;
  fields: Array<{
    field: string;
    label: string;
    answer?: unknown;
    score: number;
    max_score: number | null;
  }>;
};

type ValidationResult = {
  errors: ValidationError[];
  completedSections: number;
  score: ScoreResult;
};

type SectionRenderItem =
  | { type: 'group'; key: string; label: string }
  | { type: 'field'; key: string; field: FormField };

type PublishedFormResponse = {
  success: boolean;
  data: Array<{
    id?: number;
    slug: string;
    title: string;
    description?: string;
    requires_scoring?: boolean;
    scoring_max_score?: number | string | null;
    current_version?: {
      id?: number;
      version: number;
      metadata_schema?: Record<string, unknown> | null;
      requires_scoring?: boolean;
      scoring_max_score?: number | string | null;
      sections?: FormSection[];
      fields?: FormField[];
      validation_rules?: unknown[];
      visibility_rules?: unknown[];
      scoring_rules?: FormSchema['scoring_rules'];
      required_evidence_rules?: FormSchema['required_evidence_rules'];
      signature_rules?: FormSchema['signature_rules'];
    };
  }>;
};

type ReferenceResponse = {
  success: boolean;
  data: {
    facilities: Facility[];
  };
};

type MobileAssignmentsResponse = {
  success: boolean;
  data: {
    assignments: MobileAssignment[];
    exercises: MobileExercise[];
    forms: PublishedFormResponse['data'];
    facilities: Facility[];
  };
  synced_at?: string;
};

type MobileWorkspace = {
  id: number;
  uuid: string;
  slug: string;
  name: string;
  type: 'individual' | 'organization';
  status: string;
  pivot?: { role_key: string; status: string };
};

type MobileUser = {
  id: number;
  uuid: string;
  name: string;
  email: string;
  staff_code?: string | null;
  workspaces?: MobileWorkspace[];
};

type MobileAuthResponse = {
  success: boolean;
  data: { user: MobileUser; token: string };
};

const API_BASE_URL = String(
  Constants.expoConfig?.extra?.apiBaseUrl ?? 'http://odk-web.test/api'
).replace(/\/$/, '');
const REQUEST_TIMEOUT_MS = 15000;

const STORAGE_KEYS = {
  authToken: 'datakit.mobile.auth-token.v1',
  authUser: 'datakit.mobile.auth-user.v1',
  forms: 'nicare.forms.v1',
  formCacheMeta: 'nicare.forms.cache-meta.v1',
  assignments: 'nicare.assignments.v1',
  facilities: 'nicare.facilities.v1',
  drafts: 'nicare.drafts.v1',
  activeDraftId: 'nicare.active-draft-id.v1',
  queue: 'nicare.sync-queue.v1',
  history: 'nicare.history.v1',
};

const assessor = {
  id: '1001',
  name: 'Amina Yusuf',
  email: 'amina.yusuf@health.gov.ng',
};

const TAB_ITEMS: Array<{ screen: Screen; label: string; icon: keyof typeof Feather.glyphMap }> = [
  { screen: 'Home', label: 'Home', icon: 'home' },
  { screen: 'Forms', label: 'Forms', icon: 'file-text' },
  { screen: 'Facilities', label: 'Sites', icon: 'map-pin' },
  { screen: 'Assessments', label: 'Assess', icon: 'clipboard' },
  { screen: 'Sync', label: 'Sync', icon: 'refresh-cw' },
];

const colors = {
  green950: '#042f25',
  green900: '#064231',
  green800: '#075f3d',
  green700: '#087a48',
  green600: '#079455',
  green100: '#dcf7e7',
  green50: '#eefbf3',
  amber100: '#fff0c8',
  amber700: '#a75d00',
  red100: '#fee4e2',
  red700: '#b42318',
  slate900: '#101828',
  slate700: '#344054',
  slate600: '#475467',
  slate500: '#667085',
  slate100: '#eef2f6',
  slate50: '#f6f8fa',
  border: '#d8e0e7',
  white: '#ffffff',
};

const shadow = {
  shadowColor: '#0f172a',
  shadowOpacity: 0.08,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 8 },
  elevation: 2,
};

const fallbackFacilities: Facility[] = [
  {
    id: 1,
    code: 'NG-BOS-001',
    name: 'Bosso PHC',
    type: 'Primary Health Care Centre',
    lga: 'Bosso',
    ward: 'Maikunkele',
    active: true,
    accreditation_status: 'Active',
  },
  {
    id: 2,
    code: 'NG-CHA-001',
    name: 'Chanchaga PHC',
    type: 'Primary Health Care Centre',
    lga: 'Chanchaga',
    ward: 'Tungan Maje',
    active: true,
    accreditation_status: 'Active',
  },
  {
    id: 3,
    code: 'NG-MAI-001',
    name: 'Maikunkele PHC',
    type: 'Primary Health Care Centre',
    lga: 'Bosso',
    ward: 'Maikunkele',
    active: true,
    accreditation_status: 'Active',
  },
];

const fallbackForm: FormSchema = {
  slug: 'nicare-qa-checklist',
  title: 'DataKit QA Checklist',
  description: 'Offline starter schema. Download updates for the full published checklist.',
  version: 1,
  metadata_schema: {
    facility_source: 'fallback',
    requires_facility_snapshot: true,
    requires_assessor_snapshot: true,
    requires_device_snapshot: true,
    requires_scoring: false,
  },
  requires_scoring: false,
  scoring_max_score: null,
  sections: [
    { key: 'assessment_metadata', label: 'Assessment details', order: 0 },
    { key: 'personnel', label: 'A. Personnel', order: 1 },
    { key: 'drugs_stocks', label: 'B. Drugs and stocks', order: 2 },
    { key: 'evidence', label: 'Evidence', order: 9 },
    { key: 'signatures', label: 'Signatures', order: 10 },
  ],
  fields: [
    { key: 'assessment_id', label: 'Assessment ID', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'assessment_date', label: 'Assessment date', type: 'date', section: 'assessment_metadata', required: true },
    { key: 'assessor_id', label: 'Assessor ID', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'facility_id', label: 'Facility ID', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'facility_name', label: 'Facility name', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'lga_id', label: 'LGA', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'ward_id', label: 'Ward', type: 'readonly', section: 'assessment_metadata', required: true },
    { key: 'gps_coordinates', label: 'GPS coordinates', type: 'gps', section: 'assessment_metadata', required: true },
    {
      key: 'doctors_available',
      label: 'Does the facility have doctors?',
      type: 'yes_no',
      section: 'personnel',
      required: true,
      options: yesNoOptions(),
    },
    {
      key: 'total_doctors',
      label: 'Total number of doctors',
      type: 'number',
      section: 'personnel',
      required_if: { doctors_available: 'yes' },
      visible_if: { field: 'doctors_available', operator: 'equals', value: 'yes' },
    },
    {
      key: 'drug_supply_source',
      label: 'Main source of supply',
      type: 'select',
      section: 'drugs_stocks',
      required: true,
      options: [
        { label: 'DMA', value: 'dma', score: 2 },
        { label: 'NGO supply', value: 'ngo_supply', score: 1 },
        { label: 'Local purchase', value: 'local_purchase', score: 1 },
      ],
    },
    { key: 'facility_photos', label: 'Facility photos', type: 'photo_collection', section: 'evidence' },
    { key: 'supporting_documents', label: 'Supporting documents', type: 'file_collection', section: 'evidence' },
    { key: 'facility_representative_name', label: 'Facility representative name', type: 'text', section: 'signatures', required: true },
    { key: 'accuracy_attestation', label: 'I confirm that the information collected is accurate', type: 'checkbox', section: 'signatures', required: true },
  ],
  required_evidence_rules: [{ field: 'gps_coordinates', required_on_submit: true, label: 'GPS location' }],
  signature_rules: [],
};

export default function App() {
  const [ready, setReady] = useState(false);
  const [signedIn, setSignedIn] = useState(false);
  const [mobileUser, setMobileUser] = useState<MobileUser | null>(null);
  const [mobileEmail, setMobileEmail] = useState('');
  const [mobilePassword, setMobilePassword] = useState('');
  const [authError, setAuthError] = useState('');
  const [authSubmitting, setAuthSubmitting] = useState(false);
  const [mobileWorkspaces, setMobileWorkspaces] = useState<MobileWorkspace[]>([]);
  const [selectedWorkspaceId, setSelectedWorkspaceId] = useState<number | null>(null);
  const [screen, setScreen] = useState<Screen>('Home');
  const [forms, setForms] = useState<FormSchema[]>([]);
  const [formCacheMeta, setFormCacheMeta] = useState<FormCacheMeta | null>(null);
  const [assignments, setAssignments] = useState<MobileAssignment[]>([]);
  const [facilities, setFacilities] = useState<Facility[]>([]);
  const [drafts, setDrafts] = useState<DraftAssessment[]>([]);
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [history, setHistory] = useState<HistoryItem[]>([]);
  const [activeDraftId, setActiveDraftId] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [selectedExerciseId, setSelectedExerciseId] = useState<string>('all');
  const [selectedLga, setSelectedLga] = useState('All LGAs');
  const [selectedWard, setSelectedWard] = useState('All wards');
  const [online, setOnline] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [lastMessage, setLastMessage] = useState('Ready for offline use.');

  useEffect(() => {
    let mounted = true;

    async function bootstrap() {
      const [storedToken, storedUser, storedForms, storedFormCacheMeta, storedAssignments, storedFacilities, storedDrafts, storedActiveDraftId, storedQueue, storedHistory] = await Promise.all([
        loadJson<string | null>(STORAGE_KEYS.authToken, null),
        loadJson<MobileUser | null>(STORAGE_KEYS.authUser, null),
        loadJson<FormSchema[]>(STORAGE_KEYS.forms, []),
        loadJson<FormCacheMeta | null>(STORAGE_KEYS.formCacheMeta, null),
        loadJson<MobileAssignment[]>(STORAGE_KEYS.assignments, []),
        loadJson<Facility[]>(STORAGE_KEYS.facilities, []),
        loadJson<DraftAssessment[]>(STORAGE_KEYS.drafts, []),
        loadJson<string | null>(STORAGE_KEYS.activeDraftId, null),
        loadJson<QueueItem[]>(STORAGE_KEYS.queue, []),
        loadJson<HistoryItem[]>(STORAGE_KEYS.history, []),
      ]);

      if (!mounted) {
        return;
      }

      const cachedForms = storedForms.length > 0 ? storedForms : [fallbackForm];
      const restoredDrafts = hydrateDrafts(storedDrafts, cachedForms);
      const restoredQueue = hydrateQueue(storedQueue);
      const restoredActiveDraftId = restoredDrafts.some((draft) => draft.id === storedActiveDraftId) ? storedActiveDraftId : null;

      setForms(storedForms);
      setFormCacheMeta(storedFormCacheMeta);
      setAssignments(storedAssignments);
      setFacilities(storedFacilities);
      setDrafts(restoredDrafts);
      setActiveDraftId(restoredActiveDraftId);
      setQueue(restoredQueue);
      setHistory(storedHistory);
      setMobileUser(storedUser);
      setMobileWorkspaces(storedUser?.workspaces ?? []);
      setSelectedWorkspaceId(storedUser?.workspaces?.[0]?.id ?? null);
      setSignedIn(Boolean(storedToken && storedUser));
      setReady(true);

      if (storedDrafts.length > 0) {
        await saveJson(STORAGE_KEYS.drafts, restoredDrafts);
      }
      if (storedQueue.length > 0) {
        await saveJson(STORAGE_KEYS.queue, restoredQueue);
      }
    }

    const unsubscribe = NetInfo.addEventListener((state) => {
      setOnline(Boolean(state.isConnected && state.isInternetReachable !== false));
    });

    void bootstrap();

    return () => {
      mounted = false;
      unsubscribe();
    };
  }, []);

  const form = forms[0] ?? fallbackForm;
  const activeDraft = drafts.find((draft) => draft.id === activeDraftId) ?? null;
  const activeForm = activeDraft?.formSchema ?? form;
  const latestFormForActiveDraft = activeDraft ? formForDraft(activeDraft, forms.length > 0 ? forms : [fallbackForm]) : null;
  const assignedExercises = useMemo(() => {
    return uniqueExercisesFromFacilities(facilities);
  }, [facilities]);
  const filteredFacilities = useMemo(() => {
    return facilities.filter((facility) => {
      const term = search.trim().toLowerCase();
      const matchesSearch =
        term.length === 0 ||
        facility.name.toLowerCase().includes(term) ||
        String(facility.code ?? facility.id).toLowerCase().includes(term);
      const matchesExercise = selectedExerciseId === 'all' || String(facility.exercise_id) === selectedExerciseId;
      const matchesLga = selectedLga === 'All LGAs' || facility.lga === selectedLga;
      const matchesWard = selectedWard === 'All wards' || facility.ward === selectedWard;

      return matchesSearch && matchesExercise && matchesLga && matchesWard;
    });
  }, [facilities, search, selectedExerciseId, selectedLga, selectedWard]);

  const lgas = useMemo(() => ['All LGAs', ...unique(facilities.map((facility) => facility.lga).filter(Boolean) as string[])], [facilities]);
  const wards = useMemo(() => ['All wards', ...unique(facilities.map((facility) => facility.ward).filter(Boolean) as string[])], [facilities]);
  const validation = activeDraft ? validateDraft(activeForm, activeDraft) : emptyValidationResult();
  const activeAssessor = mobileUser
    ? { id: mobileUser.staff_code || String(mobileUser.id), name: mobileUser.name, email: mobileUser.email }
    : assessor;

  async function signInMobile() {
    setAuthError('');
    setAuthSubmitting(true);
    try {
      const response = await apiPost<MobileAuthResponse>('/mobile/auth/login', {
        email: mobileEmail.trim(),
        password: mobilePassword,
      });
      await saveJson(STORAGE_KEYS.authToken, response.data.token);
      await saveJson(STORAGE_KEYS.authUser, response.data.user);
      setMobileUser(response.data.user);
      setMobileWorkspaces(response.data.user.workspaces ?? []);
      setSelectedWorkspaceId(response.data.user.workspaces?.[0]?.id ?? null);
      setSignedIn(true);
      setMobilePassword('');
      setLastMessage('Signed in. Download your assigned work when you are online.');
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Unable to sign in.');
    } finally {
      setAuthSubmitting(false);
    }
  }

  async function switchMobileWorkspace(workspaceId: number) {
    if (workspaceId === selectedWorkspaceId) return;
    try {
      await apiPost(`/auth/workspaces/${workspaceId}/switch`, {});
      setSelectedWorkspaceId(workspaceId);
      setAssignments([]);
      setFacilities([]);
      await saveJson(STORAGE_KEYS.assignments, []);
      await saveJson(STORAGE_KEYS.facilities, []);
      await refreshReferenceData();
    } catch (error) {
      Alert.alert('Workspace switch failed', error instanceof Error ? error.message : 'Unable to switch workspace.');
    }
  }

  async function refreshReferenceData() {
    setRefreshing(true);
    try {
      const assignmentResponse = await apiGet<MobileAssignmentsResponse>(
        '/mobile/secure/assignments'
      );

      const downloadedAssignments = assignmentResponse.data.assignments ?? [];
      const downloadedForms = (assignmentResponse.data.forms ?? [])
        .map(mapPublishedForm)
        .filter((item): item is FormSchema => item !== null);
      const downloadedFacilities = assignmentsToFacilities(downloadedAssignments, assignmentResponse.data.facilities ?? []);
      const returnedDrafts = downloadedAssignments
        .map((assignment) => draftFromReturnedAssignment(assignment, downloadedForms))
        .filter((draft): draft is DraftAssessment => draft !== null);
      const newReturnedDrafts = returnedDrafts.filter((draft) => !drafts.some((existing) => existing.id === draft.id));

      setAssignments(downloadedAssignments);
      await saveJson(STORAGE_KEYS.assignments, downloadedAssignments);

      setForms(downloadedForms);
      await saveJson(STORAGE_KEYS.forms, downloadedForms);

      setFacilities(downloadedFacilities);
      await saveJson(STORAGE_KEYS.facilities, downloadedFacilities);

      if (newReturnedDrafts.length > 0) {
        const nextDrafts = [...newReturnedDrafts, ...drafts];
        setDrafts(nextDrafts);
        await saveJson(STORAGE_KEYS.drafts, nextDrafts);
        Alert.alert(
          'Assessment returned',
          String(newReturnedDrafts.length) +
            (newReturnedDrafts.length === 1
              ? ' assessment is back on this device for correction.'
              : ' assessments are back on this device for correction.')
        );
      }

      if (selectedExerciseId !== 'all' && !downloadedFacilities.some((facility) => String(facility.exercise_id) === selectedExerciseId)) {
        setSelectedExerciseId('all');
      }

      const nextMeta = buildFormCacheMeta(
        downloadedForms,
        downloadedFacilities.length,
        downloadedAssignments.length,
        assignmentResponse.data.exercises?.length ?? uniqueExercisesFromFacilities(downloadedFacilities).length
      );
      setFormCacheMeta(nextMeta);
      await saveJson(STORAGE_KEYS.formCacheMeta, nextMeta);

      setLastMessage(
        `Downloaded ${downloadedAssignments.length} assignment(s), ${downloadedForms.length} form(s), and ${downloadedFacilities.length} assigned facilities.`
      );
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to download updates.';
      setLastMessage(message);
      Alert.alert('Download failed', `${message}\n\nCached data remains available offline.`);
    } finally {
      setRefreshing(false);
    }
  }

  function activateDraft(draftId: string | null) {
    setActiveDraftId(draftId);
    if (draftId) {
      void saveJson(STORAGE_KEYS.activeDraftId, draftId);
      return;
    }

    void Storage.removeItemAsync(STORAGE_KEYS.activeDraftId);
  }

  function createDraft(facility: Facility) {
    const windowError = submissionWindowError({
      status: facility.exercise_status,
      startsAt: facility.exercise_starts_at,
      endsAt: facility.exercise_ends_at,
    });
    if (windowError) {
      Alert.alert('Exercise not open', windowError);
      return;
    }

    const now = new Date().toISOString();
    const assessmentId = `NIC-LOCAL-${Date.now()}`;
    const selectedForm = formForFacility(facility, forms.length > 0 ? forms : [fallbackForm]) ?? form;
    const formSnapshot = cloneFormSchema(selectedForm);
    const nextDraft: DraftAssessment = {
      id: `draft-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
      assessmentId,
      formSlug: formSnapshot.slug,
      formTitle: formSnapshot.title,
      formVersion: formSnapshot.version,
      formVersionId: formSnapshot.version_id,
      assessmentExerciseId: facility.exercise_id,
      assessmentExerciseUuid: facility.exercise_uuid,
      assessmentAssignmentId: facility.assignment_id,
      assignmentUuid: facility.assignment_uuid,
      exerciseName: facility.exercise_name,
      exerciseStatus: facility.exercise_status,
      exerciseStartsAt: facility.exercise_starts_at,
      exerciseEndsAt: facility.exercise_ends_at,
      formSchema: formSnapshot,
      facility,
      assessor: activeAssessor,
      answers: {
        assessment_id: assessmentId,
        assessment_date: now.slice(0, 10),
        assessor_id: activeAssessor.id,
        facility_id: facility.id,
        facility_name: facility.name,
        lga_id: facility.lga_id ?? '',
        ward_id: facility.ward_id ?? '',
        assessment_exercise_id: facility.exercise_id ?? '',
        assessment_assignment_id: facility.assignment_id ?? '',
      },
      attachments: [],
      currentSectionIndex: 0,
      startedAt: now,
      updatedAt: now,
    };

    const nextDrafts = [nextDraft, ...drafts];
    setDrafts(nextDrafts);
    void saveJson(STORAGE_KEYS.drafts, nextDrafts);
    activateDraft(nextDraft.id);
    setScreen('Assessment');
  }

  function updateDraft(draftId: string, updater: (draft: DraftAssessment) => DraftAssessment) {
    setDrafts((current) => {
      const next = current.map((draft) => (draft.id === draftId ? updater(draft) : draft));
      void saveJson(STORAGE_KEYS.drafts, next);
      return next;
    });
  }

  function setAnswer(fieldKey: string, value: unknown) {
    if (!activeDraft) {
      return;
    }

    updateDraft(activeDraft.id, (draft) => ({
      ...draft,
      answers: { ...draft.answers, [fieldKey]: value },
      updatedAt: new Date().toISOString(),
    }));
  }

  async function captureGps(field: FormField) {
    if (!activeDraft) {
      return;
    }

    const permission = await Location.requestForegroundPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('GPS permission needed', 'Allow location access to capture facility coordinates.');
      return;
    }

    const location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    const gps: GpsValue = {
      latitude: location.coords.latitude,
      longitude: location.coords.longitude,
      accuracy_meters: location.coords.accuracy,
      captured_at: new Date(location.timestamp).toISOString(),
    };
    setAnswer(field.key, gps);
  }

  async function addEvidence(field: FormField, source: 'camera' | 'library' | 'document') {
    if (!activeDraft) {
      return;
    }

    if (source === 'document') {
      const result = await DocumentPicker.getDocumentAsync({
        type: ['application/pdf', 'image/*'],
        multiple: true,
        copyToCacheDirectory: true,
      });

      if (result.canceled || !result.assets) {
        return;
      }

      const additions = result.assets.map((asset, index): EvidenceAttachment => ({
        id: `evidence-${Date.now()}-${index}`,
        fieldKey: field.key,
        type: 'document',
        uri: asset.uri,
        name: asset.name,
        mimeType: asset.mimeType ?? inferMimeType(asset.name),
        capturedAt: new Date().toISOString(),
      }));

      updateDraft(activeDraft.id, (draft) => {
        const attachments = [...draft.attachments, ...additions];
        return {
          ...draft,
          attachments,
          answers: {
            ...draft.answers,
            [field.key]: attachments
              .filter((attachment) => attachment.fieldKey === field.key)
              .map((attachment) => ({
                id: attachment.id,
                name: attachment.name,
                type: attachment.type,
                uri: attachment.uri,
                captured_at: attachment.capturedAt,
              })),
          },
          updatedAt: new Date().toISOString(),
        };
      });
      return;
    }

    const permission =
      source === 'camera'
        ? await ImagePicker.requestCameraPermissionsAsync()
        : await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission needed', 'Allow access to attach evidence to this assessment.');
      return;
    }

    const result =
      source === 'camera'
        ? await ImagePicker.launchCameraAsync({ quality: 0.75, mediaTypes: ['images'] })
        : await ImagePicker.launchImageLibraryAsync({ quality: 0.75, mediaTypes: ['images'], allowsMultipleSelection: true });

    if (result.canceled || !result.assets) {
      return;
    }

    const additions = result.assets.map((asset, index): EvidenceAttachment => {
      const mimeType = asset.mimeType ?? 'image/jpeg';
      return {
        id: `evidence-${Date.now()}-${index}`,
        fieldKey: field.key,
        type: field.type === 'photo_collection' ? 'photo' : 'document',
        uri: asset.uri,
        name: asset.fileName ?? `${field.key}-${Date.now()}-${index}.${mimeType.includes('png') ? 'png' : 'jpg'}`,
        mimeType,
        capturedAt: new Date().toISOString(),
      };
    });

    updateDraft(activeDraft.id, (draft) => {
      const attachments = [...draft.attachments, ...additions];
      return {
        ...draft,
        attachments,
        answers: {
          ...draft.answers,
          [field.key]: attachments
            .filter((attachment) => attachment.fieldKey === field.key)
            .map((attachment) => ({
              id: attachment.id,
              name: attachment.name,
              type: attachment.type,
              uri: attachment.uri,
              captured_at: attachment.capturedAt,
            })),
        },
        updatedAt: new Date().toISOString(),
      };
    });
  }

  function removeEvidence(attachmentId: string) {
    if (!activeDraft) {
      return;
    }

    updateDraft(activeDraft.id, (draft) => {
      const removed = draft.attachments.find((attachment) => attachment.id === attachmentId);
      const attachments = draft.attachments.filter((attachment) => attachment.id !== attachmentId);
      const answers = { ...draft.answers };
      if (removed) {
        answers[removed.fieldKey] = attachments
          .filter((attachment) => attachment.fieldKey === removed.fieldKey)
          .map((attachment) => ({ id: attachment.id, name: attachment.name, type: attachment.type, uri: attachment.uri }));
      }

      return { ...draft, attachments, answers, updatedAt: new Date().toISOString() };
    });
  }

  function goToSection(index: number) {
    if (!activeDraft) {
      return;
    }

    updateDraft(activeDraft.id, (draft) => ({
      ...draft,
      currentSectionIndex: Math.max(0, Math.min(index, activeForm.sections.length - 1)),
      updatedAt: new Date().toISOString(),
    }));
  }

  function finalizeDraft() {
    if (!activeDraft) {
      return;
    }

    const windowError = submissionWindowError({
      status: activeDraft.exerciseStatus,
      startsAt: activeDraft.exerciseStartsAt,
      endsAt: activeDraft.exerciseEndsAt,
    });
    if (windowError) {
      Alert.alert('Submission window closed', windowError);
      return;
    }

    const result = validateDraft(activeForm, activeDraft);
    if (result.errors.length > 0) {
      setScreen('Review');
      Alert.alert('Fix required answers', `${result.errors.length} item(s) need attention before finalizing.`);
      return;
    }

    const queuedDraft: DraftAssessment = {
      ...activeDraft,
      submittedAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    const queueItem: QueueItem = {
      id: `queue-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
      draft: queuedDraft,
      status: 'pending',
      attempts: 0,
      attachmentStatus: initialAttachmentStatus(queuedDraft),
      queuedAt: new Date().toISOString(),
    };
    const nextDrafts = drafts.filter((draft) => draft.id !== activeDraft.id);
    const nextQueue = [queueItem, ...queue];

    setDrafts(nextDrafts);
    setQueue(nextQueue);
    activateDraft(null);
    void saveJson(STORAGE_KEYS.drafts, nextDrafts);
    void saveJson(STORAGE_KEYS.queue, nextQueue);
    setScreen('Sync');
    setLastMessage('Assessment queued. It will upload when internet is available.');
  }

  async function syncQueue(itemIds?: string[]) {
    if (queue.length === 0) {
      setLastMessage('No pending uploads.');
      return;
    }

    if (!online) {
      Alert.alert('Offline', 'Connect to the internet before syncing pending uploads.');
      return;
    }

    setSyncing(true);
    let workingQueue = queue;
    const synced: HistoryItem[] = [];
    const selectedIds = itemIds ? new Set(itemIds) : null;

    for (const item of queue) {
      if (selectedIds && !selectedIds.has(item.id)) {
        continue;
      }

      if (item.status === 'syncing') {
        continue;
      }

      const attemptAt = new Date().toISOString();
      workingQueue = workingQueue.map((candidate) =>
        candidate.id === item.id
          ? {
              ...candidate,
              status: 'syncing',
              attempts: candidate.attempts + 1,
              lastAttemptAt: attemptAt,
              lastError: undefined,
              attachmentStatus: candidate.attachmentStatus ?? initialAttachmentStatus(candidate.draft),
            }
          : candidate
      );
      setQueue(workingQueue);
      await saveJson(STORAGE_KEYS.queue, workingQueue);

      try {
        const currentItem = workingQueue.find((candidate) => candidate.id === item.id) ?? item;
        const response = await submitDraft(currentItem.draft, currentItem.remoteUuid, currentItem.attachmentStatus);
        synced.push({
          id: item.id,
          assessmentId: item.draft.assessmentId,
          facilityName: item.draft.facility.name,
          syncedAt: new Date().toISOString(),
          remoteUuid: response.uuid,
          attachmentsUploaded: response.attachmentsUploaded,
        });
        workingQueue = workingQueue.filter((candidate) => candidate.id !== item.id);
      } catch (error) {
        const message = error instanceof Error ? error.message : 'Sync failed.';
        const uploadError = error instanceof SyncUploadError ? error : null;
        workingQueue = workingQueue.map((candidate) =>
          candidate.id === item.id
            ? {
                ...candidate,
                status: 'failed',
                lastError: message,
                remoteUuid: uploadError?.remoteUuid ?? candidate.remoteUuid,
                attachmentStatus: uploadError?.attachmentStatus ?? candidate.attachmentStatus,
              }
            : candidate
        );
      }

      setQueue(workingQueue);
      await saveJson(STORAGE_KEYS.queue, workingQueue);
    }

    if (synced.length > 0) {
      const nextHistory = [...synced, ...history];
      setHistory(nextHistory);
      await saveJson(STORAGE_KEYS.history, nextHistory);
    }

    setLastMessage(
      synced.length > 0
        ? workingQueue.length > 0
          ? `Synced ${synced.length} assessment(s). ${workingQueue.length} still need attention.`
          : 'All pending assessments and evidence uploaded successfully.'
        : 'No uploads completed. Check the failed items and retry.'
    );
    setSyncing(false);
  }

  async function retryQueueItem(itemId: string) {
    await syncQueue([itemId]);
  }

  async function submitDraft(
    draft: DraftAssessment,
    existingRemoteUuid?: string,
    previousAttachmentStatus?: QueueItem['attachmentStatus']
  ): Promise<{ uuid?: string; attachmentsUploaded: number }> {
    const draftForm = draft.formSchema ?? formForDraft(draft, forms.length > 0 ? forms : [fallbackForm]) ?? fallbackForm;
    const localValidation = validateDraft(draftForm, draft);
    const signatureAttachments = await createSignatureAttachments(draft);
    const submissionAttachments = [...draft.attachments, ...signatureAttachments];
    const payload = {
      client_submission_id: draft.clientSubmissionId ?? draft.id,
      assessment_id: draft.assessmentId,
      form_slug: draft.formSlug,
      form_version: draft.formVersion,
      form_version_id: draft.formVersionId,
      assessment_exercise_id: draft.assessmentExerciseId,
      assessment_exercise_uuid: draft.assessmentExerciseUuid,
      assessment_assignment_id: draft.assessmentAssignmentId,
      assignment_uuid: draft.assignmentUuid,
      facility_id: draft.facility.id,
      facility_name: draft.facility.name,
      lga: draft.facility.lga,
      ward: draft.facility.ward,
      facility: {
        id: draft.facility.id,
        code: draft.facility.code,
        name: draft.facility.name,
        type: draft.facility.type,
        lga: draft.facility.lga,
        ward: draft.facility.ward,
      },
      target_type: draft.facility.target_type ?? 'facility',
      target: draft.facility.target_type && draft.facility.target_type !== 'facility'
        ? {
            id: draft.facility.id,
            type: draft.facility.target_type,
            name: draft.facility.name,
          }
        : null,
      assessor_id: draft.assessor.id,
      assessor_name: draft.assessor.name,
      assessor: draft.assessor,
      assessment_date: String(draft.answers.assessment_date ?? new Date().toISOString().slice(0, 10)),
      source: 'mobile',
      status: 'submitted',
      answers: draft.answers,
      gps: draft.answers.gps_coordinates ?? null,
      device: {
        app: 'nicare-odk-mobile',
        api_base_url: API_BASE_URL,
        local_validation: {
          valid: localValidation.errors.length === 0,
          errors_count: localValidation.errors.length,
          score: localValidation.score,
        },
      },
      started_at: draft.startedAt,
      submitted_at: draft.submittedAt ?? new Date().toISOString(),
    };

    const remoteUuid = isRemoteSubmissionUuid(existingRemoteUuid) ? existingRemoteUuid : undefined;
    const submission = remoteUuid
      ? null
      : await apiPost<{ success: boolean; data: { id?: number; uuid?: string } }>('/mobile/secure/submissions', payload);
    const uuid = remoteUuid ?? submission?.data.uuid;

    if (!isRemoteSubmissionUuid(uuid)) {
      throw new Error('Submission upload did not return a valid remote UUID.');
    }

    const attachmentStatus = {
      ...initialAttachmentStatus({ ...draft, attachments: submissionAttachments }),
      ...(previousAttachmentStatus ?? {}),
    };
    let attachmentsUploaded = countUploadedAttachments(attachmentStatus);
    if (uuid) {
      for (const attachment of submissionAttachments) {
        if (attachmentStatus[attachment.id]?.status === 'uploaded') {
          continue;
        }

        try {
          await uploadAttachment(uuid, attachment);
          attachmentsUploaded += 1;
          attachmentStatus[attachment.id] = {
            status: 'uploaded',
            uploadedAt: new Date().toISOString(),
          };
        } catch (error) {
          const message = error instanceof Error ? error.message : 'Evidence upload failed.';
          attachmentStatus[attachment.id] = {
            status: 'failed',
            error: message,
          };
          throw new SyncUploadError('Submission saved, but an evidence upload failed.', uuid, attachmentStatus);
        }
      }
    }

    return { uuid, attachmentsUploaded };
  }

  async function uploadAttachment(submissionUuid: string, attachment: EvidenceAttachment) {
    const data = new FormData();
    data.append('file', {
      uri: attachment.uri,
      name: attachment.name,
      type: attachment.mimeType,
    } as unknown as Blob);
    data.append('field_key', attachment.fieldKey);
    // The API uses this stable local ID to make timeout retries idempotent.
    data.append('client_attachment_id', attachment.id);
    data.append('type', attachment.type);
    data.append('original_name', attachment.name);
    data.append('captured_at', attachment.capturedAt);

    const token = await loadJson<string | null>(STORAGE_KEYS.authToken, null);
    const url = `${API_BASE_URL}/mobile/secure/submissions/${submissionUuid}/attachments`;
    const response = await fetchWithTimeout(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: data,
    });

    if (!response.ok) {
      throw new Error(await responseError(response, url));
    }
  }

  async function createSignatureAttachments(draft: DraftAssessment): Promise<EvidenceAttachment[]> {
    return [];
  }

  if (!ready) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="dark" />
        <View style={styles.centerScreen}>
          <Brand large />
          <Text style={styles.loadingText}>Preparing offline workspace...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (!signedIn) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="dark" />
        <View style={styles.loginScreen}>
          <Brand large />
          <Text style={styles.welcome}>Welcome back</Text>
          <Text style={styles.mutedCenter}>Sign in to access your assigned assessment forms.</Text>
          <TextInput
            style={styles.input}
            value={mobileEmail}
            onChangeText={setMobileEmail}
            autoCapitalize="none"
            keyboardType="email-address"
            placeholder="Email address"
            editable={!authSubmitting}
          />
          <TextInput
            style={styles.input}
            secureTextEntry
            value={mobilePassword}
            onChangeText={setMobilePassword}
            placeholder="Password"
            editable={!authSubmitting}
          />
          {authError ? <Text style={styles.errorText}>{authError}</Text> : null}
          <View style={styles.rememberRow}>
            <Text style={styles.rememberText}>Remember me</Text>
            <Text style={styles.linkText}>Forgot password?</Text>
          </View>
          <PrimaryButton label={authSubmitting ? 'Signing in...' : 'Sign in'} onPress={signInMobile} disabled={authSubmitting || !mobileEmail.trim() || !mobilePassword} />
          <View style={styles.noticeCard}>
            <Text style={styles.noticeIcon}>Wi-Fi</Text>
            <Text style={styles.noticeText}>Connect to download assigned forms and facility references.</Text>
          </View>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="dark" />
      <View style={styles.appScreen}>
        <ScrollView contentContainerStyle={styles.content}>
          {screen !== 'Home' ? (
            <View style={styles.header}>
              <Brand />
              <StatusPill label={online ? 'Online' : 'Offline'} tone={online ? 'success' : 'warning'} />
            </View>
          ) : null}

          {screen === 'Home' && (
            <HomeScreen
              form={form}
              assignments={assignments}
              exercises={assignedExercises}
              drafts={drafts}
              queue={queue}
              history={history}
              lastMessage={lastMessage}
              refreshing={refreshing}
              onRefresh={refreshReferenceData}
              onStart={() => setScreen('Facilities')}
              onSync={() => setScreen('Sync')}
              assessorName={activeAssessor.name}
              workspaces={mobileWorkspaces}
              selectedWorkspaceId={selectedWorkspaceId}
              onWorkspace={switchMobileWorkspace}
              onOpenDraft={(draftId) => {
                activateDraft(draftId);
                setScreen('Assessment');
              }}
            />
          )}

          {screen === 'Forms' && (
            <FormsScreen
              forms={forms}
              cacheMeta={formCacheMeta}
              assignments={assignments}
              exercises={assignedExercises}
              facilities={facilities}
              lastMessage={lastMessage}
              refreshing={refreshing}
              onRefresh={refreshReferenceData}
            />
          )}

          {screen === 'Facilities' && (
            <FacilitiesScreen
              facilities={filteredFacilities}
              exercises={assignedExercises}
              selectedExerciseId={selectedExerciseId}
              lgas={lgas}
              wards={wards}
              search={search}
              selectedLga={selectedLga}
              selectedWard={selectedWard}
              onExercise={setSelectedExerciseId}
              onSearch={setSearch}
              onLga={setSelectedLga}
              onWard={setSelectedWard}
              onSelect={createDraft}
            />
          )}

          {screen === 'Assessment' && activeDraft && (
            <AssessmentScreen
              form={activeForm}
              latestForm={latestFormForActiveDraft}
              draft={activeDraft}
              onAnswer={setAnswer}
              onCaptureGps={captureGps}
              onAddEvidence={addEvidence}
              onRemoveEvidence={removeEvidence}
              onSection={goToSection}
              onReview={() => setScreen('Review')}
            />
          )}

          {screen === 'Review' && activeDraft && (
            <ReviewScreen
              form={activeForm}
              draft={activeDraft}
              validation={validation}
              onBack={() => setScreen('Assessment')}
              onFix={(sectionIndex) => {
                goToSection(sectionIndex);
                setScreen('Assessment');
              }}
              onFinalize={finalizeDraft}
            />
          )}

          {screen === 'Sync' && (
            <SyncScreen queue={queue} history={history} syncing={syncing} lastMessage={lastMessage} onSync={() => syncQueue()} onRetry={retryQueueItem} />
          )}

          {screen === 'Assessments' && (
            <AssessmentsScreen
              drafts={drafts}
              queue={queue}
              history={history}
              onOpenDraft={(draftId) => {
                activateDraft(draftId);
                setScreen('Assessment');
              }}
            />
          )}
        </ScrollView>

        <View style={styles.tabBar}>
          {TAB_ITEMS.map((item) => (
            <Pressable
              key={item.screen}
              style={[styles.tabItem, item.screen === screen && styles.tabItemActive]}
              onPress={() => setScreen(item.screen)}
            >
              <Feather name={item.icon} size={16} color={item.screen === screen ? colors.white : '#a9cbb9'} />
              <Text style={[styles.tabText, item.screen === screen && styles.tabTextActive]}>{item.label}</Text>
            </Pressable>
          ))}
        </View>
      </View>
    </SafeAreaView>
  );
}

function HomeScreen({
  form,
  assignments,
  exercises,
  drafts,
  queue,
  history,
  lastMessage,
  refreshing,
  onRefresh,
  onStart,
  onSync,
  assessorName,
  workspaces,
  selectedWorkspaceId,
  onWorkspace,
  onOpenDraft,
}: {
  form: FormSchema;
  assignments: MobileAssignment[];
  exercises: MobileExercise[];
  drafts: DraftAssessment[];
  queue: QueueItem[];
  history: HistoryItem[];
  lastMessage: string;
  refreshing: boolean;
  onRefresh: () => void;
  onStart: () => void;
  onSync: () => void;
  assessorName: string;
  workspaces: MobileWorkspace[];
  selectedWorkspaceId: number | null;
  onWorkspace: (workspaceId: number) => void;
  onOpenDraft: (draftId: string) => void;
}) {
  const activeDraft = drafts[0];
  const roundFacilities = assignments
    .map((assignment) => assignment.facility)
    .filter(Boolean)
    .slice(0, 3) as Facility[];

  return (
    <>
      <View style={styles.fieldTopBar}>
        <DataKitLogo />
        <StatusPill label="Online" tone="success" />
      </View>

      {workspaces.length > 1 ? (
        <Pressable
          style={styles.workspaceSwitcher}
          onPress={() => {
            const currentIndex = workspaces.findIndex((workspace) => workspace.id === selectedWorkspaceId);
            const nextWorkspace = workspaces[(currentIndex + 1) % workspaces.length];
            if (nextWorkspace) onWorkspace(nextWorkspace.id);
          }}
        >
          <Text style={styles.workspaceSwitcherLabel}>Workspace</Text>
          <Text style={styles.workspaceSwitcherValue} numberOfLines={1}>
            {workspaces.find((workspace) => workspace.id === selectedWorkspaceId)?.name ?? 'Select workspace'}
          </Text>
          <Feather name="chevron-down" size={16} color={colors.green800} />
        </Pressable>
      ) : null}

      <Text style={styles.fieldDate}>FRI 09 OCT 2026 - EXERCISE {exercises[0]?.code ?? 'FIELD QA'}</Text>
      <Text style={styles.fieldGreeting}>Afternoon,{'\n'}{assessorName || 'Assessor'}.</Text>

      <Pressable style={styles.featureWorkCard} onPress={activeDraft ? () => onOpenDraft(activeDraft.id) : onStart}>
        <View style={styles.rowBetween}>
          <Text style={styles.featureEyebrow}>{activeDraft ? 'Draft in progress' : 'Ready for assessment'}</Text>
          <Text style={styles.featureSaved}>{activeDraft ? `Saved ${formatTime(activeDraft.updatedAt)}` : `${assignments.length} assigned`}</Text>
        </View>
        <Text style={styles.featureTitle}>{activeDraft?.facility.name ?? roundFacilities[0]?.name ?? 'Start a facility assessment'}</Text>
        <Text style={styles.featureSubtitle}>{activeDraft ? 'Continue draft' : 'Choose an assigned facility'}</Text>
        <View style={styles.featureActionRow}>
          <Text style={styles.featureActionText}>{activeDraft ? 'Continue draft' : 'Start first assessment'}</Text>
          <View style={styles.featureActionIcon}>
            <Feather name="arrow-right" size={16} color={colors.white} />
          </View>
        </View>
      </Pressable>

      <View style={styles.metricGrid}>
        <MetricTile value={String(drafts.length)} label="Drafts" icon="edit-3" />
        <MetricTile value={String(queue.length)} label="Pending" icon="upload-cloud" />
        <MetricTile value={String(history.length)} label="Synced" icon="check-circle" />
      </View>

      <View style={styles.actionStack}>
        <PrimaryButton label="Start assessment" icon="plus" onPress={onStart} />
      </View>

      <View style={styles.roundHeader}>
        <Text style={styles.roundTitle}>The round</Text>
        <Text style={styles.roundHint}>{assignments.length} stops</Text>
      </View>
      <View style={styles.roundList}>
        {roundFacilities.map((facility, index) => (
          <Pressable key={`${facility.id}-${facility.assignment_uuid ?? index}`} style={styles.roundRow} onPress={onStart}>
            <View style={styles.roundNumber}>
              <Text style={styles.roundNumberText}>{String(index + 1)}</Text>
            </View>
            <View style={styles.flexOne}>
              <Text style={styles.roundFacility}>{facility.name}</Text>
              <Text style={styles.roundMeta}>{formatRoundMeta(facility)}</Text>
            </View>
            <Feather name="chevron-right" size={18} color={colors.green800} />
          </Pressable>
        ))}
        {roundFacilities.length === 0 ? <EmptyState label="No assigned facilities downloaded yet." /> : null}
      </View>

      <View style={styles.offlinePackCard}>
        <View style={styles.rowBetween}>
          <View style={styles.offlinePackTitleRow}>
            <Feather name={refreshing ? 'download-cloud' : 'check'} size={16} color={colors.green800} />
            <Text style={styles.offlinePackTitle}>{refreshing ? 'Downloading pack...' : 'Offline pack ready'}</Text>
          </View>
          <StatusPill label={`${assignments.length} work`} tone="success" />
        </View>
        <Text style={styles.offlinePackMeta}>{assignments.length} assignments - {formsCountLabel(form)} - {roundFacilities.length} facilities</Text>
        <Text style={styles.bannerText}>{lastMessage}</Text>
        <SecondaryButton label={refreshing ? 'Downloading...' : 'Download updates'} icon="download-cloud" onPress={onRefresh} disabled={refreshing} />
      </View>

      {queue.length > 0 && (
        <View style={styles.marginTop}>
          <PrimaryButton label={`Sync ${queue.length} pending upload(s)`} onPress={onSync} />
        </View>
      )}
    </>
  );
}

function FormsScreen({
  forms,
  cacheMeta,
  assignments,
  exercises,
  facilities,
  lastMessage,
  refreshing,
  onRefresh,
}: {
  forms: FormSchema[];
  cacheMeta: FormCacheMeta | null;
  assignments: MobileAssignment[];
  exercises: MobileExercise[];
  facilities: Facility[];
  lastMessage: string;
  refreshing: boolean;
  onRefresh: () => void;
}) {
  return (
    <>
      <View style={styles.pageIntroCard}>
        <Text style={styles.screenTitle}>Forms and downloads</Text>
        <Text style={styles.muted}>Assigned forms, exercises, and facilities are cached for offline assessment.</Text>
      </View>
      {cacheMeta ? (
        <View style={styles.readyPanel}>
          <View style={styles.rowBetween}>
            <View style={styles.readyIcon}>
              <Feather name="check-circle" size={18} color={colors.green800} />
            </View>
            <StatusPill label="Offline ready" tone="success" />
          </View>
          <Text style={styles.readyTitle}>Last download: {formatTime(cacheMeta.downloaded_at)}</Text>
          <Text style={styles.muted}>
            {cacheMeta.assignments_count ?? assignments.length} assignment(s), {cacheMeta.exercises_count ?? exercises.length} exercise(s), {cacheMeta.forms_count} form(s), {cacheMeta.facilities_count} assigned facilities cached from {cacheMeta.api_base_url}
          </Text>
        </View>
      ) : null}
      {forms.map((item) => (
        <View key={item.slug} style={styles.downloadCard}>
          <View style={styles.rowBetween}>
            <View style={styles.workIcon}>
              <Feather name="file-text" size={18} color={colors.green800} />
            </View>
            <View style={styles.flexOne}>
              <Text style={styles.cardTitle}>{item.title}</Text>
              <Text style={styles.muted}>Version {item.version} - {item.sections.length} sections - {item.fields.length} fields</Text>
            </View>
            <StatusPill label={item.requires_scoring ? 'Scored' : 'Ready'} tone="success" />
          </View>
          <View style={styles.downloadMetaBox}>
            <Text style={styles.readyText}>Ready for offline use</Text>
            <Text style={styles.muted}>
              {schemaRuleSummary(item)}
            </Text>
            {item.requires_scoring ? (
              <Text style={styles.metaText}>Maximum score: {item.scoring_max_score ?? 'not set'}</Text>
            ) : null}
            {item.downloaded_at ? <Text style={styles.metaText}>Downloaded {formatTime(item.downloaded_at)}</Text> : null}
          </View>
        </View>
      ))}
      {forms.length === 0 && <EmptyState label="No assigned forms downloaded yet." />}

      <View style={styles.directoryCard}>
        <View style={styles.workIcon}>
          <Feather name="map" size={18} color={colors.green800} />
        </View>
        <View style={styles.flexOne}>
          <Text style={styles.cardTitle}>Assigned facility directory</Text>
          <Text style={styles.muted}>{facilities.length} facilities scoped to your assignments</Text>
        </View>
      </View>

      <View style={styles.syncCard}>
        <View style={styles.syncIcon}>
          <Feather name="info" size={18} color={colors.amber700} />
        </View>
        <View style={styles.flexOne}>
          <Text style={styles.bannerTitle}>{lastMessage}</Text>
          <Text style={styles.bannerText}>Refresh before field visits when internet is available.</Text>
        </View>
      </View>
      <PrimaryButton label={refreshing ? 'Downloading...' : 'Download updates'} icon="download-cloud" onPress={onRefresh} disabled={refreshing} />
    </>
  );
}

function FacilitiesScreen({
  facilities,
  exercises,
  selectedExerciseId,
  lgas,
  wards,
  search,
  selectedLga,
  selectedWard,
  onExercise,
  onSearch,
  onLga,
  onWard,
  onSelect,
}: {
  facilities: Facility[];
  exercises: MobileExercise[];
  selectedExerciseId: string;
  lgas: string[];
  wards: string[];
  search: string;
  selectedLga: string;
  selectedWard: string;
  onExercise: (value: string) => void;
  onSearch: (value: string) => void;
  onLga: (value: string) => void;
  onWard: (value: string) => void;
  onSelect: (facility: Facility) => void;
}) {
  return (
    <>
      <View style={styles.pageHeading}>
        <Text style={styles.screenTitle}>Select facility</Text>
        <Text style={styles.muted}>Choose an assigned facility to start an assessment.</Text>
      </View>

      <View style={styles.filterPanel}>
        <Text style={styles.filterLabel}>Exercise</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          <FilterChip label="All assigned" active={selectedExerciseId === 'all'} onPress={() => onExercise('all')} />
          {exercises.map((exercise) => (
            <FilterChip
              key={exercise.id}
              label={exercise.code ?? exercise.name}
              active={selectedExerciseId === String(exercise.id)}
              onPress={() => onExercise(String(exercise.id))}
            />
          ))}
        </ScrollView>
        <View style={styles.searchBox}>
          <Feather name="search" size={16} color={colors.slate500} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search for a facility"
            value={search}
            onChangeText={onSearch}
            placeholderTextColor={colors.slate500}
          />
        </View>
        <Text style={styles.filterLabel}>LGA</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          {lgas.map((lga) => (
            <FilterChip key={lga} label={lga} active={selectedLga === lga} onPress={() => onLga(lga)} />
          ))}
        </ScrollView>
        <Text style={styles.filterLabel}>Ward</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          {wards.map((ward) => (
            <FilterChip key={ward} label={ward} active={selectedWard === ward} onPress={() => onWard(ward)} />
          ))}
        </ScrollView>
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Assigned facilities</Text>
        <Text style={styles.sectionHint}>{facilities.length} found</Text>
      </View>
      {facilities.map((facility) => (
        <Pressable key={`${facility.id}-${facility.code}`} style={styles.facilityCard} onPress={() => onSelect(facility)}>
          <View style={styles.facilityIcon}>
            <Feather name="home" size={18} color={colors.green800} />
          </View>
          <View style={styles.flexOne}>
            <View style={styles.rowBetween}>
              <Text style={styles.cardTitle}>{facility.name}</Text>
              <StatusPill label={facility.accreditation_status ?? 'Active'} tone="success" />
            </View>
            <Text style={styles.muted}>{facility.type ?? 'Health facility'}</Text>
            <Text style={styles.metaText}>{facility.lga ?? 'Unknown LGA'} LGA - {facility.ward ?? 'Unknown ward'}</Text>
            {facility.exercise_name ? (
              <Text style={styles.metaText}>{facility.exercise_name} - {facility.exercise_starts_at ?? 'open'} to {facility.exercise_ends_at ?? 'close'}</Text>
            ) : null}
          </View>
        </Pressable>
      ))}
      {facilities.length === 0 && <EmptyState label="No facility matches this filter." />}
    </>
  );
}

function AssessmentScreen({
  form,
  latestForm,
  draft,
  onAnswer,
  onCaptureGps,
  onAddEvidence,
  onRemoveEvidence,
  onSection,
  onReview,
}: {
  form: FormSchema;
  latestForm: FormSchema | null;
  draft: DraftAssessment;
  onAnswer: (fieldKey: string, value: unknown) => void;
  onCaptureGps: (field: FormField) => void;
  onAddEvidence: (field: FormField, source: 'camera' | 'library' | 'document') => void;
  onRemoveEvidence: (attachmentId: string) => void;
  onSection: (index: number) => void;
  onReview: () => void;
}) {
  const section = form.sections[draft.currentSectionIndex] ?? form.sections[0];
  const fields = fieldsForSection(form, section.key, draft.answers);
  const renderItems = buildSectionRenderItems(fields);
  const progress = Math.round(((draft.currentSectionIndex + 1) / Math.max(form.sections.length, 1)) * 100);
  const newerVersionAvailable = Boolean(latestForm && latestForm.version !== form.version);

  return (
    <>
      <Text style={styles.screenTitle}>{draft.facility.name}</Text>
      <Text style={styles.muted}>{draft.formTitle} - Section {draft.currentSectionIndex + 1} of {form.sections.length}</Text>
      {draft.reviewNote ? (
        <View style={styles.versionNotice}>
          <Text style={styles.versionNoticeTitle}>Returned for correction</Text>
          <Text style={styles.versionNoticeText}>{draft.reviewNote}</Text>
        </View>
      ) : null}
      {newerVersionAvailable ? (
        <View style={styles.versionNotice}>
          <Text style={styles.versionNoticeTitle}>Using saved form version {form.version}</Text>
          <Text style={styles.versionNoticeText}>A newer downloaded version is available. This draft will continue with the version it was started with.</Text>
        </View>
      ) : null}
      <View style={styles.progressTrack}>
        <View style={[styles.progressBar, { width: `${progress}%` }]} />
      </View>
      <Text style={styles.metaText}>{progress}% complete</Text>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.sectionTabs}>
        {form.sections.map((item, index) => (
          <Pressable
            key={item.key}
            style={[styles.sectionTab, index === draft.currentSectionIndex && styles.sectionTabActive]}
            onPress={() => onSection(index)}
          >
            <Text style={[styles.sectionTabText, index === draft.currentSectionIndex && styles.sectionTabTextActive]}>
              {index + 1}
            </Text>
          </Pressable>
        ))}
      </ScrollView>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{section.label}</Text>
        {section.description ? <Text style={styles.muted}>{section.description}</Text> : null}
      </View>

      {renderItems.map((item) => {
        if (item.type === 'group') {
          return (
            <View key={item.key} style={styles.fieldGroupHeader}>
              <Text style={styles.fieldGroupTitle}>{item.label}</Text>
            </View>
          );
        }

        const field = item.field;
        return (
          <FieldRenderer
            key={field.key}
            field={field}
            value={draft.answers[field.key]}
            attachments={draft.attachments.filter((attachment) => attachment.fieldKey === field.key)}
            onAnswer={onAnswer}
            onCaptureGps={() => onCaptureGps(field)}
            onAddEvidence={(source) => onAddEvidence(field, source)}
            onRemoveEvidence={onRemoveEvidence}
          />
        );
      })}

      {fields.length === 0 && <EmptyState label="No visible questions in this section." />}

      <View style={styles.bottomActions}>
        <SecondaryButton label="Back" onPress={() => onSection(draft.currentSectionIndex - 1)} disabled={draft.currentSectionIndex === 0} />
        {draft.currentSectionIndex < form.sections.length - 1 ? (
          <PrimaryButton label="Next" onPress={() => onSection(draft.currentSectionIndex + 1)} compact />
        ) : (
          <PrimaryButton label="Review" onPress={onReview} compact />
        )}
      </View>
      <Text style={styles.savedText}>Saved on device - {formatTime(draft.updatedAt)}</Text>
    </>
  );
}

function FieldRenderer({
  field,
  value,
  attachments,
  onAnswer,
  onCaptureGps,
  onAddEvidence,
  onRemoveEvidence,
}: {
  field: FormField;
  value: unknown;
  attachments: EvidenceAttachment[];
  onAnswer: (fieldKey: string, value: unknown) => void;
  onCaptureGps: () => void;
  onAddEvidence: (source: 'camera' | 'library' | 'document') => void;
  onRemoveEvidence: (attachmentId: string) => void;
}) {
  const requiredMarker = field.required || field.required_if ? ' *' : '';

  if (field.readonly || field.type === 'readonly' || (field.type === 'reference_select' && !field.options?.length)) {
    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        <Text style={styles.readonlyValue}>{formatFieldValue(field, value)}</Text>
      </View>
    );
  }

  if (field.type === 'yes_no_with_count') {
    const options = field.options?.length ? field.options : yesNoOptions();
    const compound = normalizeCompoundAnswer(value);
    const countRequiredValue = field.count_required_value ?? 'yes';
    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        {field.group ? <Text style={styles.metaText}>{field.group}</Text> : null}
        <View style={styles.optionGrid}>
          {options.map((option) => (
            <Pressable
              key={option.value}
              style={[styles.optionButton, compound.answer === option.value && styles.optionButtonActive]}
              onPress={() => onAnswer(field.key, { ...compound, answer: option.value, count: option.value === countRequiredValue ? compound.count : null })}
            >
              <Text style={[styles.optionText, compound.answer === option.value && styles.optionTextActive]}>{option.label}</Text>
            </Pressable>
          ))}
        </View>
        {compound.answer === countRequiredValue ? (
          <View style={styles.compoundCountBox}>
            <Text style={styles.fieldLabel}>{field.count_label ?? 'Total number'} *</Text>
            <TextInput
              style={styles.input}
              value={compound.count === undefined || compound.count === null ? '' : String(compound.count)}
              keyboardType="numeric"
              placeholder={field.count_label ?? 'Total number'}
              onChangeText={(next) => onAnswer(field.key, { ...compound, count: next })}
              placeholderTextColor={colors.slate500}
            />
          </View>
        ) : null}
      </View>
    );
  }

  if (field.type === 'yes_no' || field.type === 'select' || field.type === 'reference_select') {
    const options = field.options?.length ? field.options : yesNoOptions();
    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        {field.group ? <Text style={styles.metaText}>{field.group}</Text> : null}
        <View style={styles.optionGrid}>
          {options.map((option) => (
            <Pressable
              key={option.value}
              style={[styles.optionButton, value === option.value && styles.optionButtonActive]}
              onPress={() => onAnswer(field.key, option.value)}
            >
              <Text style={[styles.optionText, value === option.value && styles.optionTextActive]}>{option.label}</Text>
            </Pressable>
          ))}
        </View>
      </View>
    );
  }

  if (['text', 'textarea', 'number', 'currency', 'date'].includes(field.type)) {
    const keyboardType = field.type === 'number' || field.type === 'currency' ? 'numeric' : 'default';
    const placeholder = field.type === 'date' ? 'YYYY-MM-DD' : (field.placeholder ?? field.label);

    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        {field.help_text ? <Text style={styles.muted}>{field.help_text}</Text> : null}
        <TextInput
          style={[styles.input, field.type === 'textarea' && styles.textArea]}
          value={value === undefined || value === null ? '' : String(value)}
          multiline={field.type === 'textarea'}
          placeholder={placeholder}
          keyboardType={keyboardType}
          onChangeText={(next) => onAnswer(field.key, next)}
          placeholderTextColor={colors.slate500}
        />
      </View>
    );
  }

  if (field.type === 'checkbox') {
    return (
      <Pressable style={styles.checkRow} onPress={() => onAnswer(field.key, value !== true)}>
        <View style={[styles.checkBox, value === true && styles.checkBoxActive]}>
          <Text style={styles.checkText}>{value === true ? 'OK' : ''}</Text>
        </View>
        <Text style={styles.checkLabel}>{field.label}{requiredMarker}</Text>
      </Pressable>
    );
  }

  if (field.type === 'gps') {
    const gps = value as GpsValue | undefined;
    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        <View style={styles.mapBox}>
          <Text style={styles.mapPin}>GPS</Text>
          <Text style={styles.mapText}>
            {gps?.latitude && gps?.longitude
              ? `${gps.latitude.toFixed(6)}, ${gps.longitude.toFixed(6)}`
              : 'Location not captured'}
          </Text>
          {gps?.accuracy_meters !== undefined && gps?.accuracy_meters !== null ? (
            <Text style={styles.metaText}>Accuracy: {Math.round(gps.accuracy_meters)} m</Text>
          ) : null}
        </View>
        <SecondaryButton label={gps ? 'Refresh location' : 'Capture location'} onPress={onCaptureGps} />
      </View>
    );
  }

  if (field.type === 'photo_collection' || field.type === 'file_collection') {
    return (
      <View style={styles.fieldCard}>
        <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
        <View style={styles.attachmentGrid}>
          {attachments.map((attachment) => (
            <View key={attachment.id} style={styles.attachmentTile}>
              {attachment.mimeType.startsWith('image/') ? <Image source={{ uri: attachment.uri }} style={styles.attachmentImage} /> : null}
              <Text numberOfLines={1} style={styles.attachmentName}>{attachment.name}</Text>
              <Pressable style={styles.removeButton} onPress={() => onRemoveEvidence(attachment.id)}>
                <Text style={styles.removeText}>Remove</Text>
              </Pressable>
            </View>
          ))}
        </View>
        <View style={styles.bottomActions}>
          <SecondaryButton label="Use camera" onPress={() => onAddEvidence('camera')} />
          <SecondaryButton label={field.type === 'photo_collection' ? 'Add photo' : 'Attach image'} onPress={() => onAddEvidence('library')} />
        </View>
        {field.type === 'file_collection' ? (
          <>
            <View style={styles.buttonGap} />
            <SecondaryButton label="Attach PDF/document" onPress={() => onAddEvidence('document')} />
          </>
        ) : null}
      </View>
    );
  }

  if (field.type === 'signature') return null;

  return (
    <View style={styles.fieldCard}>
      <Text style={styles.fieldLabel}>{field.label}{requiredMarker}</Text>
      <TextInput
        style={styles.input}
        value={value === undefined || value === null ? '' : String(value)}
        onChangeText={(next) => onAnswer(field.key, next)}
      />
    </View>
  );
}

function SignatureBox({ value, onChange }: { value: string; onChange: (value: string) => void }) {
  const [points, setPoints] = useState<Array<{ x: number; y: number }>>(() => parseSignature(value));

  const responder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: () => true,
        onPanResponderGrant: (event) => addPoint(event),
        onPanResponderMove: (event) => addPoint(event),
        onPanResponderRelease: () => {
          onChange(JSON.stringify({ captured_at: new Date().toISOString(), points }));
        },
      }),
    [points, onChange]
  );

  function addPoint(event: GestureResponderEvent) {
    const point = {
      x: Math.max(0, Math.min(300, event.nativeEvent.locationX)),
      y: Math.max(0, Math.min(110, event.nativeEvent.locationY)),
    };
    setPoints((current) => {
      const next = [...current, point].slice(-240);
      onChange(JSON.stringify({ captured_at: new Date().toISOString(), points: next }));
      return next;
    });
  }

  function clear() {
    setPoints([]);
    onChange('');
  }

  return (
    <>
      <View style={styles.signatureBox} {...responder.panHandlers}>
        {points.length === 0 ? <Text style={styles.signatureHint}>Sign inside this box</Text> : null}
        {points.map((point, index) => (
          <View key={`${point.x}-${point.y}-${index}`} style={[styles.signatureDot, { left: point.x, top: point.y }]} />
        ))}
      </View>
      <View style={styles.rowBetween}>
        <Text style={styles.metaText}>{points.length > 0 ? 'Signature captured' : 'Required before submission'}</Text>
        <Pressable onPress={clear}>
          <Text style={styles.linkText}>Clear</Text>
        </Pressable>
      </View>
    </>
  );
}

function ReviewScreen({
  form,
  draft,
  validation,
  onBack,
  onFix,
  onFinalize,
}: {
  form: FormSchema;
  draft: DraftAssessment;
  validation: ValidationResult;
  onBack: () => void;
  onFix: (sectionIndex: number) => void;
  onFinalize: () => void;
}) {
  const hasIssues = validation.errors.length > 0;
  const scorePercent = validation.score.percent ?? (
    validation.score.max && validation.score.max > 0
      ? Math.round((validation.score.total / validation.score.max) * 100)
      : null
  );
  const scorePreview = validation.score.fields.slice(0, 4);

  return (
    <>
      <View style={styles.reviewHeaderCard}>
        <Text style={styles.reviewEyebrow}>Final check</Text>
        <Text style={styles.screenTitle}>Review and finalize</Text>
        <Text style={styles.muted}>Confirm the assessment is complete before sending it to DataKit.</Text>
      </View>

      <View style={styles.reviewFacilityCard}>
        <View style={styles.rowBetween}>
          <View style={styles.flexOne}>
            <Text style={styles.reviewFacilityName}>{draft.facility.name}</Text>
            <Text style={styles.muted}>{draft.formTitle}</Text>
            <Text style={styles.metaText}>{draft.facility.lga ?? 'Unknown LGA'} - {draft.facility.ward ?? 'Unknown ward'}</Text>
          </View>
          <StatusPill label={hasIssues ? 'Needs attention' : 'Ready'} tone={hasIssues ? 'warning' : 'success'} />
        </View>
      </View>

      <View style={styles.reviewStatsGrid}>
        <View style={styles.reviewStatCard}>
          <View style={styles.reviewStatIcon}>
            <Feather name="check-circle" size={17} color={colors.green800} />
          </View>
          <Text style={styles.metricValue}>{validation.completedSections}/{form.sections.length}</Text>
          <Text style={styles.metricLabel}>Sections complete</Text>
        </View>
        <View style={styles.reviewStatCard}>
          <View style={[styles.reviewStatIcon, hasIssues && styles.reviewStatIconWarning]}>
            <Feather name={hasIssues ? 'alert-triangle' : 'shield'} size={17} color={hasIssues ? colors.amber700 : colors.green800} />
          </View>
          <Text style={styles.metricValue}>{validation.errors.length}</Text>
          <Text style={styles.metricLabel}>Issues to fix</Text>
        </View>
      </View>

      {form.requires_scoring ? (
        <View style={styles.scoreCard}>
          <View style={styles.rowBetween}>
            <View style={styles.flexOne}>
              <Text style={styles.scoreTitle}>Assessment score</Text>
              <Text style={styles.muted}>{validation.score.fields.length} scored answer(s)</Text>
            </View>
            <View style={styles.scoreBadge}>
              <Text style={styles.scoreValue}>
                {formatScore(validation.score.total)}{validation.score.max !== null ? ` / ${formatScore(validation.score.max)}` : ''}
              </Text>
              {scorePercent !== null ? <Text style={styles.scorePercent}>{scorePercent}%</Text> : null}
            </View>
          </View>
          {scorePercent !== null ? (
            <View style={styles.scoreTrack}>
              <View style={[styles.scoreBar, { width: `${Math.max(0, Math.min(scorePercent, 100))}%` }]} />
            </View>
          ) : null}
          {scorePreview.map((item) => (
            <View key={item.field} style={styles.scoreRow}>
              <Text numberOfLines={1} style={styles.scoreItemLabel}>{item.label}</Text>
              <Text style={styles.scoreRowValue}>{formatScore(item.score)}{item.max_score !== null ? `/${formatScore(item.max_score)}` : ''}</Text>
            </View>
          ))}
          {validation.score.fields.length > scorePreview.length ? (
            <Text style={styles.metaText}>+ {validation.score.fields.length - scorePreview.length} more scored answer(s)</Text>
          ) : null}
        </View>
      ) : null}

      <View style={styles.reviewSummaryCard}>
        <View style={styles.rowBetween}>
          <Text style={styles.sectionTitle}>Checklist summary</Text>
          <StatusPill label={`${validation.completedSections}/${form.sections.length}`} tone={hasIssues ? 'warning' : 'success'} />
        </View>
        {form.sections.map((section, index) => {
          const sectionErrors = validation.errors.filter((error) => error.sectionKey === section.key);
          const sectionOk = sectionErrors.length === 0;
          return (
            <Pressable key={section.key} style={styles.reviewRow} onPress={() => onFix(index)}>
              <View style={[styles.reviewStatus, !sectionOk && styles.reviewStatusWarning]}>
                <Text style={[styles.reviewStatusText, !sectionOk && styles.reviewStatusWarningText]}>
                  {sectionOk ? 'OK' : '!'}
                </Text>
              </View>
              <View style={styles.flexOne}>
                <Text style={styles.assessmentTitle}>{section.label}</Text>
                <Text style={styles.muted}>{sectionOk ? 'Completed' : `${sectionErrors.length} item(s) need attention`}</Text>
              </View>
              <Feather name="chevron-right" size={18} color={colors.slate500} />
            </Pressable>
          );
        })}
      </View>

      {hasIssues ? (
        <View style={styles.warningCard}>
          <View style={styles.rowBetween}>
            <Text style={styles.warningTitle}>{validation.errors.length} items need attention</Text>
            <Feather name="alert-triangle" size={18} color={colors.amber700} />
          </View>
          {validation.errors.slice(0, 8).map((error) => (
            <Pressable key={error.fieldKey} style={styles.errorRow} onPress={() => onFix(error.sectionIndex)}>
              <Text style={styles.errorText}>{error.label}</Text>
              <Text style={styles.metaText}>{error.message}</Text>
            </Pressable>
          ))}
        </View>
      ) : (
        <View style={styles.readySubmitCard}>
          <Feather name="check-circle" size={20} color={colors.green800} />
          <View style={styles.flexOne}>
            <Text style={styles.readySubmitTitle}>Ready to submit</Text>
            <Text style={styles.muted}>No validation issues were found. Finalize when the assessment is complete.</Text>
          </View>
        </View>
      )}

      <View style={styles.bottomActions}>
        <SecondaryButton label="Edit answers" onPress={onBack} />
        <PrimaryButton label={hasIssues ? 'Fix answers' : 'Finalize'} onPress={hasIssues ? onBack : onFinalize} compact />
      </View>
    </>
  );
}

function SyncScreen({
  queue,
  history,
  syncing,
  lastMessage,
  onSync,
  onRetry,
}: {
  queue: QueueItem[];
  history: HistoryItem[];
  syncing: boolean;
  lastMessage: string;
  onSync: () => void;
  onRetry: (itemId: string) => void;
}) {
  return (
    <>
      <Text style={styles.screenTitle}>Sync centre</Text>
      <View style={styles.syncStatusPanel}>
        <View style={styles.syncStatusIcon}>
          <Feather name={queue.length > 0 ? 'clock' : 'check-circle'} size={20} color={colors.green800} />
        </View>
        <View style={styles.flexOne}>
          <Text style={styles.syncStatusTitle}>{queue.length > 0 ? `${queue.length} assessment${queue.length === 1 ? '' : 's'} waiting` : 'All caught up'}</Text>
          <Text style={styles.muted}>{lastMessage}</Text>
        </View>
      </View>
      <View style={styles.syncSummaryRow}>
        <View style={styles.syncSummaryItem}>
          <Text style={styles.syncSummaryValue}>{queue.length}</Text>
          <Text style={styles.syncSummaryLabel}>Pending</Text>
        </View>
        <View style={styles.syncSummaryItem}>
          <Text style={styles.syncSummaryValue}>{queue.filter((item) => item.status === 'failed').length}</Text>
          <Text style={styles.syncSummaryLabel}>Failed</Text>
        </View>
        <View style={styles.syncSummaryItem}>
          <Text style={styles.syncSummaryValue}>{history.length}</Text>
          <Text style={styles.syncSummaryLabel}>Synced</Text>
        </View>
      </View>
      <PrimaryButton icon="refresh-cw" label={syncing ? 'Syncing...' : `Sync now (${queue.length})`} onPress={onSync} disabled={syncing || queue.length === 0} />

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Upload queue</Text>
      </View>
      {queue.map((item) => (
        <View key={item.id} style={styles.card}>
          <View style={styles.rowBetween}>
            <View style={styles.flexOne}>
              <Text style={styles.cardTitle}>{item.draft.facility.name}</Text>
              <Text style={styles.muted}>{item.draft.assessmentId}</Text>
            </View>
            <StatusPill label={item.status} tone={item.status === 'failed' ? 'danger' : 'warning'} />
          </View>
          {item.lastError ? <Text style={styles.errorText}>{item.lastError}</Text> : null}
          <Text style={styles.metaText}>
            Attempt {item.attempts}{item.lastAttemptAt ? ` - Last tried ${formatTime(item.lastAttemptAt)}` : ''}
          </Text>
          {item.remoteUuid ? <Text style={styles.metaText}>Remote submission: {item.remoteUuid}</Text> : null}
          {item.draft.attachments.length > 0 || Object.keys(item.attachmentStatus ?? {}).length > 0 ? (
            <View style={styles.attachmentStatusBox}>
              <View style={styles.rowBetween}>
                <Text style={styles.attachmentStatusTitle}>Evidence</Text>
                <Text style={styles.metaText}>{attachmentStatusCount(item)} file{attachmentStatusCount(item) === 1 ? '' : 's'}</Text>
              </View>
              {(['uploaded', 'pending', 'failed'] as const).map((status) => {
                const count = attachmentStatusCount(item, status);
                if (count === 0) return null;
                return (
                  <View key={status} style={styles.attachmentStatusRow}>
                    <Text style={styles.attachmentStatusLabel}>{status === 'uploaded' ? 'Uploaded' : status === 'pending' ? 'Pending' : 'Failed'}</Text>
                    <StatusPill label={String(count)} tone={status === 'failed' ? 'danger' : status === 'uploaded' ? 'success' : 'warning'} />
                  </View>
                );
              })}
              {Object.entries(item.attachmentStatus ?? {})
                .filter(([, attachment]) => attachment.status === 'failed')
                .map(([attachmentId, attachment]) => {
                  const failedFile = item.draft.attachments.find((candidate) => candidate.id === attachmentId);
                  return (
                    <Text key={attachmentId} style={styles.errorText}>
                      {failedFile?.name ?? 'Evidence file'}: {attachment.error ?? 'Upload failed.'}
                    </Text>
                  );
                })}
            </View>
          ) : null}
          {item.status === 'failed' ? (
            <View style={styles.retryAction}>
              <SecondaryButton label="Retry this upload" onPress={() => onRetry(item.id)} disabled={syncing} />
            </View>
          ) : null}
        </View>
      ))}
      {queue.length === 0 && <EmptyState label="No pending uploads." />}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Synced assessments</Text>
      </View>
      {history.slice(0, 10).map((item) => (
        <AssessmentRow
          key={`${item.id}-${item.syncedAt}`}
          title={item.facilityName}
          subtitle={`${item.assessmentId} - ${formatTime(item.syncedAt)}${item.attachmentsUploaded !== undefined ? ` - ${item.attachmentsUploaded} evidence file(s)` : ''}`}
          status="Submitted"
        />
      ))}
    </>
  );
}

function AssessmentsScreen({
  drafts,
  queue,
  history,
  onOpenDraft,
}: {
  drafts: DraftAssessment[];
  queue: QueueItem[];
  history: HistoryItem[];
  onOpenDraft: (draftId: string) => void;
}) {
  return (
    <>
      <Text style={styles.screenTitle}>Assessment history</Text>
      <Text style={styles.muted}>{drafts.length + queue.length + history.length} assessments on this device</Text>
      {drafts.map((draft) => (
        <AssessmentRow
          key={draft.id}
          title={draft.facility.name}
          subtitle={`${draft.assessmentId} - ${formatTime(draft.updatedAt)}`}
          status="Draft"
          onPress={() => onOpenDraft(draft.id)}
        />
      ))}
      {queue.map((item) => (
        <AssessmentRow
          key={item.id}
          title={item.draft.facility.name}
          subtitle={`${item.draft.assessmentId} - queued ${formatTime(item.queuedAt)}`}
          status={item.status}
        />
      ))}
      {history.map((item) => (
        <AssessmentRow
          key={`${item.id}-${item.syncedAt}`}
          title={item.facilityName}
          subtitle={`${item.assessmentId} - synced ${formatTime(item.syncedAt)}`}
          status="Submitted"
        />
      ))}
      {drafts.length + queue.length + history.length === 0 && <EmptyState label="No assessments yet." />}
    </>
  );
}

function Brand({ large = false }: { large?: boolean }) {
  return <DataKitLogo large={large} />;
}

function MetricTile({ value, label, icon }: { value: string; label: string; icon?: keyof typeof Feather.glyphMap }) {
  return (
    <View style={styles.metricTile}>
      {icon ? (
        <View style={styles.metricIcon}>
          <Feather name={icon} size={15} color={colors.green800} />
        </View>
      ) : null}
      <Text style={styles.metricValue}>{value}</Text>
      <Text style={styles.metricLabel}>{label}</Text>
    </View>
  );
}

function PrimaryButton({
  label,
  onPress,
  disabled = false,
  compact = false,
  icon,
}: {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  compact?: boolean;
  icon?: keyof typeof Feather.glyphMap;
}) {
  return (
    <Pressable style={[styles.primaryButton, compact && styles.compactButton, disabled && styles.disabledButton]} onPress={onPress} disabled={disabled}>
      {icon ? <Feather name={icon} size={16} color={colors.white} /> : null}
      <Text style={styles.primaryButtonText}>{label}</Text>
    </Pressable>
  );
}

function SecondaryButton({
  label,
  onPress,
  disabled = false,
  icon,
}: {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  icon?: keyof typeof Feather.glyphMap;
}) {
  return (
    <Pressable style={[styles.secondaryButton, disabled && styles.disabledSecondaryButton]} onPress={onPress} disabled={disabled}>
      {icon ? <Feather name={icon} size={16} color={colors.green800} /> : null}
      <Text style={styles.secondaryButtonText}>{label}</Text>
    </Pressable>
  );
}

function FilterChip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable style={[styles.filterChip, active && styles.filterChipActive]} onPress={onPress}>
      <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{label}</Text>
    </Pressable>
  );
}

function StatusPill({ label, tone }: { label: string; tone: 'success' | 'warning' | 'danger' }) {
  return (
    <View style={[styles.statusPill, tone === 'warning' && styles.statusWarning, tone === 'danger' && styles.statusDanger]}>
      <Text style={[styles.statusPillText, tone === 'warning' && styles.statusWarningText, tone === 'danger' && styles.statusDangerText]}>
        {label}
      </Text>
    </View>
  );
}

function AssessmentRow({
  title,
  subtitle,
  status,
  onPress,
}: {
  title: string;
  subtitle: string;
  status: string;
  onPress?: () => void;
}) {
  return (
    <Pressable style={styles.assessmentRow} onPress={onPress} disabled={!onPress}>
      <View style={styles.flexOne}>
        <Text style={styles.assessmentTitle}>{title}</Text>
        <Text style={styles.muted}>{subtitle}</Text>
      </View>
      <StatusPill label={status} tone={status === 'failed' ? 'danger' : status === 'Draft' ? 'warning' : 'success'} />
    </Pressable>
  );
}

function EmptyState({ label }: { label: string }) {
  return (
    <View style={styles.emptyState}>
      <Text style={styles.muted}>{label}</Text>
    </View>
  );
}

type ValidationError = {
  fieldKey: string;
  label: string;
  message: string;
  sectionKey: string;
  sectionIndex: number;
};

function validateDraft(form: FormSchema, draft: DraftAssessment): ValidationResult {
  const errors: ValidationError[] = [];

  form.fields.forEach((field) => {
    if (field.type === 'signature') {
      return;
    }

    if (!isFieldVisible(field, draft.answers)) {
      return;
    }

    const requiredByEvidence = form.required_evidence_rules?.some((rule) => rule.field === field.key && rule.required_on_submit) ?? false;
    const required = field.required || requiredByEvidence || isRequiredByLogic(field, draft.answers);
    const sectionIndex = sectionIndexForField(form, field);
    const value = draft.answers[field.key];

    if (field.type === 'yes_no_with_count') {
      const compound = normalizeCompoundAnswer(value);
      if (required && isBlankAnswer(compound.answer)) {
        pushValidationError(errors, field, sectionIndex, 'Required answer missing');
      }

      if (compound.answer === (field.count_required_value ?? 'yes')) {
        if (isBlankAnswer(compound.count)) {
          pushValidationError(errors, field, sectionIndex, 'Required number missing', field.count_label ?? field.label);
        } else {
          validateRules(field.count_rules ?? ['integer', `min:${field.count_minimum ?? 1}`], field.count_label ?? field.label, compound.count, 'numeric')
            .forEach((message) => pushValidationError(errors, field, sectionIndex, message, field.count_label ?? field.label));
        }
      }

      return;
    }

    if (required && isBlankAnswer(value)) {
      if (field.type === 'gps') {
        pushValidationError(errors, field, sectionIndex, 'GPS location not captured');
      } else {
        pushValidationError(errors, field, sectionIndex, 'Required answer missing');
      }
    }

    if (shouldValidateFieldRules(field)) {
      validateFieldValue(field, value).forEach((message) => pushValidationError(errors, field, sectionIndex, message));
    }

    if (field.type === 'photo_collection' || field.type === 'file_collection') {
      validateCollectionLimits(field, value).forEach((message) => pushValidationError(errors, field, sectionIndex, message));
    }

    if (field.type === 'gps' && required && !isBlankAnswer(value) && !hasGpsValue(value)) {
      pushValidationError(errors, field, sectionIndex, 'GPS location not captured');
    }

  });

  const completedSections = form.sections.filter((section) => {
    const sectionErrors = errors.filter((error) => error.sectionKey === section.key);
    return sectionErrors.length === 0;
  }).length;

  return { errors, completedSections, score: calculateScore(form, draft.answers) };
}

function emptyValidationResult(): ValidationResult {
  return {
    errors: [],
    completedSections: 0,
    score: { total: 0, max: null, percent: null, fields: [] },
  };
}

function sectionIndexForField(form: FormSchema, field: FormField): number {
  return Math.max(form.sections.findIndex((section) => section.key === field.section), 0);
}

function pushValidationError(
  errors: ValidationError[],
  field: FormField,
  sectionIndex: number,
  message: string,
  label = field.label
) {
  errors.push({
    fieldKey: field.key,
    label,
    message,
    sectionKey: field.section,
    sectionIndex,
  });
}

function validateFieldValue(field: FormField, value: unknown): string[] {
  const rules = new Set([...(field.rules ?? [])]);
  let valueKind: 'numeric' | 'string' | 'date' = 'string';

  if (field.type === 'number' || field.type === 'currency') {
    rules.add('numeric');
    valueKind = 'numeric';
  }
  if (field.type === 'date') {
    rules.add('date');
    valueKind = 'date';
  }
  if (field.type === 'checkbox' && field.required) {
    rules.add('accepted');
  }

  return validateRules(Array.from(rules), field.label, value, valueKind);
}

function shouldValidateFieldRules(field: FormField): boolean {
  if (field.readonly || field.type === 'readonly') {
    return false;
  }

  if (field.type === 'reference_select' && !field.options?.length) {
    return false;
  }

  return !['gps', 'signature', 'photo_collection', 'file_collection'].includes(field.type);
}

function validateRules(
  rules: string[],
  label: string,
  value: unknown,
  valueKind: 'numeric' | 'string' | 'date' = 'string'
): string[] {
  if (isBlankAnswer(value)) {
    return [];
  }

  const errors: string[] = [];
  rules.forEach((rule) => {
    const [name, argument] = rule.split(':', 2);
    const textValue = typeof value === 'string' ? value : String(value);
    const numeric = Number(value);

    if (name === 'integer' && !Number.isInteger(numeric)) {
      errors.push(`${label} must be a whole number`);
    }

    if (name === 'numeric' && !Number.isFinite(numeric)) {
      errors.push(`${label} must be a number`);
    }

    if (name === 'date' && Number.isNaN(Date.parse(textValue))) {
      errors.push(`${label} must be a valid date`);
    }

    if (name === 'before_or_equal' && argument === 'today') {
      const entered = new Date(textValue);
      const today = new Date();
      entered.setHours(0, 0, 0, 0);
      today.setHours(0, 0, 0, 0);
      if (Number.isNaN(entered.getTime()) || entered.getTime() > today.getTime()) {
        errors.push(`${label} must be today or earlier`);
      }
    }

    if (name === 'min' && argument !== undefined) {
      if (valueKind === 'numeric' && Number.isFinite(numeric) && numeric < Number(argument)) {
        errors.push(`${label} must be at least ${argument}`);
      } else if (valueKind !== 'numeric' && textValue.length < Number(argument)) {
        errors.push(`${label} must be at least ${argument} characters`);
      }
    }

    if (name === 'max' && argument !== undefined) {
      if (valueKind !== 'numeric' && textValue.length > Number(argument)) {
        errors.push(`${label} must be ${argument} characters or fewer`);
      } else if (valueKind === 'numeric' && Number.isFinite(numeric) && numeric > Number(argument)) {
        errors.push(`${label} must be ${argument} or less`);
      }
    }

    if (name === 'accepted' && value !== true && value !== 'yes' && value !== '1' && value !== 1) {
      errors.push(`${label} must be accepted`);
    }

    if (name === 'array' && !Array.isArray(value)) {
      errors.push(`${label} must contain one or more items`);
    }
  });

  return unique(errors);
}

function validateCollectionLimits(field: FormField, value: unknown): string[] {
  const count = Array.isArray(value) ? value.length : 0;
  const errors: string[] = [];

  if (field.min_items !== undefined && count < field.min_items) {
    errors.push(`Add at least ${field.min_items} item(s)`);
  }

  if (field.max_items !== undefined && count > field.max_items) {
    errors.push(`Remove items until there are ${field.max_items} or fewer`);
  }

  return errors;
}

function hasGpsValue(value: unknown): boolean {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return false;
  }

  const record = value as Partial<GpsValue>;
  return typeof record.latitude === 'number' && typeof record.longitude === 'number';
}

function hasSignatureValue(value: unknown): boolean {
  if (typeof value !== 'string' || value.trim() === '') {
    return false;
  }

  return parseSignature(value).length > 0;
}

function calculateScore(form: FormSchema, answers: Record<string, unknown>): ScoreResult {
  if (!form.requires_scoring || !Array.isArray(form.scoring_rules)) {
    return { total: 0, max: null, percent: null, fields: [] };
  }

  let total = 0;
  let inferredMax = 0;
  const fields: ScoreResult['fields'] = [];

  form.scoring_rules.forEach((rule) => {
    if (rule.type !== 'option_score' || !rule.field) {
      return;
    }

    const options = rule.options ?? [];
    const maxScore = options.reduce<number | null>((max, option) => {
      if (option.score === undefined || option.score === null) return max;
      return max === null ? Number(option.score) : Math.max(max, Number(option.score));
    }, null);
    inferredMax += maxScore ?? 0;

    const answer = answerComparableValue(answers[rule.field]);
    const option = options.find((item) => valuesMatch(answer, item.value));
    if (!option || option.score === undefined || option.score === null) {
      return;
    }

    const score = Number(option.score);
    total += score;
    const field = form.fields.find((item) => item.key === rule.field);
    fields.push({
      field: rule.field,
      label: field?.label ?? rule.field,
      answer,
      score,
      max_score: maxScore,
    });
  });

  const max = form.scoring_max_score ?? (inferredMax > 0 ? inferredMax : null);
  const percent = max && max > 0 ? Math.round((total / max) * 100) : null;

  return { total, max, percent, fields };
}

function fieldsForSection(form: FormSchema, sectionKey: string, answers: Record<string, unknown>): FormField[] {
  return form.fields
    .filter((field) => field.section === sectionKey)
    .filter((field) => isFieldVisible(field, answers));
}

function buildSectionRenderItems(fields: FormField[]): SectionRenderItem[] {
  const items: SectionRenderItem[] = [];
  let currentGroup = '';

  fields.forEach((field) => {
    const group = field.group?.trim() ?? '';
    if (group && group !== currentGroup) {
      currentGroup = group;
      items.push({ type: 'group', key: `group-${slugify(group)}-${items.length}`, label: group });
    }

    if (!group) {
      currentGroup = '';
    }

    items.push({ type: 'field', key: field.key, field });
  });

  return items;
}

function isFieldVisible(field: FormField, answers: Record<string, unknown>): boolean {
  if (!field.visible_if) {
    return true;
  }

  return evaluateCondition(field.visible_if, answers);
}

function isRequiredByLogic(field: FormField, answers: Record<string, unknown>): boolean {
  if (!field.required_if) {
    return false;
  }

  return Object.entries(field.required_if).every(([key, expected]) => valuesMatch(answerComparableValue(answers[key]), expected));
}

function evaluateCondition(condition: LogicCondition, answers: Record<string, unknown>): boolean {
  if (!condition.field) {
    return true;
  }

  const actual = answerComparableValue(answers[condition.field]);
  const operator = condition.operator ?? 'equals';

  if (operator === 'not_equals') {
    return !valuesMatch(actual, condition.value);
  }

  if (operator === 'in') {
    return (condition.values ?? []).some((value) => valuesMatch(actual, value));
  }

  if (operator === 'not_in') {
    return !(condition.values ?? []).some((value) => valuesMatch(actual, value));
  }

  return valuesMatch(actual, condition.value);
}

function answerComparableValue(value: unknown): unknown {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const record = value as Record<string, unknown>;
    if ('answer' in record) {
      return record.answer;
    }
    if ('value' in record) {
      return record.value;
    }
  }

  return value;
}

function valuesMatch(actual: unknown, expected: unknown): boolean {
  if (actual === expected) {
    return true;
  }

  if (actual === undefined || actual === null || expected === undefined || expected === null) {
    return false;
  }

  return String(actual) === String(expected);
}

function isBlankAnswer(value: unknown): boolean {
  if (value === undefined || value === null) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim().length === 0;
  }

  if (Array.isArray(value)) {
    return value.length === 0;
  }

  if (typeof value === 'boolean') {
    return value !== true;
  }

  if (typeof value === 'object') {
    return Object.keys(value as Record<string, unknown>).length === 0;
  }

  return false;
}

function draftFromReturnedAssignment(assignment: MobileAssignment, availableForms: FormSchema[]): DraftAssessment | null {
  const submission = assignment.submission;
  const facility = assignment.facility;

  if (!submission || submission.status !== 'returned' || !facility) {
    return null;
  }

  const form = formForFacility(facility, availableForms) ?? availableForms[0] ?? fallbackForm;
  const formSnapshot = cloneFormSchema(form);
  const answers = { ...(submission.answers ?? {}) };
  const returnedAt = submission.submitted_at ?? new Date().toISOString();
  const assessmentId = String(
    submission.assessment_id ??
      answers.assessment_id ??
      'RETURNED-' + submission.uuid.slice(0, 8).toUpperCase()
  );

  answers.assessment_id = assessmentId;
  answers.assessment_date = answers.assessment_date ?? returnedAt.slice(0, 10);
  answers.assessor_id = answers.assessor_id ?? assessor.id;
  answers.facility_id = facility.id;
  answers.facility_name = facility.name;
  answers.lga_id = facility.lga_id ?? '';
  answers.ward_id = facility.ward_id ?? '';
  answers.assessment_exercise_id = facility.exercise_id ?? '';
  answers.assessment_assignment_id = facility.assignment_id ?? '';

  return {
    id: 'returned-' + submission.uuid,
    clientSubmissionId: submission.client_submission_id ?? undefined,
    reviewNote: submission.review_note ?? null,
    assessmentId,
    formSlug: formSnapshot.slug,
    formTitle: formSnapshot.title,
    formVersion: formSnapshot.version,
    formVersionId: formSnapshot.version_id,
    assessmentExerciseId: facility.exercise_id,
    assessmentExerciseUuid: facility.exercise_uuid,
    assessmentAssignmentId: facility.assignment_id,
    assignmentUuid: facility.assignment_uuid,
    exerciseName: facility.exercise_name,
    exerciseStatus: facility.exercise_status,
    exerciseStartsAt: facility.exercise_starts_at,
    exerciseEndsAt: facility.exercise_ends_at,
    formSchema: formSnapshot,
    facility,
    assessor,
    answers,
    attachments: [],
    currentSectionIndex: 0,
    startedAt: returnedAt,
    updatedAt: new Date().toISOString(),
  };
}

function assignmentsToFacilities(assignments: MobileAssignment[], legacyFacilities: Facility[]): Facility[] {
  const normalized = assignments.map((assignment): Facility | null => {
    if (assignment.facility) {
      return assignment.facility;
    }

    const target = assignment.target;
    const exercise = assignment.exercise;
    if (target) {
      return {
        id: target.id,
        code: target.external_id,
        name: target.name,
        type: target.type,
        target_type: assignment.target_type || target.type,
        assignment_id: assignment.id,
        assignment_uuid: assignment.uuid,
        assignment_status: assignment.status,
        assignment_due_at: assignment.due_at,
        allow_duplicate_submission: assignment.allow_duplicate_submission,
        exercise_id: exercise?.id,
        exercise_uuid: exercise?.uuid,
        exercise_name: exercise?.name,
        exercise_code: exercise?.code,
        exercise_status: exercise?.status,
        exercise_starts_at: exercise?.starts_at,
        exercise_ends_at: exercise?.ends_at,
        form_slug: exercise?.form_slug,
        form_version_id: exercise?.form_version_id,
      };
    }

    if (assignment.target_type === 'no_target') {
      return {
        id: `assignment-${assignment.id}`,
        name: 'Unspecified target',
        target_type: 'no_target',
        assignment_id: assignment.id,
        assignment_uuid: assignment.uuid,
        assignment_status: assignment.status,
        assignment_due_at: assignment.due_at,
        allow_duplicate_submission: assignment.allow_duplicate_submission,
        exercise_id: exercise?.id,
        exercise_uuid: exercise?.uuid,
        exercise_name: exercise?.name,
        exercise_code: exercise?.code,
        exercise_status: exercise?.status,
        form_slug: exercise?.form_slug,
        form_version_id: exercise?.form_version_id,
      };
    }

    return null;
  }).filter((facility): facility is Facility => facility !== null);

  return normalized.length > 0 ? normalized : legacyFacilities;
}

function mapPublishedForm(record: PublishedFormResponse['data'][number]): FormSchema | null {
  const version = record.current_version;
  if (!version) {
    return null;
  }

  const downloadedAt = new Date().toISOString();
  const requiresScoring = Boolean(record.requires_scoring ?? version.requires_scoring ?? version.metadata_schema?.requires_scoring);
  const scoringMaxScore = toNullableNumber(record.scoring_max_score ?? version.scoring_max_score ?? version.metadata_schema?.scoring_max_score);
  const fields = normalizeFields(version.fields ?? []);

  return {
    id: record.id,
    slug: record.slug,
    title: record.title,
    description: record.description,
    version: Number(version.version),
    version_id: version.id,
    metadata_schema: version.metadata_schema ?? null,
    requires_scoring: requiresScoring,
    scoring_max_score: requiresScoring ? scoringMaxScore : null,
    sections: normalizeSections(version.sections ?? []).filter((section) => fields.some((field) => field.section === section.key)),
    fields,
    validation_rules: version.validation_rules,
    visibility_rules: version.visibility_rules,
    scoring_rules: version.scoring_rules,
    required_evidence_rules: version.required_evidence_rules,
    signature_rules: [],
    downloaded_at: downloadedAt,
  };
}

function normalizeSections(sections: FormSection[]): FormSection[] {
  return [...sections]
    .filter((section) => section.key && section.label)
    .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
}

function normalizeFields(fields: FormField[]): FormField[] {
  return [...fields]
    .filter((field) => field.key && field.label && field.type && field.type !== 'signature')
    .map((field) => ({
      ...field,
      section: field.section || 'assessment_metadata',
      required: Boolean(field.required),
      readonly: Boolean(field.readonly),
      options: field.options?.map((option) => ({
        label: option.label,
        value: option.value,
        score: option.score === undefined || option.score === null ? null : Number(option.score),
        needs_review: Boolean(option.needs_review),
      })),
    }));
}

function buildFormCacheMeta(forms: FormSchema[], facilitiesCount: number, assignmentsCount = 0, exercisesCount = 0): FormCacheMeta {
  return {
    downloaded_at: new Date().toISOString(),
    api_base_url: API_BASE_URL,
    forms_count: forms.length,
    facilities_count: facilitiesCount,
    assignments_count: assignmentsCount,
    exercises_count: exercisesCount,
    form_versions: forms.map((form) => ({
      slug: form.slug,
      title: form.title,
      version: form.version,
      version_id: form.version_id,
      fields_count: form.fields.length,
      sections_count: form.sections.length,
    })),
  };
}

function uniqueExercisesFromFacilities(facilities: Facility[]): MobileExercise[] {
  const byId = new Map<number, MobileExercise>();
  facilities.forEach((facility) => {
    if (!facility.exercise_id || byId.has(facility.exercise_id)) {
      return;
    }

    byId.set(facility.exercise_id, {
      id: facility.exercise_id,
      uuid: facility.exercise_uuid ?? String(facility.exercise_id),
      name: facility.exercise_name ?? facility.exercise_code ?? `Exercise ${facility.exercise_id}`,
      code: facility.exercise_code,
      status: facility.exercise_status ?? 'assigned',
      starts_at: facility.exercise_starts_at,
      ends_at: facility.exercise_ends_at,
      form_slug: facility.form_slug,
      form_version_id: facility.form_version_id,
    });
  });

  return Array.from(byId.values()).sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '') || a.name.localeCompare(b.name));
}

function hydrateDrafts(drafts: DraftAssessment[], availableForms: FormSchema[]): DraftAssessment[] {
  return drafts.map((draft) => {
    if (draft.formSchema) {
      return {
        ...draft,
        formSchema: cloneFormSchema(draft.formSchema),
      };
    }

    const recoveredForm = formForDraft(draft, availableForms) ?? availableForms[0] ?? fallbackForm;

    return {
      ...draft,
      formSchema: cloneFormSchema(recoveredForm),
      formSlug: draft.formSlug || recoveredForm.slug,
      formTitle: draft.formTitle || recoveredForm.title,
      formVersion: draft.formVersion || recoveredForm.version,
      formVersionId: draft.formVersionId ?? recoveredForm.version_id,
    };
  });
}

function hydrateQueue(queue: QueueItem[]): QueueItem[] {
  return queue.map((item) => ({
    ...item,
    status: item.status === 'syncing' ? 'pending' : item.status,
    attachmentStatus: {
      ...initialAttachmentStatus(item.draft),
      ...(item.attachmentStatus ?? {}),
    },
  }));
}

function formForDraft(draft: DraftAssessment, forms: FormSchema[]): FormSchema | null {
  return forms.find((form) => {
    if (draft.formVersionId && form.version_id) {
      return form.slug === draft.formSlug && form.version_id === draft.formVersionId;
    }

    return form.slug === draft.formSlug && form.version === draft.formVersion;
  }) ?? forms.find((form) => form.slug === draft.formSlug) ?? null;
}

function formForFacility(facility: Facility, forms: FormSchema[]): FormSchema | null {
  if (facility.form_slug && facility.form_version_id) {
    return forms.find((form) => form.slug === facility.form_slug && form.version_id === facility.form_version_id) ?? null;
  }

  if (facility.form_slug) {
    return forms.find((form) => form.slug === facility.form_slug) ?? null;
  }

  return forms[0] ?? null;
}

function cloneFormSchema(form: FormSchema): FormSchema {
  return JSON.parse(JSON.stringify(form)) as FormSchema;
}

function submissionWindowError({
  status,
  startsAt,
  endsAt,
}: {
  status?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
}): string | null {
  if (!status && !startsAt && !endsAt) {
    return null;
  }

  if (status && status !== 'active') {
    return 'This exercise is not active yet. Download updates again when the exercise opens.';
  }

  const today = new Date().toISOString().slice(0, 10);
  if (startsAt && today < startsAt) {
    return `This exercise starts on ${startsAt}.`;
  }

  if (endsAt && today > endsAt) {
    return `This exercise closed on ${endsAt}.`;
  }

  return null;
}

function schemaRuleSummary(form: FormSchema): string {
  const counts = [
    `${countArray(form.validation_rules)} validation`,
    `${countArray(form.visibility_rules)} visibility`,
    `${countArray(form.scoring_rules)} scoring`,
    `${countArray(form.required_evidence_rules)} evidence`,
  ];

  return `${counts.join(', ')} rule(s) cached.`;
}

function formsCountLabel(form: FormSchema): string {
  return `${form.sections.length} sections - ${form.fields.length} fields`;
}

function countArray(value: unknown): number {
  return Array.isArray(value) ? value.length : 0;
}

function toNullableNumber(value: unknown): number | null {
  if (value === undefined || value === null || value === '') {
    return null;
  }

  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

async function apiGet<T>(path: string): Promise<T> {
  const url = `${API_BASE_URL}${path}`;
  const token = await loadJson<string | null>(STORAGE_KEYS.authToken, null);
  const response = await fetchWithTimeout(url, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  if (!response.ok) {
    throw new Error(await responseError(response, url));
  }

  return response.json() as Promise<T>;
}

async function apiPost<T>(path: string, payload: unknown): Promise<T> {
  const url = `${API_BASE_URL}${path}`;
  const token = await loadJson<string | null>(STORAGE_KEYS.authToken, null);
  const response = await fetchWithTimeout(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    throw new Error(await responseError(response, url));
  }

  return response.json() as Promise<T>;
}

async function fetchWithTimeout(url: string, options: RequestInit = {}, timeoutMs = REQUEST_TIMEOUT_MS): Promise<Response> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, { ...options, signal: controller.signal });
  } catch (error) {
    if (error instanceof Error && error.name === 'AbortError') {
      throw new Error(`Connection timed out after ${Math.round(timeoutMs / 1000)}s while contacting ${url}.`);
    }

    throw new Error(`Cannot reach ${url}. Check the production API and your phone's internet connection.`);
  } finally {
    clearTimeout(timeout);
  }
}

async function responseError(response: Response, url: string): Promise<string> {
  let detail = '';
  try {
    const raw = await response.text();
    try {
      const json = JSON.parse(raw) as { message?: unknown; error?: unknown };
      detail = String(json.message ?? json.error ?? '').trim();
    } catch {
      detail = raw.match(/<title[^>]*>(.*?)<\/title>/i)?.[1]?.replace(/\s+/g, ' ').trim() ?? '';
    }
  } catch {
    // Keep the status and URL when the server response cannot be read.
  }

  return `Request failed (${response.status}) at ${url}${detail ? `: ${detail}` : '.'}`;
}

class SyncUploadError extends Error {
  constructor(
    message: string,
    readonly remoteUuid: string,
    readonly attachmentStatus: NonNullable<QueueItem['attachmentStatus']>
  ) {
    super(message);
    this.name = 'SyncUploadError';
  }
}

function initialAttachmentStatus(draft: DraftAssessment): NonNullable<QueueItem['attachmentStatus']> {
  return Object.fromEntries(
    draft.attachments.map((attachment) => [
      attachment.id,
      { status: 'pending' as const },
    ])
  );
}

function countUploadedAttachments(status?: QueueItem['attachmentStatus']): number {
  if (!status) {
    return 0;
  }

  return Object.values(status).filter((item) => item.status === 'uploaded').length;
}

function isRemoteSubmissionUuid(value: string | undefined): value is string {
  return Boolean(value && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value));
}

function attachmentStatusCount(item: QueueItem, status?: 'uploaded' | 'pending' | 'failed'): number {
  const values = Object.values(item.attachmentStatus ?? {});
  if (values.length === 0) {
    return status ? (status === 'pending' ? item.draft.attachments.length : 0) : item.draft.attachments.length;
  }

  return status ? values.filter((attachment) => attachment.status === status).length : values.length;
}

async function loadJson<T>(key: string, fallback: T): Promise<T> {
  try {
    const raw = await Storage.getItemAsync(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

async function saveJson<T>(key: string, value: T): Promise<void> {
  await Storage.setItemAsync(key, JSON.stringify(value));
}

function yesNoOptions(): FieldOption[] {
  return [
    { label: 'Yes', value: 'yes', score: 2 },
    { label: 'No', value: 'no', score: 0 },
  ];
}

function unique(values: string[]): string[] {
  return Array.from(new Set(values)).sort();
}

function formatTime(value: string): string {
  try {
    return new Intl.DateTimeFormat('en-NG', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function formatRoundMeta(facility: Facility): string {
  if (facility.assignment_due_at) {
    const due = new Date(facility.assignment_due_at);
    if (!Number.isNaN(due.getTime())) {
      return `Due ${new Intl.DateTimeFormat('en-NG', { day: '2-digit', month: 'short' }).format(due)} - ${facility.lga ?? 'Unknown LGA'}`;
    }
  }

  return `${facility.lga ?? 'Unknown LGA'} - ${facility.ward ?? 'Unknown ward'}`;
}

function formatScore(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function formatValue(value: unknown): string {
  if (value === undefined || value === null || value === '') {
    return 'Not set';
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value);
}

function formatFieldValue(field: FormField, value: unknown): string {
  const option = field.options?.find((item) => valuesMatch(value, item.value));
  if (option) {
    return option.label;
  }

  if (field.type === 'yes_no_with_count') {
    const compound = normalizeCompoundAnswer(value);
    const answerLabel = field.options?.find((item) => item.value === compound.answer)?.label ?? compound.answer;
    if (compound.count !== undefined && compound.count !== null && compound.count !== '') {
      return `${answerLabel ?? 'Not set'} - ${field.count_label ?? 'Total number'}: ${compound.count}`;
    }

    return answerLabel ?? 'Not set';
  }

  return formatValue(value);
}

function slugify(value: string): string {
  return value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'group';
}

function normalizeCompoundAnswer(value: unknown): { answer?: string | null; count?: string | number | null } {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const record = value as Record<string, unknown>;
    return {
      answer: typeof record.answer === 'string' ? record.answer : null,
      count: typeof record.count === 'string' || typeof record.count === 'number' ? record.count : null,
    };
  }

  if (typeof value === 'string') {
    return { answer: value, count: null };
  }

  return { answer: null, count: null };
}

function inferMimeType(filename: string): string {
  const lower = filename.toLowerCase();
  if (lower.endsWith('.pdf')) {
    return 'application/pdf';
  }
  if (lower.endsWith('.png')) {
    return 'image/png';
  }
  if (lower.endsWith('.webp')) {
    return 'image/webp';
  }

  return 'image/jpeg';
}

function parseSignature(value: string): Array<{ x: number; y: number }> {
  if (!value) {
    return [];
  }

  try {
    const parsed = JSON.parse(value) as { points?: Array<{ x: number; y: number }> };
    return parsed.points ?? [];
  } catch {
    return [];
  }
}

function signatureSvg(points: Array<{ x: number; y: number }>): string {
  const path = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
  return `<svg xmlns="http://www.w3.org/2000/svg" width="300" height="110" viewBox="0 0 300 110"><rect width="300" height="110" fill="#ffffff"/><path d="${path}" fill="none" stroke="#0b3d2a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#f5f4ee',
  },
  centerScreen: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 28,
  },
  loginScreen: {
    flex: 1,
    justifyContent: 'center',
    padding: 28,
    backgroundColor: colors.white,
  },
  appScreen: {
    flex: 1,
    backgroundColor: '#f5f4ee',
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 122,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  brandRowLarge: {
    justifyContent: 'center',
    gap: 12,
    marginBottom: 26,
  },
  brandMark: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#21c45d',
  },
  brandMarkLarge: {
    width: 48,
    height: 48,
    borderRadius: 16,
  },
  brandMarkText: {
    color: colors.white,
    fontWeight: '900',
  },
  brandMarkTextLarge: {
    fontSize: 24,
  },
  brandTitle: {
    color: colors.slate900,
    fontSize: 17,
    fontWeight: '900',
  },
  brandTitleLarge: {
    fontSize: 26,
  },
  brandSubtitle: {
    color: colors.slate600,
    fontSize: 12,
  },
  screenTitle: {
    color: colors.slate900,
    fontSize: 22,
    fontWeight: '900',
    marginBottom: 4,
  },
  welcome: {
    color: colors.slate900,
    fontSize: 24,
    fontWeight: '900',
    textAlign: 'center',
    marginBottom: 8,
  },
  muted: {
    color: colors.slate600,
    fontSize: 13,
  },
  mutedCenter: {
    color: colors.slate600,
    fontSize: 13,
    textAlign: 'center',
  },
  loadingText: {
    color: colors.slate600,
    marginTop: 14,
  },
  input: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    marginTop: 12,
    color: colors.slate900,
    backgroundColor: colors.white,
  },
  textArea: {
    minHeight: 96,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  rememberRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginVertical: 18,
  },
  rememberText: {
    color: colors.slate600,
    fontSize: 12,
  },
  linkText: {
    color: colors.green700,
    fontSize: 13,
    fontWeight: '800',
  },
  primaryButton: {
    minHeight: 50,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    backgroundColor: colors.green700,
    shadowColor: colors.green900,
    shadowOpacity: 0.18,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
    alignSelf: 'stretch',
  },
  compactButton: {
    flex: 1,
  },
  disabledButton: {
    opacity: 0.55,
  },
  primaryButtonText: {
    color: colors.white,
    fontWeight: '900',
    fontSize: 15,
  },
  secondaryButton: {
    minHeight: 48,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.green700,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    backgroundColor: colors.white,
    flex: 1,
  },
  disabledSecondaryButton: {
    opacity: 0.55,
  },
  secondaryButtonText: {
    color: colors.green800,
    fontWeight: '900',
    fontSize: 14,
  },
  buttonGap: {
    height: 10,
  },
  noticeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginTop: 16,
    padding: 16,
    borderRadius: 10,
    backgroundColor: colors.slate100,
  },
  noticeIcon: {
    color: colors.green700,
    fontWeight: '900',
  },
  noticeText: {
    flex: 1,
    color: colors.slate600,
    textAlign: 'center',
  },
  offlineBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 10,
    marginVertical: 18,
    backgroundColor: colors.amber100,
  },
  heroCard: {
    borderRadius: 8,
    padding: 18,
    marginBottom: 14,
    backgroundColor: colors.green900,
    ...shadow,
  },
  heroEyebrow: {
    color: '#b7f7cf',
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  heroTitle: {
    color: colors.white,
    fontSize: 24,
    fontWeight: '900',
    marginTop: 18,
  },
  heroSubtitle: {
    color: '#d7fbe4',
    fontSize: 13,
    marginTop: 4,
  },
  pageIntroCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 16,
    marginBottom: 14,
    ...shadow,
  },
  syncCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 8,
    marginBottom: 14,
    backgroundColor: '#fff4d7',
    borderWidth: 1,
    borderColor: '#f5d48d',
  },
  syncIcon: {
    width: 38,
    height: 38,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffe3a4',
  },
  offlineIcon: {
    color: colors.amber700,
    fontWeight: '900',
  },
  fieldTopBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#f5f4ee',
    paddingVertical: 0,
    marginBottom: 14,
  },
  workspaceSwitcher: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: colors.green100,
    borderRadius: 8,
    backgroundColor: colors.white,
    paddingHorizontal: 10,
    paddingVertical: 8,
    marginBottom: 12,
  },
  workspaceSwitcherLabel: {
    color: colors.slate600,
    fontSize: 10,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  workspaceSwitcherValue: {
    flex: 1,
    color: colors.green900,
    fontSize: 12,
    fontWeight: '900',
  },
  fieldBrandLockup: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 6,
  },
  fieldLogoMark: {
    color: colors.green900,
    fontSize: 15,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  fieldLogoSub: {
    color: colors.slate600,
    fontSize: 9,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  fieldDate: {
    color: colors.slate600,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
    marginBottom: 8,
  },
  fieldGreeting: {
    color: colors.slate900,
    fontSize: 32,
    lineHeight: 34,
    fontWeight: '900',
    marginBottom: 14,
  },
  featureWorkCard: {
    borderRadius: 8,
    backgroundColor: colors.green900,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.green900,
  },
  featureEyebrow: {
    color: colors.green100,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  featureSaved: {
    color: '#d7fbe4',
    fontSize: 10,
    fontWeight: '800',
  },
  featureTitle: {
    color: colors.white,
    fontSize: 18,
    lineHeight: 22,
    fontWeight: '900',
    marginTop: 10,
    marginBottom: 12,
  },
  featureSubtitle: {
    color: '#d7fbe4',
    fontSize: 12,
    fontWeight: '800',
    marginBottom: 8,
  },
  featureActionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: '#0b5f45',
    paddingTop: 10,
  },
  featureActionText: {
    color: colors.white,
    fontSize: 13,
    fontWeight: '900',
  },
  featureActionIcon: {
    width: 30,
    height: 30,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green700,
  },
  flexOne: {
    flex: 1,
  },
  bannerTitle: {
    color: colors.slate900,
    fontWeight: '900',
  },
  bannerText: {
    color: colors.slate600,
    fontSize: 12,
  },
  metricGrid: {
    flexDirection: 'row',
    gap: 0,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.green900,
    backgroundColor: colors.white,
  },
  metricTile: {
    flex: 1,
    borderRadius: 0,
    minHeight: 76,
    padding: 12,
    backgroundColor: colors.white,
    borderRightWidth: 1,
    borderRightColor: colors.green900,
  },
  metricIcon: {
    display: 'none',
  },
  metricValue: {
    color: colors.green900,
    fontSize: 24,
    fontWeight: '900',
  },
  metricLabel: {
    color: colors.green900,
    fontSize: 9,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  actionStack: {
    gap: 10,
    marginTop: 4,
  },
  reviewStatsGrid: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 10,
  },
  reviewStatCard: {
    flex: 1,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    ...shadow,
  },
  reviewHeaderCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 16,
    marginBottom: 10,
    ...shadow,
  },
  reviewEyebrow: {
    color: colors.green800,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  reviewFacilityCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.green100,
    backgroundColor: colors.green50,
    padding: 14,
    marginBottom: 10,
  },
  reviewFacilityName: {
    color: colors.slate900,
    fontSize: 16,
    fontWeight: '900',
    lineHeight: 21,
  },
  reviewStatIcon: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
    marginBottom: 10,
  },
  reviewStatIconWarning: {
    backgroundColor: colors.amber100,
  },
  scoreCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.green100,
    backgroundColor: colors.green50,
    padding: 16,
    marginBottom: 10,
  },
  scoreTitle: {
    color: colors.green900,
    fontSize: 15,
    fontWeight: '900',
  },
  scoreBadge: {
    alignItems: 'flex-end',
    paddingLeft: 10,
  },
  scoreValue: {
    color: colors.green900,
    fontSize: 20,
    fontWeight: '900',
  },
  scorePercent: {
    color: colors.green800,
    fontSize: 12,
    fontWeight: '900',
    marginTop: 2,
  },
  scoreTrack: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.white,
    overflow: 'hidden',
    marginTop: 12,
    marginBottom: 8,
  },
  scoreBar: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.green700,
  },
  scoreRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 10,
    paddingTop: 6,
  },
  scoreItemLabel: {
    flex: 1,
    color: colors.slate600,
    fontSize: 12,
  },
  scoreRowValue: {
    color: colors.green900,
    fontWeight: '900',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 22,
    marginBottom: 10,
  },
  sectionTitle: {
    color: colors.slate900,
    fontWeight: '900',
    fontSize: 16,
  },
  sectionHint: {
    color: colors.green800,
    fontSize: 12,
    fontWeight: '900',
  },
  roundHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: colors.green900,
    paddingBottom: 6,
    marginTop: 14,
    marginBottom: 2,
  },
  roundTitle: {
    color: colors.green900,
    fontSize: 16,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  roundHint: {
    color: colors.slate600,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  roundList: {
    borderBottomWidth: 1,
    borderBottomColor: colors.green900,
    marginBottom: 12,
  },
  roundRow: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  roundNumber: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
  },
  roundNumberText: {
    color: colors.green800,
    fontSize: 12,
    fontWeight: '900',
  },
  roundFacility: {
    color: colors.green900,
    fontSize: 13,
    fontWeight: '900',
  },
  roundMeta: {
    color: colors.green700,
    fontSize: 10,
    fontWeight: '800',
    marginTop: 2,
    textTransform: 'uppercase',
  },
  offlinePackCard: {
    borderRadius: 12,
    backgroundColor: '#dff0e5',
    padding: 14,
    marginTop: 6,
    marginBottom: 12,
  },
  offlinePackTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  offlinePackTitle: {
    color: colors.green900,
    fontSize: 13,
    fontWeight: '900',
  },
  offlinePackMeta: {
    color: colors.green800,
    fontSize: 11,
    fontWeight: '800',
    marginTop: 10,
    marginBottom: 4,
    textTransform: 'uppercase',
  },
  card: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 10,
  },
  workCard: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 10,
    ...shadow,
  },
  workIcon: {
    width: 40,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
  },
  inlineStats: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 10,
  },
  inlineStat: {
    color: colors.green800,
    fontSize: 11,
    fontWeight: '900',
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: 999,
    backgroundColor: colors.green50,
  },
  cardTitle: {
    color: colors.slate900,
    fontWeight: '900',
    fontSize: 15,
  },
  pageHeading: {
    paddingTop: 4,
    paddingBottom: 8,
  },
  metaText: {
    color: colors.slate500,
    fontSize: 12,
    marginTop: 4,
  },
  readyBox: {
    borderRadius: 8,
    padding: 12,
    marginTop: 10,
    backgroundColor: colors.green50,
  },
  readyText: {
    color: colors.green900,
    fontWeight: '900',
  },
  syncStatusPanel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingBottom: 14,
    marginTop: 8,
  },
  syncStatusIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
  },
  syncStatusTitle: {
    color: colors.slate900,
    fontSize: 15,
    fontWeight: '900',
    marginBottom: 2,
  },
  syncSummaryRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    marginBottom: 14,
  },
  syncSummaryItem: {
    flex: 1,
    paddingVertical: 12,
    borderRightWidth: 1,
    borderRightColor: colors.border,
  },
  syncSummaryValue: {
    color: colors.green900,
    fontSize: 20,
    fontWeight: '900',
  },
  syncSummaryLabel: {
    color: colors.slate500,
    fontSize: 10,
    fontWeight: '800',
    marginTop: 2,
    textTransform: 'uppercase',
  },
  readyPanel: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.green100,
    backgroundColor: colors.green50,
    padding: 14,
    marginBottom: 12,
  },
  readyIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
  },
  readyTitle: {
    color: colors.green900,
    fontSize: 15,
    fontWeight: '900',
    marginTop: 12,
    marginBottom: 4,
  },
  downloadCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 10,
    ...shadow,
  },
  downloadMetaBox: {
    borderRadius: 8,
    padding: 12,
    marginTop: 12,
    backgroundColor: colors.slate50,
    borderWidth: 1,
    borderColor: colors.slate100,
  },
  directoryCard: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginVertical: 10,
  },
  rowBetween: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  marginTop: {
    marginTop: 14,
  },
  filterLabel: {
    color: colors.slate700,
    fontWeight: '800',
    marginTop: 12,
    marginBottom: 2,
  },
  filterPanel: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 6,
    ...shadow,
  },
  chipRow: {
    gap: 8,
    paddingVertical: 8,
  },
  filterChip: {
    minHeight: 36,
    borderRadius: 18,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterChipActive: {
    backgroundColor: colors.green700,
    borderColor: colors.green700,
  },
  filterChipText: {
    color: colors.slate700,
    fontWeight: '800',
    fontSize: 12,
  },
  filterChipTextActive: {
    color: colors.white,
  },
  searchBox: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    paddingHorizontal: 12,
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.slate50,
  },
  searchInput: {
    flex: 1,
    color: colors.slate900,
    paddingVertical: 8,
  },
  facilityCard: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginTop: 10,
    ...shadow,
  },
  facilityIcon: {
    width: 42,
    height: 42,
    borderRadius: 8,
    backgroundColor: colors.green100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  facilityIconText: {
    color: colors.green800,
    fontWeight: '900',
    fontSize: 11,
  },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 9,
    paddingVertical: 5,
    backgroundColor: colors.green100,
  },
  statusPillText: {
    color: colors.green800,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'capitalize',
  },
  statusWarning: {
    backgroundColor: colors.amber100,
  },
  statusWarningText: {
    color: colors.amber700,
  },
  statusDanger: {
    backgroundColor: colors.red100,
  },
  statusDangerText: {
    color: colors.red700,
  },
  assessmentRow: {
    minHeight: 64,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  assessmentTitle: {
    color: colors.slate900,
    fontWeight: '900',
  },
  emptyState: {
    minHeight: 96,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 18,
  },
  progressTrack: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.slate100,
    marginTop: 14,
    overflow: 'hidden',
  },
  progressBar: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.green700,
  },
  versionNotice: {
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.amber700,
    backgroundColor: colors.amber100,
    marginTop: 12,
  },
  versionNoticeTitle: {
    color: colors.slate900,
    fontWeight: '900',
  },
  versionNoticeText: {
    color: colors.slate700,
    fontSize: 12,
    marginTop: 4,
  },
  sectionTabs: {
    gap: 8,
    paddingVertical: 14,
  },
  sectionTab: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
  },
  sectionTabActive: {
    borderColor: colors.green700,
    backgroundColor: colors.green100,
  },
  sectionTabText: {
    color: colors.slate700,
    fontWeight: '900',
  },
  sectionTabTextActive: {
    color: colors.green800,
  },
  fieldCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 10,
  },
  fieldGroupHeader: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: colors.green50,
    borderWidth: 1,
    borderColor: colors.green100,
  },
  fieldGroupTitle: {
    color: colors.green900,
    fontSize: 13,
    fontWeight: '900',
  },
  fieldLabel: {
    color: colors.slate900,
    fontWeight: '900',
    fontSize: 14,
    lineHeight: 19,
  },
  readonlyValue: {
    color: colors.slate700,
    marginTop: 8,
    fontWeight: '700',
  },
  optionGrid: {
    gap: 8,
    marginTop: 10,
  },
  optionButton: {
    minHeight: 42,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate50,
  },
  optionButtonActive: {
    borderColor: colors.green700,
    backgroundColor: colors.green700,
  },
  optionText: {
    color: colors.slate700,
    fontWeight: '800',
  },
  optionTextActive: {
    color: colors.white,
  },
  compoundCountBox: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  checkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: 14,
    marginBottom: 10,
  },
  checkBox: {
    width: 24,
    height: 24,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkBoxActive: {
    backgroundColor: colors.green700,
    borderColor: colors.green700,
  },
  checkText: {
    color: colors.white,
    fontWeight: '900',
  },
  checkLabel: {
    flex: 1,
    color: colors.slate900,
    fontWeight: '800',
  },
  mapBox: {
    minHeight: 110,
    borderRadius: 8,
    backgroundColor: colors.green50,
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 12,
    padding: 12,
  },
  mapPin: {
    color: colors.green800,
    fontWeight: '900',
  },
  mapText: {
    color: colors.slate900,
    fontWeight: '800',
    marginTop: 4,
  },
  attachmentGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 10,
  },
  attachmentTile: {
    width: '47%',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 8,
    backgroundColor: colors.slate50,
  },
  attachmentImage: {
    width: '100%',
    height: 86,
    borderRadius: 6,
    backgroundColor: colors.slate100,
  },
  attachmentName: {
    color: colors.slate700,
    fontSize: 12,
    marginTop: 6,
  },
  removeButton: {
    marginTop: 6,
  },
  removeText: {
    color: colors.red700,
    fontSize: 12,
    fontWeight: '800',
  },
  signatureBox: {
    height: 120,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.slate50,
    marginVertical: 10,
    overflow: 'hidden',
  },
  signatureHint: {
    color: colors.slate500,
    textAlign: 'center',
    marginTop: 48,
  },
  signatureDot: {
    position: 'absolute',
    width: 4,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate900,
  },
  reviewRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  reviewStatus: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green100,
  },
  reviewStatusWarning: {
    backgroundColor: colors.amber100,
  },
  reviewStatusText: {
    color: colors.green800,
    fontWeight: '900',
    fontSize: 11,
  },
  reviewStatusWarningText: {
    color: colors.amber700,
  },
  warningCard: {
    borderRadius: 8,
    backgroundColor: colors.amber100,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#f5d48d',
  },
  warningTitle: {
    color: colors.amber700,
    fontWeight: '900',
  },
  reviewSummaryCard: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    paddingTop: 14,
    paddingBottom: 2,
    marginBottom: 10,
    ...shadow,
  },
  readySubmitCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.green100,
    backgroundColor: colors.green50,
    padding: 14,
    marginBottom: 10,
  },
  readySubmitTitle: {
    color: colors.green900,
    fontWeight: '900',
  },
  errorRow: {
    paddingVertical: 7,
  },
  errorText: {
    color: colors.red700,
    fontSize: 12,
    fontWeight: '800',
    marginTop: 8,
  },
  attachmentStatusBox: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 10,
    marginTop: 10,
    backgroundColor: colors.slate50,
  },
  attachmentStatusTitle: {
    color: colors.slate900,
    fontSize: 12,
    fontWeight: '900',
  },
  attachmentStatusLabel: {
    flex: 1,
    color: colors.slate600,
    fontSize: 12,
    fontWeight: '800',
  },
  attachmentStatusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 4,
  },
  retryAction: {
    marginTop: 10,
  },
  bottomActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
  },
  savedText: {
    color: colors.green800,
    fontSize: 12,
    fontWeight: '800',
    textAlign: 'center',
    marginTop: 10,
    marginBottom: 18,
  },
  tabBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    minHeight: 74,
    paddingHorizontal: 6,
    borderRadius: 0,
    backgroundColor: colors.green950,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    ...shadow,
  },
  tabItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 58,
    borderRadius: 14,
    gap: 3,
  },
  tabItemActive: {
    backgroundColor: 'rgba(255, 255, 255, 0.16)',
    marginVertical: 8,
  },
  tabText: {
    color: '#a9cbb9',
    fontSize: 9,
    fontWeight: '800',
  },
  tabTextActive: {
    color: colors.white,
  },
});

<template>
  <LandingScreen v-if="!signedIn && landingVisible" @sign-in="openLogin" />
  <RegisterScreen v-else-if="!signedIn && registerVisible" @registered="handleRegistered" @sign-in="openLogin" />
  <AuthScreen v-else-if="!signedIn" @signed-in="handleSignedIn" @register="openRegister" />

  <div v-else class="app-frame">
    <AppSidebar :items="visibleNavItems" :active="activeSection" @select="openSection" />

    <section class="app-workspace">
      <AppTopbar
        :user="currentUser"
        :impersonating="isImpersonating"
        :original-user="originalUser"
        :workspaces="availableWorkspaces"
        :current-workspace-id="currentWorkspace?.id"
        @logout="logout"
        @stop-impersonation="stopImpersonation"
        @workspace-switch="switchWorkspace"
      />

      <DashboardScreen v-if="currentView === 'dashboard'" @navigate="openSection" />
      <MyAssessmentsScreen v-else-if="currentView === 'my-assessments'" :user="currentUser" />
      <FormsScreen v-else-if="currentView === 'forms'" @open-settings="openFormSettings" />
      <FormSettingsScreen v-else-if="currentView === 'form-settings'" :slug="selectedFormSlug" @back="openSection('Forms')" />
      <ExercisesScreen v-else-if="currentView === 'exercises'" @manage-assignments="openAssignmentManager" />
      <AssignmentsScreen v-else-if="currentView === 'assignments'" :initial-exercise-id="assignmentSeedExerciseId" />
      <SubmissionsScreen v-else-if="currentView === 'submissions'" />
      <TargetsScreen v-else-if="currentView === 'targets'" />
      <FacilitiesScreen v-else-if="currentView === 'facilities'" />
      <ReportsScreen v-else-if="currentView === 'reports'" />
      <TeamScreen v-else-if="currentView === 'team'" @session-updated="handleSessionUpdated" />
      <WorkspaceSettingsScreen v-else-if="currentView === 'workspace-settings'" @workspace-updated="handleWorkspaceUpdated" />
      <PlatformAdminScreen v-else-if="currentView === 'platform'" @session-updated="handleSessionUpdated" />
      <MobileSyncScreen v-else-if="currentView === 'mobile-sync'" />
      <GenericScreen v-else :section="activeSection" />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppSidebar, { type NavItem } from './components/layout/AppSidebar.vue';
import AppTopbar from './components/layout/AppTopbar.vue';
import AuthScreen from './components/layout/AuthScreen.vue';
import RegisterScreen from './components/layout/RegisterScreen.vue';
import AssignmentsScreen from './screens/AssignmentsScreen.vue';
import DashboardScreen from './screens/DashboardScreen.vue';
import ExercisesScreen from './screens/ExercisesScreen.vue';
import FacilitiesScreen from './screens/FacilitiesScreen.vue';
import FormsScreen from './screens/FormsScreen.vue';
import FormSettingsScreen from './screens/FormSettingsScreen.vue';
import GenericScreen from './screens/GenericScreen.vue';
import LandingScreen from './screens/LandingScreen.vue';
import MobileSyncScreen from './screens/MobileSyncScreen.vue';
import MyAssessmentsScreen from './screens/MyAssessmentsScreen.vue';
import ReportsScreen from './screens/ReportsScreen.vue';
import SubmissionsScreen from './screens/SubmissionsScreen.vue';
import TeamScreen from './screens/TeamScreen.vue';
import WorkspaceSettingsScreen from './screens/WorkspaceSettingsScreen.vue';
import PlatformAdminScreen from './screens/PlatformAdminScreen.vue';
import TargetsScreen from './screens/TargetsScreen.vue';
import {
  clearOdkSession,
  getCurrentOdkUser,
  getOdkToken,
  logoutOdkUser,
  stopOdkImpersonation,
  type OdkUser,
} from './services/auth';
import {
  acceptOdkInvitation,
  loadOdkWorkspaces,
  switchOdkWorkspace,
  type Workspace,
} from './services/workspaces';
import type { OdkWorkspaceSummary } from './services/teamAccess';

type NavLabel = 'Dashboard' | 'My assessments' | 'Forms' | 'Exercises' | 'Assignment Manager' | 'Submissions' | 'Targets' | 'Facilities' | 'Reports' | 'Integrations' | 'Team' | 'Settings' | 'Platform admin';
type AppView = 'dashboard' | 'my-assessments' | 'forms' | 'form-settings' | 'exercises' | 'assignments' | 'submissions' | 'targets' | 'facilities' | 'reports' | 'team' | 'workspace-settings' | 'mobile-sync' | 'platform' | 'generic';

const signedIn = ref(false);
const landingVisible = ref(window.location.pathname === '/');
const registerVisible = ref(window.location.pathname === '/register');
const currentUser = ref<OdkUser | null>(null);
const currentWorkspace = ref<Workspace | null>(null);
const availableWorkspaces = ref<OdkWorkspaceSummary[]>([]);
const isImpersonating = ref(false);
const originalUser = ref<OdkUser | null>(null);
const activeSection = ref<NavLabel>('Dashboard');
const currentView = ref<AppView>('dashboard');
const selectedFormSlug = ref('nicare-qa-checklist');
const assignmentSeedExerciseId = ref('');

const navItems: Array<NavItem & { label: NavLabel }> = [
  { label: 'Dashboard', icon: 'dashboard' },
  { label: 'My assessments', icon: 'clipboard' },
  { label: 'Forms', icon: 'forms' },
  { label: 'Exercises', icon: 'calendar' },
  { label: 'Assignment Manager', icon: 'clipboard' },
  { label: 'Submissions', icon: 'submissions' },
  { label: 'Targets', icon: 'map-pin' },
  { label: 'Facilities', icon: 'facilities' },
  { label: 'Reports', icon: 'reports' },
  { label: 'Integrations', icon: 'integrations' },
  { label: 'Team', icon: 'team' },
  { label: 'Settings', icon: 'settings' },
  { label: 'Platform admin', icon: 'settings' },
];
const visibleNavItems = computed(() => navItems.filter((item) => (
  (!['Team', 'Settings'].includes(item.label) || canManageWorkspace.value)
  && (item.label !== 'Platform admin' || Boolean(currentUser.value?.is_platform_admin))
)));

const canManageWorkspace = computed(() => {
  const workspace = currentWorkspace.value;
  const membership = currentUser.value?.workspaces?.find((item) => item.id === workspace?.id)?.pivot;

  return membership?.role_key === 'owner'
    || membership?.role_key === 'admin'
    || currentUser.value?.role?.key === 'admin';
});

const sectionPaths: Record<NavLabel, string> = {
  Dashboard: '/dashboard',
  'My assessments': '/my-assessments',
  Forms: '/forms',
  Exercises: '/exercises',
  'Assignment Manager': '/assignments',
  Submissions: '/submissions',
  Targets: '/targets',
  Facilities: '/facilities',
  Reports: '/reports',
  Integrations: '/integrations',
  Team: '/team',
  Settings: '/settings',
  'Platform admin': '/platform',
};

const viewBySection: Partial<Record<NavLabel, AppView>> = {
  Dashboard: 'dashboard',
  'My assessments': 'my-assessments',
  Forms: 'forms',
  Exercises: 'exercises',
  'Assignment Manager': 'assignments',
  Submissions: 'submissions',
  Targets: 'targets',
  Facilities: 'facilities',
  Reports: 'reports',
  Team: 'team',
  Settings: 'workspace-settings',
  Integrations: 'mobile-sync',
  'Platform admin': 'platform',
};

const sectionFromPath = (path: string): NavLabel => {
  const formSettingsPrefix = '/forms/';
  if (path.startsWith(formSettingsPrefix) && path.endsWith('/settings')) {
    return 'Forms';
  }

  const match = Object.entries(sectionPaths).find(([, sectionPath]) => (
    path === sectionPath || path.startsWith(`${sectionPath}/`)
  ));

  return (match?.[0] as NavLabel) || 'Dashboard';
};

const syncRoute = () => {
  const path = window.location.pathname;
  landingVisible.value = path === '/';
  registerVisible.value = path === '/register';
  const section = sectionFromPath(path);

  activeSection.value = section;

  if (path.startsWith('/forms/') && path.endsWith('/settings')) {
    const formSettingsPrefix = '/forms/';
    const formSettingsSuffix = '/settings';
    selectedFormSlug.value = decodeURIComponent(path.slice(
      formSettingsPrefix.length,
      -formSettingsSuffix.length,
    ));
    currentView.value = 'form-settings';
  } else {
    currentView.value = viewBySection[section] ?? 'generic';
  }

  const query = new URLSearchParams(window.location.search);
  assignmentSeedExerciseId.value = currentView.value === 'assignments'
    ? (query.get('exercise_id') || '')
    : '';
};

const navigate = (path: string, replace = false) => {
  const currentPath = `${window.location.pathname}${window.location.search}`;
  if (currentPath !== path) {
    window.history[replace ? 'replaceState' : 'pushState']({}, '', path);
  }
  syncRoute();
};

const openSection = (label: string) => {
  const section = label as NavLabel;
  navigate(sectionPaths[section] || '/dashboard');
};

const openLogin = () => {
  navigate('/login');
};

const openRegister = () => {
  navigate('/register');
};

const refreshWorkspaceState = async () => {
  availableWorkspaces.value = currentUser.value?.workspaces ?? [];
  const workspaceState = await loadOdkWorkspaces();
  currentWorkspace.value = workspaceState.current ?? workspaceState.items[0] ?? null;
};

const completePendingInvitation = async () => {
  const query = new URLSearchParams(window.location.search);
  const invitationUuid = query.get('invite');
  const invitationToken = query.get('token');

  if (!invitationUuid || !invitationToken) return;

  try {
    await acceptOdkInvitation(invitationUuid, invitationToken);
    currentUser.value = await getCurrentOdkUser();
    await refreshWorkspaceState();
    window.alert('You joined the workspace successfully.');
  } catch (error) {
    window.alert(error instanceof Error ? error.message : 'Unable to accept this invitation.');
  } finally {
    query.delete('invite');
    query.delete('token');
    const cleanPath = `${window.location.pathname}${query.toString() ? `?${query.toString()}` : ''}`;
    window.history.replaceState({}, '', cleanPath);
  }
};

const openAssignmentManager = (exerciseId?: number | string) => {
  const path = exerciseId
    ? `/assignments?exercise_id=${encodeURIComponent(String(exerciseId))}`
    : '/assignments';
  navigate(path);
};

const openFormSettings = (slug: string) => {
  navigate(`/forms/${encodeURIComponent(slug)}/settings`);
};

const handleSignedIn = async (user: OdkUser) => {
  currentUser.value = user;
  availableWorkspaces.value = user.workspaces ?? [];
  currentWorkspace.value = availableWorkspaces.value[0] ?? null;
  signedIn.value = true;
  isImpersonating.value = false;
  originalUser.value = null;

  await completePendingInvitation();
  navigate('/dashboard', true);
};

const handleRegistered = (result: { user: OdkUser; workspace: Workspace }) => {
  currentUser.value = result.user;
  availableWorkspaces.value = result.user.workspaces ?? [result.workspace];
  currentWorkspace.value = result.workspace;
  signedIn.value = true;
  registerVisible.value = false;
  navigate('/dashboard', true);
};

const handleSessionUpdated = (user: OdkUser, impersonating: boolean, original?: OdkUser | null) => {
  currentUser.value = user;
  isImpersonating.value = impersonating;
  originalUser.value = original ?? null;
  if (impersonating) {
    navigate('/dashboard', true);
  }
};

const stopImpersonation = async () => {
  try {
    currentUser.value = await stopOdkImpersonation();
    isImpersonating.value = false;
    originalUser.value = null;
    navigate('/dashboard', true);
  } catch {
    clearOdkSession();
    signedIn.value = false;
    navigate('/login', true);
  }
};

const handleWorkspaceUpdated = (workspace: Workspace) => {
  currentWorkspace.value = workspace;
  availableWorkspaces.value = availableWorkspaces.value.map((item) => item.id === workspace.id ? { ...item, ...workspace } : item);
};

const switchWorkspace = async (workspaceId: number) => {
  if (workspaceId === currentWorkspace.value?.id) return;
  try {
    await switchOdkWorkspace(workspaceId);
    window.location.reload();
  } catch (error) {
    window.alert(error instanceof Error ? error.message : 'Unable to switch workspace.');
  }
};

const logout = async () => {
  await logoutOdkUser().catch(() => clearOdkSession());
  currentUser.value = null;
  currentWorkspace.value = null;
  availableWorkspaces.value = [];
  isImpersonating.value = false;
  originalUser.value = null;
  signedIn.value = false;
  activeSection.value = 'Dashboard';
  currentView.value = 'dashboard';
  navigate('/login', true);
};

onMounted(async () => {
  syncRoute();
  window.addEventListener('popstate', syncRoute);

  if (!getOdkToken()) {
    return;
  }

  try {
    currentUser.value = await getCurrentOdkUser();
    availableWorkspaces.value = currentUser.value.workspaces ?? [];
    const hasPendingInvitation = new URLSearchParams(window.location.search).has('invite');
    if (hasPendingInvitation) {
      await completePendingInvitation();
    } else {
      await refreshWorkspaceState();
    }
    signedIn.value = true;

    if (window.location.pathname === '/' || window.location.pathname === '/login') {
      navigate('/dashboard', true);
    }
  } catch {
    clearOdkSession();
    signedIn.value = false;
    navigate('/login', true);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('popstate', syncRoute);
});
</script>

<template>
  <AuthScreen v-if="!signedIn" @signed-in="handleSignedIn" />

  <div v-else class="app-frame">
    <AppSidebar :items="visibleNavItems" :active="activeSection" @select="openSection" />

    <section class="app-workspace">
      <AppTopbar
        :user="currentUser"
        :impersonating="isImpersonating"
        :original-user="originalUser"
        @logout="logout"
        @stop-impersonation="stopImpersonation"
      />

      <DashboardScreen v-if="currentView === 'dashboard'" @navigate="openSection" />
      <FormsScreen v-else-if="currentView === 'forms'" @open-settings="openFormSettings" />
      <FormSettingsScreen v-else-if="currentView === 'form-settings'" :slug="selectedFormSlug" @back="openSection('Forms')" />
      <ExercisesScreen v-else-if="currentView === 'exercises'" @manage-assignments="openAssignmentManager" />
      <AssignmentsScreen v-else-if="currentView === 'assignments'" :initial-exercise-id="assignmentSeedExerciseId" />
      <SubmissionsScreen v-else-if="currentView === 'submissions'" />
      <FacilitiesScreen v-else-if="currentView === 'facilities'" />
      <ReportsScreen v-else-if="currentView === 'reports'" />
      <TeamScreen v-else-if="currentView === 'team'" @session-updated="handleSessionUpdated" />
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
import AssignmentsScreen from './screens/AssignmentsScreen.vue';
import DashboardScreen from './screens/DashboardScreen.vue';
import ExercisesScreen from './screens/ExercisesScreen.vue';
import FacilitiesScreen from './screens/FacilitiesScreen.vue';
import FormsScreen from './screens/FormsScreen.vue';
import FormSettingsScreen from './screens/FormSettingsScreen.vue';
import GenericScreen from './screens/GenericScreen.vue';
import MobileSyncScreen from './screens/MobileSyncScreen.vue';
import ReportsScreen from './screens/ReportsScreen.vue';
import SubmissionsScreen from './screens/SubmissionsScreen.vue';
import TeamScreen from './screens/TeamScreen.vue';
import {
  clearOdkSession,
  getCurrentOdkUser,
  getOdkToken,
  logoutOdkUser,
  stopOdkImpersonation,
  type OdkUser,
} from './services/auth';

type NavLabel = 'Dashboard' | 'Forms' | 'Exercises' | 'Assignment Manager' | 'Submissions' | 'Facilities' | 'Reports' | 'Integrations' | 'Team' | 'Settings';
type AppView = 'dashboard' | 'forms' | 'form-settings' | 'exercises' | 'assignments' | 'submissions' | 'facilities' | 'reports' | 'team' | 'mobile-sync' | 'generic';

const signedIn = ref(false);
const currentUser = ref<OdkUser | null>(null);
const isImpersonating = ref(false);
const originalUser = ref<OdkUser | null>(null);
const activeSection = ref<NavLabel>('Dashboard');
const currentView = ref<AppView>('dashboard');
const selectedFormSlug = ref('nicare-qa-checklist');
const assignmentSeedExerciseId = ref('');

const navItems: Array<NavItem & { label: NavLabel }> = [
  { label: 'Dashboard', icon: 'dashboard' },
  { label: 'Forms', icon: 'forms' },
  { label: 'Exercises', icon: 'calendar' },
  { label: 'Assignment Manager', icon: 'clipboard' },
  { label: 'Submissions', icon: 'submissions' },
  { label: 'Facilities', icon: 'facilities' },
  { label: 'Reports', icon: 'reports' },
  { label: 'Integrations', icon: 'integrations' },
  { label: 'Team', icon: 'team' },
  { label: 'Settings', icon: 'settings' },
];
const visibleNavItems = computed(() => navItems.filter((item) => (
  item.label !== 'Team' || currentUser.value?.role?.key === 'admin'
)));

const sectionPaths: Record<NavLabel, string> = {
  Dashboard: '/dashboard',
  Forms: '/forms',
  Exercises: '/exercises',
  'Assignment Manager': '/assignments',
  Submissions: '/submissions',
  Facilities: '/facilities',
  Reports: '/reports',
  Integrations: '/integrations',
  Team: '/team',
  Settings: '/settings',
};

const viewBySection: Partial<Record<NavLabel, AppView>> = {
  Dashboard: 'dashboard',
  Forms: 'forms',
  Exercises: 'exercises',
  'Assignment Manager': 'assignments',
  Submissions: 'submissions',
  Facilities: 'facilities',
  Reports: 'reports',
  Team: 'team',
  Integrations: 'mobile-sync',
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

const openAssignmentManager = (exerciseId?: number | string) => {
  const path = exerciseId
    ? `/assignments?exercise_id=${encodeURIComponent(String(exerciseId))}`
    : '/assignments';
  navigate(path);
};

const openFormSettings = (slug: string) => {
  navigate(`/forms/${encodeURIComponent(slug)}/settings`);
};

const handleSignedIn = (user: OdkUser) => {
  currentUser.value = user;
  signedIn.value = true;
  isImpersonating.value = false;
  originalUser.value = null;

  const currentPath = `${window.location.pathname}${window.location.search}`;
  navigate(currentPath === '/' || currentPath === '/login' ? '/dashboard' : currentPath, true);
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

const logout = async () => {
  await logoutOdkUser().catch(() => clearOdkSession());
  currentUser.value = null;
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
    if (window.location.pathname === '/') {
      navigate('/login', true);
    }
    return;
  }

  try {
    currentUser.value = await getCurrentOdkUser();
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

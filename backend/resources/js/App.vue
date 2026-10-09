<template>
  <AuthScreen v-if="!signedIn" @signed-in="signedIn = true" />

  <div v-else class="app-frame">
    <AppSidebar :items="navItems" :active="activeSection" @select="openSection" />

    <section class="app-workspace">
      <AppTopbar @logout="logout" />

      <DashboardScreen v-if="currentView === 'dashboard'" @navigate="openSection" />
      <FormsScreen v-else-if="currentView === 'forms'" @open-settings="openFormSettings" />
      <FormSettingsScreen v-else-if="currentView === 'form-settings'" :slug="selectedFormSlug" @back="openSection('Forms')" />
      <ExercisesScreen v-else-if="currentView === 'exercises'" @manage-assignments="openAssignmentManager" />
      <AssignmentsScreen v-else-if="currentView === 'assignments'" :initial-exercise-id="assignmentSeedExerciseId" />
      <SubmissionsScreen v-else-if="currentView === 'submissions'" />
      <FacilitiesScreen v-else-if="currentView === 'facilities'" />
      <ReportsScreen v-else-if="currentView === 'reports'" />
      <TeamScreen v-else-if="currentView === 'team'" />
      <MobileSyncScreen v-else-if="currentView === 'mobile-sync'" />
      <GenericScreen v-else :section="activeSection" />
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
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

type NavLabel = 'Dashboard' | 'Forms' | 'Exercises' | 'Assignment Manager' | 'Submissions' | 'Facilities' | 'Reports' | 'Integrations' | 'Team' | 'Settings';
type AppView = 'dashboard' | 'forms' | 'form-settings' | 'exercises' | 'assignments' | 'submissions' | 'facilities' | 'reports' | 'team' | 'mobile-sync' | 'generic';

const signedIn = ref(true);
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

const openSection = (label: string) => {
  activeSection.value = label as NavLabel;
  const views: Partial<Record<NavLabel, AppView>> = {
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

  currentView.value = views[label as NavLabel] ?? 'generic';
  if (currentView.value !== 'assignments') {
    assignmentSeedExerciseId.value = '';
  }
};

const openAssignmentManager = (exerciseId?: number | string) => {
  assignmentSeedExerciseId.value = exerciseId ? String(exerciseId) : '';
  activeSection.value = 'Assignment Manager';
  currentView.value = 'assignments';
};

const openFormSettings = (slug: string) => {
  selectedFormSlug.value = slug;
  activeSection.value = 'Forms';
  currentView.value = 'form-settings';
};

const logout = () => {
  signedIn.value = false;
  activeSection.value = 'Dashboard';
  currentView.value = 'dashboard';
};
</script>

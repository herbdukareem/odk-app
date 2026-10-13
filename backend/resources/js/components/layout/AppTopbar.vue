<template>
  <header class="topbar">
    <div v-if="impersonating" class="impersonation-banner">
      <span>Viewing as <strong>{{ user?.name || user?.email }}</strong></span>
      <button type="button" @click="$emit('stop-impersonation')">Return to admin</button>
    </div>
    <QdsSearch placeholder="Search forms, facilities, submissions..." />
    <label v-if="workspaces.length > 1" class="workspace-switcher">
      <span>Workspace</span>
      <select :value="currentWorkspaceId || ''" @change="$emit('workspace-switch', Number(($event.target as HTMLSelectElement).value))">
        <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
      </select>
    </label>
    <QdsIconButton icon="bell" aria-label="Notifications" />
    <div class="user-menu">
      <span class="user-avatar">AY</span>
      <div>
        <strong>{{ user?.name || user?.email || 'ODK user' }}</strong>
        <span>{{ user?.designation || user?.role?.name || 'Portal user' }}</span>
      </div>
      <button class="logout-button" type="button" @click="$emit('logout')">Logout</button>
    </div>
  </header>
</template>

<script setup lang="ts">
import QdsIconButton from '../qds/QdsIconButton.vue';
import QdsSearch from '../qds/QdsSearch.vue';
import type { OdkWorkspaceSummary } from '../../services/teamAccess';

withDefaults(defineProps<{
  user?: {
    name?: string | null;
    email?: string | null;
    designation?: string | null;
    role?: { name?: string | null } | null;
  } | null;
  impersonating?: boolean;
  originalUser?: { name?: string | null } | null;
  workspaces?: OdkWorkspaceSummary[];
  currentWorkspaceId?: number | null;
}>(), {
  user: null,
  impersonating: false,
  originalUser: null,
  workspaces: () => [],
  currentWorkspaceId: null,
});

defineEmits<{
  logout: [];
  'stop-impersonation': [];
  'workspace-switch': [workspaceId: number];
}>();
</script>

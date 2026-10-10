<template>
  <header class="topbar">
    <div v-if="impersonating" class="impersonation-banner">
      <span>Viewing as <strong>{{ user?.name || user?.email }}</strong></span>
      <button type="button" @click="$emit('stop-impersonation')">Return to admin</button>
    </div>
    <QdsSearch placeholder="Search forms, facilities, submissions..." />
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

withDefaults(defineProps<{
  user?: {
    name?: string | null;
    email?: string | null;
    designation?: string | null;
    role?: { name?: string | null } | null;
  } | null;
  impersonating?: boolean;
  originalUser?: { name?: string | null } | null;
}>(), {
  user: null,
  impersonating: false,
  originalUser: null,
});

defineEmits<{
  logout: [];
  'stop-impersonation': [];
}>();
</script>

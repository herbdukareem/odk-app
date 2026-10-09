<template>
  <header class="topbar">
    <QdsSearch placeholder="Search forms, facilities, submissions..." />
    <QdsIconButton icon="bell" aria-label="Notifications" />
    <div class="user-menu">
      <span class="user-avatar">AY</span>
      <div>
        <strong>Amina Yusuf</strong>
        <span>National QA Officer</span>
      </div>
      <button class="logout-button" type="button" @click="$emit('logout')">Logout</button>
    </div>
  </header>
</template>

<script setup lang="ts">
import QdsIconButton from '../qds/QdsIconButton.vue';
import QdsSearch from '../qds/QdsSearch.vue';

defineEmits<{
  logout: [];
}>();
</script>

<template>
  <aside class="app-sidebar">
    <BrandMark />
    <nav class="sidebar-nav" aria-label="Primary navigation">
      <button
        v-for="item in items"
        :key="item.label"
        :class="['sidebar-link', { active: item.label === active }]"
        type="button"
        @click="$emit('select', item.label)"
      >
        <AppIcon :name="item.icon" />
        <span>{{ item.label }}</span>
      </button>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import AppIcon from '../../AppIcon.vue';
import BrandMark from './BrandMark.vue';

export type NavItem = {
  label: string;
  icon: string;
};

defineProps<{
  items: NavItem[];
  active: string;
}>();

defineEmits<{
  select: [label: string];
}>();
</script>

<template>
  <aside :class="['app-sidebar', { 'app-sidebar--open': open }]">
    <div class="sidebar-brand-row">
      <a class="brand-link" href="/" aria-label="Go to DataKit home" @click.prevent="$emit('home')">
        <BrandMark />
      </a>
      <QdsIconButton class="mobile-sidebar-close" icon="close" aria-label="Close navigation" @click="$emit('close')" />
    </div>
    <nav class="sidebar-nav" aria-label="Primary navigation">
      <button
        v-for="item in items"
        :key="item.label"
        :class="['sidebar-link', { active: item.label === active }]"
        type="button"
        @click="handleSelect(item.label)"
      >
        <AppIcon :name="item.icon" />
        <span>{{ item.label }}</span>
      </button>
    </nav>
  </aside>
  <div v-if="open" class="app-sidebar__scrim" aria-hidden="true" @click="$emit('close')"></div>
</template>

<script setup lang="ts">
import AppIcon from '../../AppIcon.vue';
import QdsIconButton from '../qds/QdsIconButton.vue';
import BrandMark from './BrandMark.vue';

export type NavItem = {
  label: string;
  icon: string;
};

defineProps<{
  items: NavItem[];
  active: string;
  open?: boolean;
}>();

const emit = defineEmits<{
  select: [label: string];
  home: [];
  close: [];
}>();

const handleSelect = (label: string) => {
  emit('select', label);
  emit('close');
};
</script>

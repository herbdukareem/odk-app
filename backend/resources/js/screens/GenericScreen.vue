<template>
  <section class="screen-stack">
    <AppPageHeader :title="section" subtitle="Workspace foundation ready for the next phase." />
    <QdsCard class="empty-state">
      <div>
        <div class="empty-icon">
          <AppIcon name="forms" />
        </div>
        <h2>{{ section }} workspace</h2>
        <p>This area inherits the shared DataKit design system: spacing, cards, badges, buttons, tables, inputs, and iconography.</p>
      </div>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsCard from '../components/qds/QdsCard.vue';

defineProps<{
  section: string;
}>();
</script>

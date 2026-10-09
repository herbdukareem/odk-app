<template>
  <div :class="['page-heading', compact ? 'page-heading--compact' : '']">
    <div class="page-heading__title">
      <p v-if="kicker" class="qds-kicker">{{ kicker }}</p>
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="qds-muted">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="page-heading__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string;
  subtitle?: string;
  kicker?: string;
  compact?: boolean;
}>(), {
  subtitle: '',
  kicker: '',
  compact: false,
});
</script>

<template>
  <span :class="['qds-badge', `qds-badge--${tone}`]">
    <slot />
  </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  tone?: 'green' | 'amber' | 'blue' | 'slate' | 'red';
}>(), {
  tone: 'slate',
});
</script>

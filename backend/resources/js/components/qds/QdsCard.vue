<template>
  <article :class="['qds-card', padded ? 'qds-card--padded' : '']">
    <slot />
  </article>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  padded?: boolean;
}>(), {
  padded: false,
});
</script>

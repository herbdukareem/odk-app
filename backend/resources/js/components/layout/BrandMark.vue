<template>
  <img
    class="brand-logo"
    :src="theme === 'light' ? '/brand/datakit-light.svg' : '/brand/datakit-dark.svg'"
    alt="DataKit Quality Assurance"
  >
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  theme?: 'default' | 'light';
}>(), {
  theme: 'default',
});
</script>

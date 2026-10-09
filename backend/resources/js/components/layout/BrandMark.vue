<template>
  <div :class="['brand-mark', theme === 'light' ? 'brand-mark--light' : '']">
    <span class="brand-symbol">
      <AppIcon name="leaf" />
    </span>
    <div>
      <strong>NiCare</strong>
      <small>Quality Assurance</small>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppIcon from '../../AppIcon.vue';

withDefaults(defineProps<{
  theme?: 'default' | 'light';
}>(), {
  theme: 'default',
});
</script>

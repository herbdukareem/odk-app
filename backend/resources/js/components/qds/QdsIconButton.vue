<template>
  <button :class="['qds-icon-button', tiny ? 'qds-icon-button--tiny' : '']" type="button">
    <AppIcon :name="icon" />
  </button>
</template>

<script setup lang="ts">
import AppIcon from '../../AppIcon.vue';

withDefaults(defineProps<{
  icon: string;
  tiny?: boolean;
}>(), {
  tiny: false,
});
</script>

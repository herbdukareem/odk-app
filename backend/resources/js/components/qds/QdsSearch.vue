<template>
  <label :class="['qds-search', compact ? 'qds-search--compact' : '']">
    <AppIcon name="search" />
    <input :value="modelValue" :placeholder="placeholder" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)">
  </label>
</template>

<script setup lang="ts">
import AppIcon from '../../AppIcon.vue';

withDefaults(defineProps<{
  modelValue?: string;
  placeholder?: string;
  compact?: boolean;
}>(), {
  modelValue: '',
  placeholder: 'Search...',
  compact: false,
});

defineEmits<{
  'update:modelValue': [value: string];
}>();
</script>

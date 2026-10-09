<template>
  <label class="qds-checkbox">
    <input
      type="checkbox"
      :checked="modelValue"
      @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    >
    <span>{{ label }}</span>
  </label>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  label: string;
  modelValue?: boolean;
}>(), {
  modelValue: false,
});

defineEmits<{
  'update:modelValue': [value: boolean];
}>();
</script>

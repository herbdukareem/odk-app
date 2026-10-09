<template>
  <button :class="['qds-switch', modelValue ? 'active' : '']" type="button" :aria-pressed="modelValue" @click="$emit('update:modelValue', !modelValue)">
    <span />
  </button>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue?: boolean;
}>(), {
  modelValue: false,
});

defineEmits<{
  'update:modelValue': [value: boolean];
}>();
</script>

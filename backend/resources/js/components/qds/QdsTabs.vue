<template>
  <div :class="['qds-tabs', wide ? 'qds-tabs--wide' : '']">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      :class="{ active: tab.value === modelValue }"
      type="button"
      @click="$emit('update:modelValue', tab.value)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
export type QdsTab = {
  label: string;
  value: string;
};

withDefaults(defineProps<{
  tabs: QdsTab[];
  modelValue: string;
  wide?: boolean;
}>(), {
  wide: false,
});

defineEmits<{
  'update:modelValue': [value: string];
}>();
</script>

<template>
  <label class="qds-field">
    <span>{{ label }}</span>
    <textarea v-if="textarea" :rows="rows" :disabled="disabled" :value="modelValue" @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)" />
    <select v-else-if="options.length" :disabled="disabled" :value="modelValue" @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)">
      <option v-for="option in options" :key="optionValue(option)" :value="optionValue(option)">
        {{ optionLabel(option) }}
      </option>
    </select>
    <input v-else :type="type" :min="min" :max="max" :disabled="disabled" :readonly="readonly" :value="modelValue" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)">
  </label>
</template>

<script setup lang="ts">
type FieldOption = string | {
  label: string;
  value: string;
};

withDefaults(defineProps<{
  label: string;
  modelValue?: string;
  type?: string;
  readonly?: boolean;
  disabled?: boolean;
  min?: string | number;
  max?: string | number;
  textarea?: boolean;
  rows?: number;
  options?: FieldOption[];
}>(), {
  modelValue: '',
  type: 'text',
  readonly: false,
  disabled: false,
  textarea: false,
  rows: 4,
  options: () => [],
});

const optionValue = (option: FieldOption) => (typeof option === 'string' ? option : option.value);
const optionLabel = (option: FieldOption) => (typeof option === 'string' ? option : option.label);

defineEmits<{
  'update:modelValue': [value: string];
}>();
</script>

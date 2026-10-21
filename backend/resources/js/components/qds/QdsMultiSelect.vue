<template>
  <div class="qds-multi-select-field">
    <span :id="labelId">{{ label }}</span>
    <details class="qds-multi-select" :aria-disabled="disabled" @toggle="search = ''">
      <summary role="button" :aria-label="label" :aria-disabled="disabled" @click="disabled && $event.preventDefault()">
        {{ modelValue.length === 1 ? options.find(option => option.value === modelValue[0])?.label : modelValue.length ? modelValue.length + ' selected' : 'Select exercises' }}
      </summary>
      <div class="qds-multi-select__panel" role="group" :aria-labelledby="labelId">
        <QdsSearch v-model="search" compact placeholder="Search exercises..." />
        <div class="qds-multi-select__actions">
          <label><input type="checkbox" :checked="allSelected" :indeterminate="someSelected && !allSelected" :disabled="disabled || !filtered.length" @change="toggleAll">Select all</label>
          <QdsButton variant="tiny" :disabled="disabled || !modelValue.length" @click="$emit('update:modelValue', [])">Clear</QdsButton>
        </div>
        <div class="qds-multi-select__options">
          <label v-for="option in filtered" :key="option.value">
            <input type="checkbox" :checked="modelValue.includes(option.value)" :disabled="disabled" @change="toggle(option.value)">
            <span>{{ option.label }}</span>
          </label>
          <p v-if="!filtered.length">No matching exercises.</p>
        </div>
      </div>
    </details>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import QdsButton from './QdsButton.vue';
import QdsSearch from './QdsSearch.vue';

const props = withDefaults(defineProps<{
  label: string;
  modelValue: string[];
  options: Array<{ value: string; label: string }>;
  disabled?: boolean;
}>(), { disabled: false });
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();
const labelId = useId();
const search = ref('');
const filtered = computed(() => props.options.filter(option => option.label.toLowerCase().includes(search.value.trim().toLowerCase())));
const allSelected = computed(() => filtered.value.length > 0 && filtered.value.every(option => props.modelValue.includes(option.value)));
const someSelected = computed(() => filtered.value.some(option => props.modelValue.includes(option.value)));
function toggle(value: string) {
  emit('update:modelValue', props.modelValue.includes(value) ? props.modelValue.filter(item => item !== value) : [...props.modelValue, value]);
}
function toggleAll() {
  const visible = new Set(filtered.value.map(option => option.value));
  emit('update:modelValue', allSelected.value ? props.modelValue.filter(value => !visible.has(value)) : [...new Set([...props.modelValue, ...visible])]);
}
</script>

<style scoped>
.qds-multi-select-field { display: grid; gap: 6px; min-width: 0; margin-bottom: 8px; color: var(--qds-slate-700); font-size: 12px; font-weight: 500; }
.qds-multi-select { min-width: 0; border: 1px solid var(--qds-slate-200); border-radius: 6px; background: var(--qds-white); }
.qds-multi-select summary { min-height: 40px; padding: 10px 12px; cursor: pointer; overflow-wrap: anywhere; }
.qds-multi-select__panel { display: grid; gap: 8px; padding: 10px; border-top: 1px solid var(--qds-slate-200); }
.qds-multi-select__actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.qds-multi-select__actions label, .qds-multi-select__options label { display: flex; align-items: center; gap: 9px; }
.qds-multi-select__options { max-height: 210px; overflow-y: auto; }
.qds-multi-select__options label { min-height: 40px; padding: 6px 0; }
.qds-multi-select__options span { min-width: 0; overflow-wrap: anywhere; }
.qds-multi-select input[type="checkbox"] { flex-shrink: 0; width: 14px; height: 14px; margin: 0; accent-color: var(--qds-green-700); }
.qds-multi-select[aria-disabled="true"] { opacity: 0.65; }
</style>

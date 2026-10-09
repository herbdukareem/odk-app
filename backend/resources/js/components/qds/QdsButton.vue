<template>
  <component :is="as" :class="classes" :type="buttonType">
    <AppIcon v-if="icon" :name="icon" />
    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '../../AppIcon.vue';

const props = withDefaults(defineProps<{
  as?: string;
  type?: 'button' | 'submit' | 'reset';
  variant?: 'primary' | 'secondary' | 'tiny';
  block?: boolean;
  icon?: string;
}>(), {
  as: 'button',
  type: 'button',
  variant: 'primary',
  block: false,
});

const classes = computed(() => [
  'qds-button',
  `qds-button--${props.variant}`,
  props.block ? 'qds-button--block' : '',
]);

const buttonType = computed(() => (props.as === 'button' ? props.type : undefined));
</script>

<template>
  <button
    v-bind="$attrs"
    :disabled="disabled || loading"
    class="active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark-800"
    :class="[variantClass, sizeClass]"
    @click="emit('click', $event)"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin flex-shrink-0"
      :class="spinnerSizeClass"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>

    <!-- Icon slot (left) -->
    <slot name="icon-left" />

    <!-- Content -->
    <slot>{{ label }}</slot>

    <!-- Icon slot (right) -->
    <slot name="icon-right" />
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: v => ['primary', 'secondary', 'ghost', 'danger', 'outline'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: v => ['xs', 'sm', 'md', 'lg'].includes(v),
  },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: '' },
});

const emit = defineEmits(['click']);

defineOptions({ inheritAttrs: false });

const variantClass = computed(() => {
  const map = {
    primary: 'btn-primary focus:ring-brand-500',
    secondary: 'btn-secondary focus:ring-dark-400',
    ghost: 'btn-ghost focus:ring-dark-500',
    danger: 'btn-danger focus:ring-red-500',
    outline:
      'btn bg-transparent border border-brand-500/50 hover:border-brand-500 hover:bg-brand-500/10 text-brand-400 hover:text-brand-300 focus:ring-brand-500',
  };
  return map[props.variant] ?? map.primary;
});

const sizeClass = computed(() => {
  const map = {
    xs: '!text-xs !px-2.5 !py-1.5 !rounded-lg',
    sm: '!text-sm !px-3.5 !py-2 !rounded-lg',
    md: '',
    lg: '!text-base !px-6 !py-3',
  };
  return map[props.size] ?? map.md;
});

const spinnerSizeClass = computed(() => {
  const map = {
    xs: 'w-3 h-3',
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  };
  return map[props.size] ?? map.md;
});
</script>

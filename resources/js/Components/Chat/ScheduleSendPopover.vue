<template>
  <div class="schedule-pop glass-elevated w-72 p-3" @click.stop>
    <p class="text-xs font-semibold text-white mb-2">Schedule message</p>

    <div class="grid grid-cols-2 gap-1.5 mb-3">
      <button
        v-for="preset in presets"
        :key="preset.label"
        @click="pick(preset.getDate())"
        type="button"
        class="text-[11px] px-2 py-1.5 rounded-lg bg-white/[0.05] hover:bg-white/[0.09] text-dark-50 transition-colors"
      >
        {{ preset.label }}
      </button>
    </div>

    <input v-model="localValue" type="datetime-local" :min="minValue" class="input-field !py-1.5 text-xs" />

    <div class="flex justify-end gap-2 mt-3">
      <button @click="$emit('close')" type="button" class="btn-ghost !px-3 !py-1.5 !text-xs">Cancel</button>
      <button @click="confirm" type="button" class="btn-primary !px-3 !py-1.5 !text-xs" :disabled="!localValue">
        Schedule
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const emit = defineEmits(['close', 'schedule']);

function toLocalInputValue(date) {
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const minValue = computed(() => toLocalInputValue(new Date(Date.now() + 60_000)));
const localValue = ref('');

const presets = [
  { label: 'In 1 hour', getDate: () => new Date(Date.now() + 60 * 60_000) },
  { label: 'In 3 hours', getDate: () => new Date(Date.now() + 3 * 60 * 60_000) },
  {
    label: 'Tomorrow 9am',
    getDate: () => {
      const d = new Date();
      d.setDate(d.getDate() + 1);
      d.setHours(9, 0, 0, 0);
      return d;
    },
  },
  {
    label: 'Monday 9am',
    getDate: () => {
      const d = new Date();
      const daysUntilMonday = (8 - d.getDay()) % 7 || 7;
      d.setDate(d.getDate() + daysUntilMonday);
      d.setHours(9, 0, 0, 0);
      return d;
    },
  },
];

function pick(date) {
  localValue.value = toLocalInputValue(date);
}

function confirm() {
  if (!localValue.value) return;
  emit('schedule', new Date(localValue.value).toISOString());
}
</script>

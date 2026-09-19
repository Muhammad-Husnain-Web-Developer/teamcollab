<template>
  <div class="relative" style="height: 200px">
    <canvas ref="chartCanvas" />
    <div v-if="!hasData" class="absolute inset-0 flex items-center justify-center">
      <p class="text-dark-500 text-sm">No data available.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import {
  Chart,
  LineElement,
  PointElement,
  LineController,
  CategoryScale,
  LinearScale,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js';

Chart.register(
  LineElement,
  PointElement,
  LineController,
  CategoryScale,
  LinearScale,
  Filler,
  Tooltip,
  Legend,
);

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ dates: [], counts: [] }),
  },
});

const chartCanvas = ref(null);
let chartInstance = null;

const hasData = computed(
  () => props.data?.dates?.length > 0 && props.data?.counts?.length > 0,
);

function buildChart() {
  if (!chartCanvas.value) return;
  if (chartInstance) {
    chartInstance.destroy();
    chartInstance = null;
  }
  if (!hasData.value) return;

  const ctx = chartCanvas.value.getContext('2d');

  // Gradient fill
  const gradient = ctx.createLinearGradient(0, 0, 0, 200);
  gradient.addColorStop(0, 'rgba(92, 124, 250, 0.25)');
  gradient.addColorStop(1, 'rgba(92, 124, 250, 0)');

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: props.data.dates,
      datasets: [
        {
          label: 'Messages',
          data: props.data.counts,
          borderColor: '#5c7cfa',
          backgroundColor: gradient,
          borderWidth: 2,
          pointBackgroundColor: '#5c7cfa',
          pointBorderColor: '#1a1a1c',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e1e22',
          borderColor: '#3a3a3e',
          borderWidth: 1,
          titleColor: '#9ca3af',
          bodyColor: '#ffffff',
          padding: 12,
          callbacks: {
            title: ctx => ctx[0].label,
            label: ctx => ` ${ctx.parsed.y.toLocaleString()} messages`,
          },
        },
      },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,0.03)' },
          ticks: {
            color: '#6b7280',
            font: { size: 11 },
            maxRotation: 0,
            maxTicksLimit: 7,
          },
          border: { color: 'transparent' },
        },
        y: {
          grid: { color: 'rgba(255,255,255,0.04)' },
          ticks: {
            color: '#6b7280',
            font: { size: 11 },
            callback: val => val.toLocaleString(),
          },
          border: { color: 'transparent' },
          beginAtZero: true,
        },
      },
      animation: {
        duration: 800,
        easing: 'easeInOutCubic',
      },
    },
  });
}

onMounted(() => {
  buildChart();
});

watch(() => props.data, buildChart, { deep: true });

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy();
  }
});
</script>

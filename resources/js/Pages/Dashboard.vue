<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-6 border-b border-white/[0.06]">
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Good {{ timeOfDay }}, {{ authStore.userName.split(' ')[0] }} 👋
        </h1>
        <p class="text-dark-50/70 text-sm mt-1">Here's what's happening in your workspace today.</p>
      </div>

      <div class="p-6 space-y-6">
        <!-- Stats cards row -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
          <StatsCard
            v-for="(stat, i) in stats"
            :key="stat.key"
            :title="stat.title"
            :value="stat.value"
            :icon="stat.icon"
            :color="stat.color"
            :trend="stat.trend"
            :ref="el => statCardRefs[i] = el"
          />
        </div>

        <!-- Charts row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <!-- Line chart: daily messages -->
          <div class="lg:col-span-2 glass-card p-5">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-sm font-semibold text-white">Message Activity</h3>
                <p class="text-xs text-dark-50/60">Last 14 days</p>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-brand-400 shadow-[0_0_6px_rgba(92,124,250,0.8)]" />
                <span class="text-xs text-dark-50/70">Messages sent</span>
              </div>
            </div>
            <MessageChart :data="chartData" />
          </div>

          <!-- Doughnut: channel activity -->
          <div class="glass-card p-5">
            <h3 class="text-sm font-semibold text-white mb-4">Top Channels</h3>
            <div class="flex flex-col items-center">
              <div ref="doughnutContainer" class="w-40 h-40 relative">
                <canvas ref="doughnutCanvas" />
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                  <span class="text-2xl font-bold text-white">{{ totalChannelMessages }}</span>
                  <span class="text-xs text-dark-50/60">total</span>
                </div>
              </div>
              <!-- Legend -->
              <div class="mt-4 space-y-2 w-full">
                <div
                  v-for="(ch, i) in topChannels"
                  :key="ch.name"
                  class="flex items-center justify-between"
                >
                  <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: chartColors[i] }" />
                    <span class="text-xs text-dark-50 truncate max-w-[100px]">#{{ ch.name }}</span>
                  </div>
                  <span class="text-xs text-dark-50/60">{{ ch.count }}</span>
                </div>
              </div>

              <div v-if="topChannels.length === 0" class="mt-2 text-xs text-dark-50/50 text-center py-4">
                No channel activity yet.
              </div>
            </div>
          </div>
        </div>

        <!-- Recent activity -->
        <div class="glass-card p-5">
          <h3 class="text-sm font-semibold text-white mb-4">Recent Activity</h3>
          <div class="space-y-1">
            <div
              v-for="(activity, i) in recentActivity"
              :key="i"
              ref="activityRefs"
              class="flex items-start gap-3 py-2.5 px-3 rounded-xl hover:bg-white/[0.04] transition-colors group"
            >
              <Avatar :src="activity.user?.avatar_url" :name="activity.user?.name" size="sm" />
              <div class="flex-1 min-w-0">
                <p class="text-sm text-dark-50">
                  <span class="font-medium text-white">{{ activity.user?.name }}</span>
                  {{ activity.description }}
                  <span v-if="activity.channel" class="text-brand-400">#{{ activity.channel }}</span>
                </p>
                <p class="text-xs text-dark-50/50 mt-0.5">{{ formatRelativeTime(activity.created_at) }}</p>
              </div>
              <div class="w-2 h-2 mt-1.5 rounded-full flex-shrink-0" :class="activityDot(activity.type)" />
            </div>
          </div>

          <div v-if="recentActivity.length === 0" class="empty-state !py-8">
            <div class="empty-state-icon">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            </div>
            <p class="text-dark-50/60 text-sm">No recent activity yet.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { Chart, registerables } from 'chart.js';
import AppLayout from '../Layouts/AppLayout.vue';
import StatsCard from '../Components/Dashboard/StatsCard.vue';
import MessageChart from '../Components/Dashboard/MessageChart.vue';
import Avatar from '../Components/Common/Avatar.vue';
import { useAuthStore } from '../Stores/useAuthStore';

Chart.register(...registerables);

const page = usePage();
const authStore = useAuthStore();

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  chartData: { type: Object, default: () => ({ dates: [], counts: [] }) },
  topChannels: { type: Array, default: () => [] },
  recentActivity: { type: Array, default: () => [] },
});

const doughnutCanvas = ref(null);
const statCardRefs = ref([]);
const activityRefs = ref([]);
let doughnutChart = null;

const chartColors = [
  '#5c7cfa', '#7c3aed', '#06b6d4', '#10b981', '#f59e0b', '#ef4444',
];

// Stats config derived from props
const stats = computed(() => [
  {
    key: 'total_members',
    title: 'Total Members',
    value: props.stats.total_members ?? 0,
    icon: 'users',
    color: 'blue',
    trend: props.stats.members_trend,
  },
  {
    key: 'active_users',
    title: 'Active Today',
    value: props.stats.active_users ?? 0,
    icon: 'activity',
    color: 'green',
    trend: props.stats.active_trend,
  },
  {
    key: 'total_messages',
    title: 'Total Messages',
    value: props.stats.total_messages ?? 0,
    icon: 'chat',
    color: 'purple',
    trend: null,
  },
  {
    key: 'messages_today',
    title: 'Messages Today',
    value: props.stats.messages_today ?? 0,
    icon: 'chat-active',
    color: 'cyan',
    trend: props.stats.messages_trend,
  },
  {
    key: 'total_channels',
    title: 'Channels',
    value: props.stats.total_channels ?? 0,
    icon: 'hash',
    color: 'yellow',
    trend: null,
  },
  {
    key: 'storage_used',
    title: 'Storage',
    value: props.stats.storage_used ?? '0 MB',
    icon: 'server',
    color: 'orange',
    trend: null,
  },
]);

const totalChannelMessages = computed(
  () => props.topChannels.reduce((s, c) => s + (c.count ?? 0), 0),
);

const timeOfDay = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return 'morning';
  if (h < 17) return 'afternoon';
  return 'evening';
});

function formatRelativeTime(iso) {
  if (!iso) return '';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

function activityDot(type) {
  const map = {
    message: 'bg-brand-400',
    join: 'bg-emerald-400',
    leave: 'bg-red-400',
    channel: 'bg-amber-400',
  };
  return map[type] ?? 'bg-dark-400';
}

function buildDoughnutChart() {
  if (!doughnutCanvas.value || props.topChannels.length === 0) return;
  if (doughnutChart) doughnutChart.destroy();

  doughnutChart = new Chart(doughnutCanvas.value, {
    type: 'doughnut',
    data: {
      labels: props.topChannels.map(c => `#${c.name}`),
      datasets: [
        {
          data: props.topChannels.map(c => c.count ?? 0),
          backgroundColor: chartColors.slice(0, props.topChannels.length),
          borderColor: '#1a1a1c',
          borderWidth: 3,
          hoverOffset: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed} messages`,
          },
        },
      },
      animation: { animateRotate: true, duration: 800 },
    },
  });
}

onMounted(() => {
  buildDoughnutChart();

  // Stagger animate activity items
  if (activityRefs.value.length) {
    gsap.fromTo(
      activityRefs.value,
      { opacity: 0, y: 12 },
      { opacity: 1, y: 0, stagger: 0.05, duration: 0.4, ease: 'power2.out', delay: 0.3 },
    );
  }
});
</script>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement,
    LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const api     = useApi();
const period  = ref('30d');
const trends  = ref(null);
const loading = ref(true);

async function load() {
    loading.value = true;
    const res = await api.get('/analytics/trends', { period: period.value });
    trends.value  = res.data;
    loading.value = false;
}

onMounted(load);

const chartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#18181b', titleColor: '#e4e4e7', bodyColor: '#a1a1aa', borderColor: '#3f3f46', borderWidth: 1 },
    },
    scales: {
        x: { ticks: { color: '#71717a', maxTicksLimit: 8, font: { size: 11 } }, grid: { color: '#27272a' } },
        y: { ticks: { color: '#71717a', font: { size: 11 } }, grid: { color: '#27272a' } },
    },
};

function makeDataset(label, key, color) {
    if (!trends.value) return { labels: [], datasets: [] };
    return {
        labels: trends.value.labels,
        datasets: [{
            label,
            data: trends.value.datasets[key] ?? [],
            borderColor: color,
            backgroundColor: color + '18',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        }],
    };
}

const charts = computed(() => [
    { label: 'Calories (kcal)',  key: 'calories', color: '#f59e0b' },
    { label: 'Water (ml)',       key: 'water_ml', color: '#3b82f6' },
    { label: 'Exercise (min)',   key: 'exercise', color: '#10b981' },
    { label: 'Sleep (h)',        key: 'sleep',    color: '#8b5cf6' },
    { label: 'Logs',             key: 'logs',     color: '#6b7280' },
    { label: 'Points earned',    key: 'points',   color: '#f97316' },
]);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Analytics</h1>
      <div class="flex gap-1.5">
        <button
          v-for="p in ['7d', '30d', '90d']"
          :key="p"
          @click="period = p; load()"
          :class="[
            'px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors',
            period === p ? 'bg-teal-500 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700',
          ]"
        >{{ p }}</button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-zinc-500">Loading…</div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div
        v-for="c in charts"
        :key="c.key"
        class="bg-zinc-900 border border-zinc-800 rounded-xl p-5"
      >
        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-4">{{ c.label }}</p>
        <div class="h-40">
          <Line :data="makeDataset(c.label, c.key, c.color)" :options="chartOpts" />
        </div>
      </div>
    </div>
  </div>
</template>

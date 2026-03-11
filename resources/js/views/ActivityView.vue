<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useApi } from '@/composables/useApi';

const { get } = useApi();
const recentLogs = ref([]);
const loading    = ref(true);

// Activity sub-sections
const sections = [
    {
        label:   'Food',
        icon:    '🍽',
        to:      '/food',
        desc:    'Log meals, snacks, and calorie intake',
        color:   'border-orange-500/30 bg-orange-500/5 hover:border-orange-500/60',
    },
    {
        label:   'Drinks',
        icon:    '💧',
        to:      '/drink',
        desc:    'Track water and beverage intake',
        color:   'border-blue-500/30 bg-blue-500/5 hover:border-blue-500/60',
    },
    {
        label:   'Exercise',
        icon:    '⚡',
        to:      '/exercise',
        desc:    'Record workouts and physical activity',
        color:   'border-green-500/30 bg-green-500/5 hover:border-green-500/60',
    },
    {
        label:   'Sleep',
        icon:    '🌙',
        to:      '/sleep',
        desc:    'Monitor sleep duration and quality',
        color:   'border-purple-500/30 bg-purple-500/5 hover:border-purple-500/60',
    },
];

async function fetchLogs() {
    loading.value = true;
    try {
        const { data } = await get('/activity-logs', { per_page: 30 });
        recentLogs.value = data.data ?? data;
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function typeIcon(log) {
    const slug = log.activity_type?.slug;
    if (slug === 'food')     return '🍽';
    if (slug === 'drink')    return '💧';
    if (slug === 'exercise') return '⚡';
    if (slug === 'sleep')    return '🌙';
    return '⭐';
}

onMounted(fetchLogs);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold">Activity Overview</h1>
      <p class="text-sm text-zinc-500 mt-0.5">Track food, drinks, exercise, and sleep from one place</p>
    </div>

    <!-- Section cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <RouterLink
        v-for="s in sections"
        :key="s.label"
        :to="s.to"
        :class="['group flex items-start gap-4 border rounded-xl p-5 transition-colors', s.color]"
      >
        <span class="text-3xl shrink-0 mt-0.5">{{ s.icon }}</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="font-semibold">{{ s.label }}</p>
            <RouterLink
              :to="`${s.to}?quickAdd=1`"
              class="w-7 h-7 rounded-full bg-zinc-700/60 hover:bg-teal-600 text-zinc-300 hover:text-white text-sm font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all"
              :title="`Log ${s.label}`"
              @click.stop
            >+</RouterLink>
          </div>
          <p class="text-sm text-zinc-400 mt-0.5">{{ s.desc }}</p>
        </div>
      </RouterLink>
    </div>

    <!-- Recent logs across all activity types -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Recent Activity Logs</h2>
      </div>
      <div v-if="loading" class="px-5 py-8 text-sm text-zinc-500 text-center">Loading…</div>
      <div v-else-if="!recentLogs.length" class="px-5 py-8 text-sm text-zinc-500 text-center">
        No activity logs yet. Start logging from one of the sections above.
      </div>
      <ul v-else class="divide-y divide-zinc-800">
        <li v-for="log in recentLogs" :key="log.id" class="flex items-center gap-4 px-5 py-3.5">
          <span class="text-lg shrink-0">{{ typeIcon(log) }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium">{{ log.activity_type?.name ?? 'Activity' }}</p>
            <p class="text-xs text-zinc-500">
              <span v-if="log.duration_minutes">{{ log.duration_minutes }} min</span>
              <span v-if="log.duration_minutes && log.calories"> · </span>
              <span v-if="log.calories">{{ log.calories }} kcal</span>
              <span v-if="log.quantity">{{ log.quantity }} {{ log.unit }}</span>
              <span v-if="log.notes && !log.duration_minutes && !log.calories && !log.quantity">{{ log.notes }}</span>
            </p>
          </div>
          <span class="text-xs text-zinc-600 shrink-0">{{ fmt(log.logged_at) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

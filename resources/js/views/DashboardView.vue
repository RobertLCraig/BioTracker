<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api     = useApi();
const dash    = ref(null);
const streak  = ref(null);
const points  = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const [dRes, sRes, pRes] = await Promise.all([
            api.get('/analytics/dashboard'),
            api.get('/streaks'),
            api.get('/points'),
        ]);
        dash.value   = dRes.data;
        streak.value = sRes.data.data;
        points.value = pRes.data;
    } finally {
        loading.value = false;
    }
});

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

    <div v-if="loading" class="text-sm text-zinc-500">Loading…</div>

    <template v-else>
      <!-- Top stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-2">Streak</p>
          <p class="text-3xl font-bold text-teal-400">{{ streak?.current_streak ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">days &nbsp;·&nbsp; best {{ streak?.longest_streak ?? 0 }}</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-2">Points</p>
          <p class="text-3xl font-bold text-amber-400">{{ points?.balance ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">total earned</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-2">Calories</p>
          <p class="text-3xl font-bold">{{ dash?.today_summary?.total_calories ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">kcal today</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-2">Logs today</p>
          <p class="text-3xl font-bold">{{ dash?.today_summary?.log_count ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">entries</p>
        </div>
      </div>

      <!-- Secondary metrics -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
          </div>
          <div>
            <p class="text-xs text-zinc-500">Water</p>
            <p class="text-base font-semibold">{{ ((dash?.today_summary?.total_water_ml ?? 0) / 1000).toFixed(1) }}L</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-green-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <div>
            <p class="text-xs text-zinc-500">Exercise</p>
            <p class="text-base font-semibold">{{ dash?.today_summary?.exercise_minutes ?? 0 }} min</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
          </div>
          <div>
            <p class="text-xs text-zinc-500">Sleep</p>
            <p class="text-base font-semibold">{{ dash?.today_summary?.sleep_hours ?? 0 }}h</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
          </div>
          <div>
            <p class="text-xs text-zinc-500">Points today</p>
            <p class="text-base font-semibold">{{ dash?.today_summary?.points_earned ?? 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Recent activity -->
      <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400 mb-4">Recent Activity</h2>
        <div v-if="!dash?.recent_logs?.length" class="text-sm text-zinc-500">No recent logs. Start tracking to see your activity here.</div>
        <ul v-else class="space-y-3">
          <li v-for="log in dash.recent_logs" :key="log.id" class="flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full bg-teal-500 shrink-0"></span>
            <span class="flex-1 text-sm">{{ log.activity_type?.name ?? 'Activity' }}</span>
            <span v-if="log.duration_minutes" class="text-xs text-zinc-500">{{ log.duration_minutes }} min</span>
            <span v-if="log.calories" class="text-xs text-zinc-500">{{ log.calories }} kcal</span>
            <span class="text-xs text-zinc-600">{{ fmt(log.logged_at) }}</span>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

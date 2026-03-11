<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useApi } from '@/composables/useApi';

const api    = useApi();
const router = useRouter();
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
        dash.value   = dRes.data.data;
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

function quickAdd(path, e) {
    e.preventDefault();
    e.stopPropagation();
    router.push({ path, query: { quickAdd: '1' } });
}

const kindDot = {
    activity:   'bg-teal-500',
    vital:      'bg-blue-500',
    symptom:    'bg-red-400',
    medication: 'bg-amber-400',
};

const sections = [
    { label: 'Food',        icon: '🍽',  route: '/food',        color: 'text-orange-400' },
    { label: 'Drinks',      icon: '💧',  route: '/drink',       color: 'text-blue-400'   },
    { label: 'Exercise',    icon: '⚡',  route: '/exercise',    color: 'text-green-400'  },
    { label: 'Sleep',       icon: '🌙',  route: '/sleep',       color: 'text-purple-400' },
    { label: 'Vitals',      icon: '🩺',  route: '/vitals',      color: 'text-red-400'    },
    { label: 'Medications', icon: '💊',  route: '/medications', color: 'text-amber-400'  },
    { label: 'Symptoms',    icon: '🩹',  route: '/symptoms',    color: 'text-pink-400'   },
    { label: 'Excretion',   icon: '💩',  route: '/excretion',   color: 'text-zinc-400'   },
];
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold">Dashboard</h1>

    <div v-if="loading" class="text-sm text-zinc-500">Loading…</div>

    <template v-else>
      <!-- ── Stats strip ─────────────────────────────────── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-1">Streak</p>
          <p class="text-3xl font-bold text-teal-400">{{ streak?.current_streak ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">days &nbsp;·&nbsp; best {{ streak?.longest_streak ?? 0 }}</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-1">Points</p>
          <p class="text-3xl font-bold text-amber-400">{{ points?.balance ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">total earned</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-1">Calories today</p>
          <p class="text-3xl font-bold">{{ dash?.today_summary?.total_calories ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">kcal</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
          <p class="text-xs text-zinc-500 uppercase tracking-wide font-medium mb-1">Today's logs</p>
          <p class="text-3xl font-bold">{{ dash?.today_summary?.log_count ?? 0 }}</p>
          <p class="text-xs text-zinc-500 mt-1">entries</p>
        </div>
      </div>

      <!-- ── Today at a glance ────────────────────────────── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 flex items-center gap-3">
          <span class="text-xl">💧</span>
          <div class="min-w-0">
            <p class="text-xs text-zinc-500">Water</p>
            <p class="text-sm font-semibold">{{ ((dash?.today_summary?.total_water_ml ?? 0) / 1000).toFixed(1) }} L</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 flex items-center gap-3">
          <span class="text-xl">⚡</span>
          <div class="min-w-0">
            <p class="text-xs text-zinc-500">Exercise</p>
            <p class="text-sm font-semibold">{{ dash?.today_summary?.exercise_minutes ?? 0 }} min</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 flex items-center gap-3">
          <span class="text-xl">🌙</span>
          <div class="min-w-0">
            <p class="text-xs text-zinc-500">Sleep</p>
            <p class="text-sm font-semibold">{{ dash?.today_summary?.sleep_hours ?? 0 }} h</p>
          </div>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 flex items-center gap-3">
          <span class="text-xl">🌟</span>
          <div class="min-w-0">
            <p class="text-xs text-zinc-500">Points today</p>
            <p class="text-sm font-semibold">{{ dash?.today_summary?.points_earned ?? 0 }}</p>
          </div>
        </div>
      </div>

      <!-- ── Quick-access cards ───────────────────────────── -->
      <div>
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 mb-3">Quick Log</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <RouterLink
            v-for="s in sections"
            :key="s.label"
            :to="s.route"
            class="group relative bg-zinc-900 border border-zinc-800 hover:border-zinc-600 rounded-xl p-4 flex items-center gap-3 transition-colors"
          >
            <span class="text-2xl">{{ s.icon }}</span>
            <span class="text-sm font-medium flex-1">{{ s.label }}</span>
            <!-- "+" quick-add button -->
            <button
              @click="quickAdd(s.route, $event)"
              class="w-6 h-6 rounded-full bg-zinc-700 hover:bg-teal-600 text-zinc-300 hover:text-white text-xs font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all shrink-0"
              :title="`Log ${s.label}`"
            >+</button>
          </RouterLink>
        </div>
      </div>

      <!-- ── Recent activity ─────────────────────────────── -->
      <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 mb-4">Recent Activity</h2>
        <div v-if="!dash?.recent_logs?.length" class="text-sm text-zinc-500">
          No recent logs. Start tracking to see your activity here.
        </div>
        <ul v-else class="divide-y divide-zinc-800">
          <li v-for="log in dash.recent_logs" :key="`${log.kind}-${log.id}`" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
            <span class="text-lg leading-none shrink-0">{{ log.icon }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate">{{ log.label }}</p>
              <p v-if="log.sub" class="text-xs text-zinc-500 truncate">{{ log.sub }}</p>
            </div>
            <span class="text-xs text-zinc-600 shrink-0">{{ fmt(log.logged_at) }}</span>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

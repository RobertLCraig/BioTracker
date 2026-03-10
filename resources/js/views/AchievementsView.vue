<script setup>
import { ref, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api          = useApi();
const achievements = ref([]);
const loading      = ref(true);

onMounted(async () => {
    const res = await api.get('/achievements');
    achievements.value = res.data.data;
    loading.value = false;
});

const unlocked = computed(() => achievements.value.filter(a => a.unlocked_at));
const locked   = computed(() => achievements.value.filter(a => !a.unlocked_at));

const tierStyle = {
    bronze: 'border-amber-700/40 bg-amber-700/5',
    silver: 'border-zinc-500/40 bg-zinc-500/5',
    gold:   'border-yellow-400/40 bg-yellow-400/5',
};

const tierBadge = {
    bronze: 'text-amber-600 bg-amber-600/10',
    silver: 'text-zinc-400 bg-zinc-400/10',
    gold:   'text-yellow-400 bg-yellow-400/10',
};
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-2">
      <h1 class="text-2xl font-bold">Achievements</h1>
      <p class="text-sm text-zinc-500">{{ unlocked.length }} / {{ achievements.length }} unlocked</p>
    </div>

    <!-- Progress bar -->
    <div class="h-1.5 bg-zinc-800 rounded-full mb-8 overflow-hidden">
      <div
        class="h-full bg-teal-500 rounded-full transition-all duration-500"
        :style="{ width: achievements.length ? `${(unlocked.length / achievements.length) * 100}%` : '0%' }"
      />
    </div>

    <div v-if="loading" class="text-sm text-zinc-500">Loading…</div>

    <template v-else>
      <!-- Unlocked -->
      <div v-if="unlocked.length" class="mb-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400 mb-4">Unlocked</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div
            v-for="a in unlocked"
            :key="a.id"
            :class="['border rounded-xl p-4 flex flex-col items-center text-center', tierStyle[a.tier] ?? 'border-zinc-800 bg-zinc-900']"
          >
            <div class="text-4xl mb-3 leading-none">{{ a.icon ?? '🏆' }}</div>
            <p class="text-sm font-semibold leading-tight mb-1">{{ a.name }}</p>
            <p class="text-xs text-zinc-500 leading-snug mb-3">{{ a.description }}</p>
            <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', tierBadge[a.tier] ?? '']">
              {{ a.tier }}
            </span>
          </div>
        </div>
      </div>

      <!-- Locked -->
      <div v-if="locked.length">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400 mb-4">Locked</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div
            v-for="a in locked"
            :key="a.id"
            class="border border-zinc-800 rounded-xl p-4 flex flex-col items-center text-center opacity-40"
          >
            <div class="text-4xl mb-3 leading-none grayscale">{{ a.icon ?? '🔒' }}</div>
            <p class="text-sm font-semibold leading-tight mb-1">{{ a.name }}</p>
            <p class="text-xs text-zinc-500 leading-snug mb-3">{{ a.description }}</p>
            <span class="text-xs text-zinc-600 capitalize">{{ a.tier }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '@/composables/useApi';
import PhotoDropzone from '@/components/PhotoDropzone.vue';

const { get, post, postForm, del } = useApi();
const route = useRoute();

const logs       = ref([]);
const loading    = ref(false);
const submitting = ref(false);
const error      = ref('');
const showForm   = ref(false);
const typeId     = ref(null);
const photos     = ref([]);

const EXERCISE_TYPES = [
    'Running', 'Cycling', 'Swimming', 'Walking', 'Hiking',
    'Weight training', 'HIIT', 'Yoga', 'Pilates', 'Rowing',
    'Boxing', 'Football', 'Tennis', 'Basketball', 'Climbing',
    'CrossFit', 'Stretching', 'Other',
];

const blank = () => ({
    exercise_name: '',
    duration:      '',
    calories:      '',
    notes:         '',
    logged_at:     new Date().toISOString().slice(0, 16),
});
const form = ref(blank());

async function fetchType() {
    try {
        const { data } = await get('/activity-types');
        const t = (data.data ?? data).find(x => x.slug === 'exercise');
        if (t) typeId.value = t.id;
    } catch { /* ignore */ }
}

async function fetchLogs() {
    loading.value = true;
    try {
        const { data } = await get('/activity-logs', { per_page: 100 });
        logs.value = (data.data ?? data).filter(
            l => l.activity_type?.slug === 'exercise' || l.activity_type_id === typeId.value
        );
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!typeId.value) {
        error.value = 'Exercise activity type not found. Please re-seed the database.';
        return;
    }
    error.value  = '';
    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('activity_type_id', typeId.value);
        fd.append('logged_at', form.value.logged_at);
        if (form.value.duration) fd.append('duration_minutes', form.value.duration);
        if (form.value.calories) fd.append('calories', form.value.calories);
        const notes = [
            form.value.exercise_name,
            form.value.notes,
        ].filter(Boolean).join(' — ');
        if (notes) fd.append('notes', notes);
        if (form.value.exercise_name) fd.append('metadata[exercise_name]', form.value.exercise_name);
        photos.value.forEach(f => fd.append('photos[]', f));
        await postForm('/activity-logs', fd);
        showForm.value = false;
        form.value = blank();
        photos.value = [];
        await fetchLogs();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Save failed.';
    } finally {
        submitting.value = false;
    }
}

async function remove(id) {
    if (!confirm('Delete this log?')) return;
    await del(`/activity-logs/${id}`);
    await fetchLogs();
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function exerciseName(log) {
    return log.metadata?.exercise_name
        || (log.notes?.split(' — ')[0])
        || '—';
}

onMounted(async () => {
    await fetchType();
    await fetchLogs();
    if (route.query.quickAdd === '1') showForm.value = true;
});
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Exercise</h1>
        <p class="text-sm text-zinc-500 mt-0.5">Log workouts and physical activity</p>
      </div>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + Log Exercise
      </button>
    </div>

    <!-- Form -->
    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-4">New Exercise Log</h2>
      <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" autocomplete="off">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Exercise type</label>
          <select v-model="form.exercise_name" class="input-field">
            <option value="">Select or type below…</option>
            <option v-for="t in EXERCISE_TYPES" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Date & time *</label>
          <input v-model="form.logged_at" type="datetime-local" required autocomplete="off" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Duration (minutes)</label>
          <input v-model="form.duration" type="number" min="1" placeholder="30" autocomplete="off" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Calories burned</label>
          <input v-model="form.calories" type="number" min="0" placeholder="250" autocomplete="off" class="input-field" />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="e.g. 5k run in the park" autocomplete="off" class="input-field" />
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Photos <span class="text-zinc-600">(optional)</span></label>
          <PhotoDropzone v-model="photos" />
        </div>
        <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
        <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
          <button type="submit" :disabled="submitting"
            class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
            {{ submitting ? 'Saving…' : 'Save' }}
          </button>
          <button type="button" @click="showForm = false"
            class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- History -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-zinc-800">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Exercise History</h2>
      </div>
      <div v-if="loading" class="px-5 py-8 text-sm text-zinc-500 text-center">Loading…</div>
      <div v-else-if="!logs.length" class="px-5 py-8 text-sm text-zinc-500 text-center">No exercise logs yet.</div>
      <ul v-else class="divide-y divide-zinc-800">
        <li v-for="log in logs" :key="log.id" class="flex items-center gap-4 px-5 py-3.5">
          <span class="text-xl shrink-0">⚡</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium">{{ exerciseName(log) }}</p>
            <p class="text-xs text-zinc-500">
              <span v-if="log.duration_minutes">{{ log.duration_minutes }} min</span>
              <span v-if="log.duration_minutes && log.calories"> · </span>
              <span v-if="log.calories">{{ log.calories }} kcal</span>
            </p>
          </div>
          <span class="text-xs text-zinc-600 shrink-0">{{ fmt(log.logged_at) }}</span>
          <button @click="remove(log.id)" class="text-zinc-600 hover:text-red-400 text-xs transition-colors shrink-0">✕</button>
        </li>
      </ul>
    </div>
  </div>
</template>

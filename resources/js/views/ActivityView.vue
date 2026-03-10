<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import LogList from '@/components/LogList.vue';

const api      = useApi();
const logs     = ref([]);
const types    = ref([]);
const loading  = ref(true);
const showForm = ref(false);
const saving   = ref(false);
const error    = ref('');

const blank = () => ({ activity_type_id: '', logged_at: new Date().toISOString().slice(0, 16), duration_minutes: '', quantity: '', unit: '', calories: '', notes: '' });
const form  = ref(blank());

const columns = [
    { key: 'logged_at',        label: 'Date',     format: r => new Date(r.logged_at).toLocaleString() },
    { key: 'type',             label: 'Type',     format: r => r.activity_type?.name ?? '—' },
    { key: 'duration_minutes', label: 'Duration', format: r => r.duration_minutes ? `${r.duration_minutes} min` : '—' },
    { key: 'quantity',         label: 'Qty',      format: r => r.quantity ? `${r.quantity} ${r.unit ?? ''}`.trim() : '—' },
    { key: 'calories',         label: 'Calories', format: r => r.calories ?? '—' },
    { key: 'notes',            label: 'Notes',    format: r => r.notes ?? '—' },
];

async function load() {
    loading.value = true;
    const [lRes, tRes] = await Promise.all([api.get('/activity-logs'), api.get('/activity-types')]);
    logs.value  = lRes.data.data;
    types.value = tRes.data.data;
    loading.value = false;
}

async function save() {
    error.value  = '';
    saving.value = true;
    try {
        const payload = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== ''));
        await api.post('/activity-logs', payload);
        showForm.value = false;
        form.value = blank();
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Save failed.';
    } finally {
        saving.value = false;
    }
}

async function remove(id) {
    if (!confirm('Delete this log?')) return;
    await api.del(`/activity-logs/${id}`);
    await load();
}

onMounted(load);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Activity Logs</h1>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + New Log
      </button>
    </div>

    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <h2 class="font-semibold mb-4 text-sm text-zinc-400 uppercase tracking-wide">Log Activity</h2>
      <form @submit.prevent="save" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Type *</label>
          <select v-model="form.activity_type_id" required
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            <option value="">Select type…</option>
            <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Date & time *</label>
          <input v-model="form.logged_at" type="datetime-local" required
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Duration (min)</label>
          <input v-model="form.duration_minutes" type="number" min="0" placeholder="30"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Quantity</label>
          <input v-model="form.quantity" type="number" step="0.01" placeholder="1.5"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Unit</label>
          <input v-model="form.unit" type="text" placeholder="ml, kg, reps…"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Calories</label>
          <input v-model="form.calories" type="number" min="0" placeholder="250"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="Optional notes…"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
        <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
          <button type="submit" :disabled="saving"
            class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <button type="button" @click="showForm = false"
            class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <LogList :logs="logs" :loading="loading" :columns="columns" @delete="remove" />
  </div>
</template>

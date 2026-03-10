<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import LogList from '@/components/LogList.vue';

const api      = useApi();
const logs     = ref([]);
const loading  = ref(true);
const showForm = ref(false);
const saving   = ref(false);
const error    = ref('');

const blank = () => ({ symptom: '', severity: 5, body_area: '', logged_at: new Date().toISOString().slice(0, 16), duration_minutes: '', notes: '' });
const form  = ref(blank());

const columns = [
    { key: 'logged_at',        label: 'Date',     format: r => new Date(r.logged_at).toLocaleString() },
    { key: 'symptom',          label: 'Symptom' },
    { key: 'severity',         label: 'Severity', format: r => `${r.severity}/10` },
    { key: 'body_area',        label: 'Area',     format: r => r.body_area ?? '—' },
    { key: 'duration_minutes', label: 'Duration', format: r => r.duration_minutes ? `${r.duration_minutes} min` : '—' },
    { key: 'notes',            label: 'Notes',    format: r => r.notes ?? '—' },
];

async function load() {
    loading.value = true;
    const res = await api.get('/symptom-logs');
    logs.value = res.data.data;
    loading.value = false;
}

async function save() {
    error.value  = '';
    saving.value = true;
    try {
        const payload = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== ''));
        await api.post('/symptom-logs', payload);
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
    await api.del(`/symptom-logs/${id}`);
    await load();
}

onMounted(load);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Symptoms</h1>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + Log Symptom
      </button>
    </div>

    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <form @submit.prevent="save" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Symptom *</label>
          <input v-model="form.symptom" type="text" required placeholder="e.g. Headache" autofocus
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Severity: <span class="text-zinc-100 font-semibold">{{ form.severity }}</span>/10</label>
          <input v-model="form.severity" type="range" min="1" max="10" class="w-full h-2 accent-teal-500 mt-2" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Body area</label>
          <input v-model="form.body_area" type="text" placeholder="Head, Chest, Abdomen…"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
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
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="Optional"
            class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
        <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">{{ saving ? 'Saving…' : 'Save' }}</button>
          <button type="button" @click="showForm = false" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">Cancel</button>
        </div>
      </form>
    </div>

    <LogList :logs="logs" :loading="loading" :columns="columns" @delete="remove" />
  </div>
</template>

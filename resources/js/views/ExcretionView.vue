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

const blank = () => ({ type: 'poop', size: '', consistency: '', colour: '', has_blood: false, blood_amount: 'none', urgency: '', pain_level: '', logged_at: new Date().toISOString().slice(0, 16), notes: '' });
const form  = ref(blank());

const columns = [
    { key: 'logged_at',   label: 'Date',     format: r => new Date(r.logged_at).toLocaleString() },
    { key: 'type',        label: 'Type',     format: r => r.type?.charAt(0).toUpperCase() + r.type?.slice(1) },
    { key: 'size',        label: 'Size',     format: r => r.size ?? '—' },
    { key: 'consistency', label: 'Bristol',  format: r => r.consistency != null ? `Type ${r.consistency}` : '—' },
    { key: 'colour',      label: 'Colour',   format: r => r.colour ?? '—' },
    { key: 'has_blood',   label: 'Blood',    format: r => r.has_blood ? '⚠ Yes' : 'No' },
    { key: 'urgency',     label: 'Urgency',  format: r => r.urgency ? `${r.urgency}/5` : '—' },
    { key: 'pain_level',  label: 'Pain',     format: r => r.pain_level != null ? `${r.pain_level}/10` : '—' },
];

async function load() {
    loading.value = true;
    const res = await api.get('/excretion-logs');
    logs.value = res.data.data;
    loading.value = false;
}

async function save() {
    error.value  = '';
    saving.value = true;
    try {
        const payload = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== ''));
        await api.post('/excretion-logs', payload);
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
    if (!confirm('Delete this entry?')) return;
    await api.del(`/excretion-logs/${id}`);
    await load();
}

onMounted(load);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Excretion Log</h1>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + New Entry
      </button>
    </div>

    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <form @submit.prevent="save" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Type *</label>
          <select v-model="form.type" required class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            <option value="poop">Poop</option>
            <option value="pee">Pee</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Date & time *</label>
          <input v-model="form.logged_at" type="datetime-local" required class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <template v-if="form.type === 'poop'">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Size</label>
            <select v-model="form.size" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">—</option>
              <option value="small">Small</option>
              <option value="medium">Medium</option>
              <option value="large">Large</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Bristol Scale (1–7)</label>
            <input v-model="form.consistency" type="number" min="1" max="7" placeholder="4" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
        </template>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Colour</label>
          <input v-model="form.colour" type="text" placeholder="Brown" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Urgency (1–5)</label>
          <input v-model="form.urgency" type="number" min="1" max="5" placeholder="3" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Pain level (0–10)</label>
          <input v-model="form.pain_level" type="number" min="0" max="10" placeholder="0" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div class="flex items-center gap-2.5 pt-5">
          <input v-model="form.has_blood" type="checkbox" id="blood" class="w-4 h-4 accent-red-500" />
          <label for="blood" class="text-sm cursor-pointer">Blood present</label>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="Optional" class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
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

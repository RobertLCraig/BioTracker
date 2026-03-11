<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '@/composables/useApi';
import LogList from '@/components/LogList.vue';

const { get, post, del } = useApi();
const route = useRoute();
const logs     = ref([]);
const loading  = ref(true);
const showForm = ref(false);
const saving   = ref(false);
const error    = ref('');

const TYPES = [
    { value: 'weight',         label: 'Weight',         unit: 'kg' },
    { value: 'blood_pressure', label: 'Blood Pressure',  unit: 'mmHg' },
    { value: 'temperature',    label: 'Temperature',     unit: '°C' },
    { value: 'heart_rate',     label: 'Heart Rate',      unit: 'bpm' },
    { value: 'blood_sugar',    label: 'Blood Sugar',     unit: 'mmol/L' },
    { value: 'spo2',           label: 'SpO₂',           unit: '%' },
];

const blank = () => ({ type: '', value: '', secondary_value: '', unit: '', logged_at: new Date().toISOString().slice(0, 16), source: 'manual', notes: '' });
const form  = ref(blank());

function onTypeChange() {
    const t = TYPES.find(t => t.value === form.value.type);
    form.value.unit = t?.unit ?? '';
}

const columns = [
    { key: 'logged_at',      label: 'Date',   format: r => new Date(r.logged_at).toLocaleString() },
    { key: 'type',           label: 'Type',   format: r => TYPES.find(t => t.value === r.type)?.label ?? r.type },
    { key: 'value',          label: 'Value',  format: r => r.secondary_value ? `${r.value} / ${r.secondary_value}` : r.value },
    { key: 'unit',           label: 'Unit' },
    { key: 'source',         label: 'Source', format: r => r.source ?? 'manual' },
];

async function load() {
    loading.value = true;
    const res = await get('/vital-logs');
    logs.value = res.data.data;
    loading.value = false;
}

async function save() {
    error.value  = '';
    saving.value = true;
    try {
        const payload = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== ''));
        await post('/vital-logs', payload);
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
    if (!confirm('Delete?')) return;
    await del(`/vital-logs/${id}`);
    await load();
}

onMounted(async () => {
    await load();
    if (route.query.quickAdd === '1') showForm.value = true;
});
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Vital Signs</h1>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + New Reading
      </button>
    </div>

    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <form @submit.prevent="save" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" autocomplete="off">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Type *</label>
          <select v-model="form.type" required @change="onTypeChange"
            class="input-field" autocomplete="off">
            <option value="">Select type…</option>
            <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Value *</label>
          <input v-model="form.value" type="number" step="0.01" required placeholder="120"
            class="input-field" autocomplete="off" />
        </div>
        <div v-if="form.type === 'blood_pressure'">
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Diastolic</label>
          <input v-model="form.secondary_value" type="number" step="0.01" placeholder="80"
            class="input-field" autocomplete="off" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Unit</label>
          <input v-model="form.unit" type="text" placeholder="bpm"
            class="input-field" autocomplete="off" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Date & time *</label>
          <input v-model="form.logged_at" type="datetime-local" required
            class="input-field" autocomplete="off" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="Optional"
            class="input-field" autocomplete="off" />
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

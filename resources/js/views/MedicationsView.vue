<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import LogList from '@/components/LogList.vue';

const api         = useApi();
const meds        = ref([]);
const logs        = ref([]);
const loading     = ref(true);
const showMedForm = ref(false);
const showLogForm = ref(false);
const saving      = ref(false);
const error       = ref('');

const blankMed = () => ({ name: '', dosage: '', frequency: '', prescribed_by: '', notes: '' });
const blankLog = () => ({ medication_id: '', taken_at: new Date().toISOString().slice(0, 16), dosage_taken: '', notes: '' });
const medForm  = ref(blankMed());
const logForm  = ref(blankLog());

const medColumns = [
    { key: 'name',          label: 'Medication' },
    { key: 'dosage',        label: 'Dosage',     format: r => r.dosage ?? '—' },
    { key: 'frequency',     label: 'Frequency',  format: r => r.frequency ?? '—' },
    { key: 'prescribed_by', label: 'Prescriber', format: r => r.prescribed_by ?? '—' },
];

const logColumns = [
    { key: 'taken_at',    label: 'Date',       format: r => new Date(r.taken_at).toLocaleString() },
    { key: 'medication',  label: 'Medication', format: r => r.medication?.name ?? '—' },
    { key: 'dosage_taken', label: 'Dose',      format: r => r.dosage_taken ?? '—' },
    { key: 'notes',       label: 'Notes',      format: r => r.notes ?? '—' },
];

async function load() {
    loading.value = true;
    const [mRes, lRes] = await Promise.all([api.get('/medications'), api.get('/medication-logs')]);
    meds.value = mRes.data.data;
    logs.value = lRes.data.data;
    loading.value = false;
}

async function saveMed() {
    error.value  = '';
    saving.value = true;
    try {
        const p = Object.fromEntries(Object.entries(medForm.value).filter(([, v]) => v !== ''));
        await api.post('/medications', p);
        showMedForm.value = false;
        medForm.value = blankMed();
        await load();
    } catch (e) { error.value = e.response?.data?.message ?? 'Save failed.'; }
    finally { saving.value = false; }
}

async function saveLog() {
    error.value  = '';
    saving.value = true;
    try {
        const p = Object.fromEntries(Object.entries(logForm.value).filter(([, v]) => v !== ''));
        await api.post('/medication-logs', p);
        showLogForm.value = false;
        logForm.value = blankLog();
        await load();
    } catch (e) { error.value = e.response?.data?.message ?? 'Save failed.'; }
    finally { saving.value = false; }
}

async function removeMed(id) {
    if (!confirm('Delete this medication and all its dose logs?')) return;
    await api.del(`/medications/${id}`);
    await load();
}

async function removeLog(id) {
    if (!confirm('Delete this dose log?')) return;
    await api.del(`/medication-logs/${id}`);
    await load();
}

onMounted(load);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold">Medications</h1>

    <!-- Medications list -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-zinc-300">My Medications</h2>
        <button @click="showMedForm = !showMedForm"
          class="px-3 py-1.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-semibold rounded-lg transition-colors">
          + Add Medication
        </button>
      </div>

      <div v-if="showMedForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-4">
        <form @submit.prevent="saveMed" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Name *</label>
            <input v-model="medForm.name" type="text" required placeholder="Metformin" autofocus
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Dosage</label>
            <input v-model="medForm.dosage" type="text" placeholder="500mg"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Frequency</label>
            <input v-model="medForm.frequency" type="text" placeholder="Twice daily"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Prescribed by</label>
            <input v-model="medForm.prescribed_by" type="text" placeholder="Dr Smith"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
          <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
            <button type="submit" :disabled="saving" class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">{{ saving ? 'Saving…' : 'Save' }}</button>
            <button type="button" @click="showMedForm = false" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">Cancel</button>
          </div>
        </form>
      </div>

      <LogList :logs="meds" :loading="loading" :columns="medColumns" @delete="removeMed" />
    </section>

    <!-- Dose history -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-zinc-300">Dose History</h2>
        <button @click="showLogForm = !showLogForm"
          class="px-3 py-1.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-semibold rounded-lg transition-colors">
          + Log Dose
        </button>
      </div>

      <div v-if="showLogForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-4">
        <form @submit.prevent="saveLog" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Medication *</label>
            <select v-model="logForm.medication_id" required
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Select…</option>
              <option v-for="m in meds" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Taken at *</label>
            <input v-model="logForm.taken_at" type="datetime-local" required
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Dose taken</label>
            <input v-model="logForm.dosage_taken" type="text" placeholder="500mg"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
          <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
            <button type="submit" :disabled="saving" class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">{{ saving ? 'Saving…' : 'Log' }}</button>
            <button type="button" @click="showLogForm = false" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">Cancel</button>
          </div>
        </form>
      </div>

      <LogList :logs="logs" :loading="loading" :columns="logColumns" @delete="removeLog" />
    </section>
  </div>
</template>

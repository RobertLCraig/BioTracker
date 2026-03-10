<script setup>
import { ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const error   = ref('');
const format  = ref('pdf');
const types   = ref([]);

// Default: last 30 days
const today = new Date();
const ago30 = new Date(today); ago30.setDate(ago30.getDate() - 30);
const from  = ref(ago30.toISOString().slice(0, 10));
const to    = ref(today.toISOString().slice(0, 10));

const ALL_TYPES = [
    { value: 'activity',    label: 'Activity' },
    { value: 'vitals',      label: 'Vitals' },
    { value: 'symptoms',    label: 'Symptoms' },
    { value: 'medications', label: 'Medications' },
    { value: 'excretion',   label: 'Excretion' },
];

function toggleType(t) {
    const i = types.value.indexOf(t);
    i === -1 ? types.value.push(t) : types.value.splice(i, 1);
}

async function download() {
    if (!from.value || !to.value) return;
    error.value   = '';
    loading.value = true;
    try {
        const payload = {
            from:   from.value,
            to:     to.value,
            format: format.value,
        };
        if (types.value.length) payload['types'] = types.value;

        const res = await axios.post('/api/v1/reports/export', payload, { responseType: 'blob' });

        const url  = URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement('a');
        link.href  = url;
        link.download = `biotracker-report-${from.value}-${to.value}.${format.value}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch {
        error.value = 'Export failed. Please check the date range and try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div class="p-6 max-w-xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Export Report</h1>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 space-y-6">
      <!-- Date range -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-3">Date Range</label>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-zinc-500 mb-1">From</label>
            <input v-model="from" type="date"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label class="block text-xs text-zinc-500 mb-1">To</label>
            <input v-model="to" type="date"
              class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
        </div>
      </div>

      <!-- Format -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-3">Format</label>
        <div class="flex gap-4">
          <label v-for="f in ['pdf', 'csv']" :key="f"
            :class="['flex items-center gap-2.5 cursor-pointer px-4 py-2.5 rounded-lg border transition-colors', format === f ? 'border-teal-500/60 bg-teal-500/10' : 'border-zinc-700 hover:border-zinc-600']"
          >
            <input v-model="format" type="radio" :value="f" class="accent-teal-500" />
            <span class="text-sm font-semibold uppercase">{{ f }}</span>
            <span class="text-xs text-zinc-500">{{ f === 'pdf' ? 'Formatted report' : 'Spreadsheet data' }}</span>
          </label>
        </div>
      </div>

      <!-- Data types -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-1">Data to include</label>
        <p class="text-xs text-zinc-600 mb-3">Leave all unchecked to include everything.</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="t in ALL_TYPES"
            :key="t.value"
            @click="toggleType(t.value)"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors',
              types.includes(t.value)
                ? 'bg-teal-500/15 border-teal-500/50 text-teal-300'
                : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:border-zinc-500',
            ]"
          >
            {{ t.label }}
          </button>
        </div>
      </div>

      <div v-if="error" class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>

      <button
        @click="download"
        :disabled="loading || !from || !to"
        class="w-full py-3 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
      >
        <svg v-if="!loading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span>{{ loading ? 'Generating…' : `Download ${format.toUpperCase()}` }}</span>
      </button>
    </div>
  </div>
</template>

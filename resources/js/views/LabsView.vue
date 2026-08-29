<script setup>
import { ref, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement,
    LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const { get, postForm } = useApi();

const results  = ref([]);
const total    = ref(0);      // server-side total; the index pages at 50
const panels   = ref({});     // id → panel
const series   = ref([]);     // analyte picker options
const trend    = ref(null);   // the selected analyte's series
const testKey  = ref('');
const loading  = ref(true);
const charting = ref(false);

const showImport = ref(false);
const file       = ref(null);
const uploading  = ref(false);
const importMsg  = ref('');
const importErr  = ref('');

// Rendering for each derived out-of-range flag (App\Enums\AbnormalFlag).
// normal/unknown are absent on purpose: a normal row gets no badge.
const FLAGS = {
    high:          { label: 'High',     arrow: '▲', badge: 'bg-amber-500/15 text-amber-300 border-amber-500/40', row: 'border-l-amber-500' },
    low:           { label: 'Low',      arrow: '▼', badge: 'bg-sky-500/15 text-sky-300 border-sky-500/40',       row: 'border-l-sky-500' },
    critical_high: { label: 'Very high', arrow: '▲', badge: 'bg-red-500/15 text-red-300 border-red-500/40',      row: 'border-l-red-500' },
    critical_low:  { label: 'Very low', arrow: '▼', badge: 'bg-red-500/15 text-red-300 border-red-500/40',       row: 'border-l-red-500' },
    abnormal:      { label: 'Abnormal', arrow: '!', badge: 'bg-amber-500/15 text-amber-300 border-amber-500/40', row: 'border-l-amber-500' },
};

const flagOf = (r) => FLAGS[r.abnormal_flag] ?? null;

// LabResult casts value_numeric/range_* as decimal:4, so the API sends "0.8000".
// Trim that back to what a lab report would print.
const num = (v) => (v === null || v === undefined || v === '' ? null : String(Number(v)));

function valueOf(r) {
    const n = num(r.value.numeric);
    // value_text is the lab's own display string and already carries the unit,
    // so only the numeric branch appends one.
    if (n === null) return r.value.text || '—';
    return `${r.value.comparator ?? ''}${n}${r.value.unit ? ' ' + r.value.unit : ''}`;
}

function rangeOf(r) {
    if (r.range.text) return r.range.text;
    const low = num(r.range.low);
    const high = num(r.range.high);
    if (low !== null && high !== null) return `${low} – ${high}`;
    if (high !== null) return `≤ ${high}`;
    if (low !== null) return `≥ ${low}`;
    return '—';
}

const dateOf = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

/**
 * Group the newest-first result list by panel, keeping first-seen order so the
 * groups stay newest-first too. Results with no lab_order_id carry no panel
 * (see LabResultImporter::resolvePanel), so they fall into a trailing bucket.
 */
const groups = computed(() => {
    const out = [];
    const byId = new Map();
    for (const r of results.value) {
        const id = r.lab_panel_id ?? 'none';
        if (!byId.has(id)) {
            const p = panels.value[r.lab_panel_id];
            byId.set(id, {
                id,
                title: p?.name || p?.performing_org || (p ? `Lab order ${p.lab_order_id}` : 'Not part of a panel'),
                date: p?.collected_at ?? r.sampled_at,
                rows: [],
            });
            out.push(byId.get(id));
        }
        byId.get(id).rows.push(r);
    }
    return out;
});

async function load() {
    loading.value = true;
    const [res, pan, ser] = await Promise.all([
        get('/lab-results'),
        get('/lab-panels'),
        get('/lab-results/trends'),
    ]);
    results.value = res.data.data;
    total.value = res.data.meta?.total ?? res.data.data.length;
    panels.value = Object.fromEntries(pan.data.data.map(p => [p.id, p]));
    series.value = ser.data.data.series;
    loading.value = false;

    if (series.value.length && !testKey.value) {
        testKey.value = series.value[0].test_key;
        await loadTrend();
    }
}

async function loadTrend() {
    if (!testKey.value) { trend.value = null; return; }
    charting.value = true;
    const res = await get('/lab-results/trends', { test_key: testKey.value });
    trend.value = res.data.data;
    charting.value = false;
}

// AC #5: text-only results have no numeric value and are dropped from the chart.
const chartPoints = computed(() => (trend.value?.points ?? []).filter(p => p.value !== null));

const chartData = computed(() => {
    const pts = chartPoints.value;
    return {
        labels: pts.map(p => dateOf(p.sampled_at)),
        datasets: [
            {
                label: trend.value?.name ?? 'Value',
                data: pts.map(p => p.value),
                borderColor: '#14b8a6',
                backgroundColor: '#14b8a6',
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
                order: 0,
            },
            // The two range datasets draw the shaded reference band: the high
            // line fills down to the low line that follows it.
            {
                label: 'Range high',
                data: pts.map(p => p.range_high),
                borderColor: '#3f3f46',
                borderDash: [4, 4],
                borderWidth: 1,
                pointRadius: 0,
                fill: '+1',
                backgroundColor: 'rgba(20, 184, 166, 0.08)',
                order: 1,
            },
            {
                label: 'Range low',
                data: pts.map(p => p.range_low),
                borderColor: '#3f3f46',
                borderDash: [4, 4],
                borderWidth: 1,
                pointRadius: 0,
                fill: false,
                order: 2,
            },
        ],
    };
});

const chartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#18181b', titleColor: '#e4e4e7', bodyColor: '#a1a1aa', borderColor: '#3f3f46', borderWidth: 1 },
    },
    scales: {
        x: { ticks: { color: '#71717a', maxTicksLimit: 8, font: { size: 11 } }, grid: { color: '#27272a' } },
        y: { ticks: { color: '#71717a', font: { size: 11 } }, grid: { color: '#27272a' } },
    },
};

async function upload() {
    if (!file.value) return;
    importMsg.value = '';
    importErr.value = '';
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file.value);
        const res = await postForm('/lab-results/import', fd);
        const d = res.data;
        importMsg.value = d.queued
            ? d.message
            : `Imported ${d.panels} panel${d.panels === 1 ? '' : 's'} and ${d.results_created} result${d.results_created === 1 ? '' : 's'}`
              + (d.results_updated ? `, ${d.results_updated} updated.` : '.');
        file.value = null;
        await load();
    } catch (e) {
        importErr.value = e.response?.data?.message ?? 'Import failed.';
    } finally {
        uploading.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Lab Results</h1>
      <button @click="showImport = !showImport"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        Import
      </button>
    </div>

    <!-- Import -->
    <div v-if="showImport" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-1">Patients Know Best export</label>
      <p class="text-xs text-zinc-600 mb-3">The <code>pkb-tests.json</code> file from the capture snippet. Re-importing the same file changes nothing.</p>
      <div class="flex flex-wrap gap-3 items-center">
        <input type="file" accept=".json,application/json" @change="file = $event.target.files[0] ?? null"
          class="text-sm text-zinc-400 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-zinc-800 file:text-zinc-300 file:text-sm" />
        <button @click="upload" :disabled="!file || uploading"
          class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
          {{ uploading ? 'Importing…' : 'Upload' }}
        </button>
      </div>
      <p v-if="importMsg" class="mt-3 text-sm text-teal-300 bg-teal-400/10 rounded-lg px-3 py-2">{{ importMsg }}</p>
      <p v-if="importErr" class="mt-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ importErr }}</p>
    </div>

    <div v-if="loading" class="text-sm text-zinc-500">Loading…</div>

    <!-- Empty state (AC #6) -->
    <div v-else-if="!results.length" class="bg-zinc-900 border border-zinc-800 rounded-xl p-10 text-center">
      <p class="text-sm font-medium text-zinc-300 mb-1.5">No lab results yet</p>
      <p class="text-sm text-zinc-500 mb-5">Upload a Patients Know Best JSON export and your panels, values and reference ranges appear here.</p>
      <button @click="showImport = true"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        Import results
      </button>
    </div>

    <template v-else>
      <!-- Trend chart -->
      <div v-if="series.length" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Trend</p>
          <select v-model="testKey" @change="loadTrend" class="input-field w-auto min-w-56">
            <option v-for="s in series" :key="s.test_key" :value="s.test_key">
              {{ s.name }} ({{ s.count }})
            </option>
          </select>
        </div>
        <div v-if="charting" class="h-56 flex items-center justify-center text-sm text-zinc-500">Loading…</div>
        <div v-else-if="!chartPoints.length" class="h-56 flex items-center justify-center text-sm text-zinc-500 text-center px-6">
          This test has no numeric values to chart. Its results are listed below.
        </div>
        <template v-else>
          <div class="h-56"><Line :data="chartData" :options="chartOpts" /></div>
          <p class="mt-3 text-xs text-zinc-600">
            Shaded band = reference range{{ trend?.unit ? ` · values in ${trend.unit}` : '' }}
          </p>
        </template>
      </div>

      <!-- Results, newest panel first -->
      <p v-if="total > results.length" class="text-xs text-zinc-500 mb-3">
        Showing the newest {{ results.length }} of {{ total }} results.
      </p>
      <div v-for="g in groups" :key="g.id" class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden mb-5">
        <div class="flex items-baseline justify-between gap-3 px-4 py-3 border-b border-zinc-800">
          <p class="text-sm font-semibold text-zinc-200 truncate">{{ g.title }}</p>
          <p class="text-xs text-zinc-500 shrink-0">{{ dateOf(g.date) }}</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-zinc-800">
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Test</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap">Value</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap">Reference range</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Flag</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in g.rows"
                :key="r.id"
                :class="[
                  'border-b border-zinc-800/50 last:border-0 border-l-2',
                  flagOf(r) ? `${flagOf(r).row} bg-zinc-800/20` : 'border-l-transparent',
                ]"
              >
                <td class="px-4 py-2.5 text-zinc-300">{{ r.test_name }}</td>
                <td class="px-4 py-2.5 text-zinc-100 font-medium whitespace-nowrap">{{ valueOf(r) }}</td>
                <td class="px-4 py-2.5 text-zinc-500 whitespace-nowrap">{{ rangeOf(r) }}</td>
                <td class="px-4 py-2.5">
                  <span v-if="flagOf(r)" :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-xs font-semibold', flagOf(r).badge]">
                    <span aria-hidden="true">{{ flagOf(r).arrow }}</span>{{ flagOf(r).label }}
                  </span>
                  <span v-else class="text-xs text-zinc-600">{{ r.abnormal_flag === 'normal' ? 'Normal' : '—' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

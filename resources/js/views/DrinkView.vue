<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '@/composables/useApi';
import PhotoDropzone from '@/components/PhotoDropzone.vue';

const { get, postForm, del } = useApi();
const route = useRoute();

const logs       = ref([]);
const loading    = ref(false);
const submitting = ref(false);
const error      = ref('');
const showForm   = ref(false);
const typeId     = ref(null);
const photos     = ref([]);

const DRINK_TYPES = ['Water', 'Coffee', 'Tea', 'Green tea', 'Juice', 'Smoothie', 'Milk', 'Sports drink', 'Protein shake', 'Alcohol', 'Other'];
const UNITS = ['ml', 'L', 'oz', 'fl oz', 'cup', 'glass', 'bottle'];

const blank = () => ({
    drink_type: 'Water',
    quantity:   '250',
    unit:       'ml',
    notes:      '',
    logged_at:  new Date().toISOString().slice(0, 16),
});
const form = ref(blank());

async function fetchType() {
    try {
        const { data } = await get('/activity-types');
        const t = (data.data ?? data).find(x => x.slug === 'drink');
        if (t) typeId.value = t.id;
    } catch { /* ignore */ }
}

async function fetchLogs() {
    loading.value = true;
    try {
        const { data } = await get('/activity-logs', { per_page: 100 });
        logs.value = (data.data ?? data).filter(
            l => l.activity_type?.slug === 'drink' || l.activity_type_id === typeId.value
        );
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!typeId.value) {
        error.value = 'Drink activity type not found. Please re-seed the database.';
        return;
    }
    error.value = '';
    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('activity_type_id', typeId.value);
        fd.append('logged_at', form.value.logged_at);
        if (form.value.quantity) fd.append('quantity', form.value.quantity);
        if (form.value.unit)     fd.append('unit',     form.value.unit);
        const notes = [form.value.drink_type !== 'Water' ? form.value.drink_type : null, form.value.notes].filter(Boolean).join(' — ');
        if (notes) fd.append('notes', notes);
        fd.append('metadata[drink_type]', form.value.drink_type);
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

function drinkLabel(log) {
    return log.metadata?.drink_type ?? log.notes?.split(' — ')[0] ?? 'Water';
}

function volumeLabel(log) {
    if (!log.quantity) return '';
    return `${log.quantity} ${log.unit ?? 'ml'}`;
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
        <h1 class="text-2xl font-bold">Drinks</h1>
        <p class="text-sm text-zinc-500 mt-0.5">Track your water intake and beverages</p>
      </div>
      <button @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors">
        + Log Drink
      </button>
    </div>

    <!-- Form -->
    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-4">New Drink Log</h2>
      <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" autocomplete="off">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Drink type</label>
          <select v-model="form.drink_type" class="input-field">
            <option v-for="d in DRINK_TYPES" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Amount</label>
          <input v-model="form.quantity" type="number" step="1" min="0" placeholder="250" autocomplete="off" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Unit</label>
          <select v-model="form.unit" class="input-field">
            <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Time *</label>
          <input v-model="form.logged_at" type="datetime-local" required autocomplete="off" class="input-field" />
        </div>
        <div class="sm:col-span-1 lg:col-span-2">
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" placeholder="e.g. with lemon" autocomplete="off" class="input-field" />
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
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Drink History</h2>
      </div>
      <div v-if="loading" class="px-5 py-8 text-sm text-zinc-500 text-center">Loading…</div>
      <div v-else-if="!logs.length" class="px-5 py-8 text-sm text-zinc-500 text-center">No drink logs yet.</div>
      <ul v-else class="divide-y divide-zinc-800">
        <li v-for="log in logs" :key="log.id" class="flex items-center gap-4 px-5 py-3.5">
          <span class="text-xl shrink-0">💧</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium">{{ drinkLabel(log) }}</p>
            <p v-if="log.quantity" class="text-xs text-zinc-500">{{ volumeLabel(log) }}</p>
          </div>
          <span class="text-xs text-zinc-600 shrink-0">{{ fmt(log.logged_at) }}</span>
          <button @click="remove(log.id)" class="text-zinc-600 hover:text-red-400 text-xs transition-colors shrink-0">✕</button>
        </li>
      </ul>
    </div>
  </div>
</template>

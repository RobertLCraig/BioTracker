<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '@/composables/useApi';
import LogList from '@/components/LogList.vue';

const { get, post, del } = useApi();
const route = useRoute();

const meds        = ref([]);
const logs        = ref([]);
const loading     = ref(true);
const showMedForm = ref(false);
const showLogForm = ref(false);
const saving      = ref(false);
const error       = ref('');

// ── Medication form ──────────────────────────────────────────────
const blankMed = () => ({
    name:           '',
    dosage:         '',
    frequency:      '',
    prescribed_by:  '',
    notes:          '',
    reminder_times: [],
});
const medForm     = ref(blankMed());
const newReminder = ref('');

function addReminder() {
    const t = newReminder.value.trim();
    if (t && !medForm.value.reminder_times.includes(t)) {
        medForm.value.reminder_times.push(t);
        medForm.value.reminder_times.sort();
    }
    newReminder.value = '';
}

function removeReminder(time) {
    medForm.value.reminder_times = medForm.value.reminder_times.filter(t => t !== time);
}

// ── Dose log form ────────────────────────────────────────────────
const blankLog = () => ({ medication_id: '', taken_at: new Date().toISOString().slice(0, 16), dosage_taken: '', notes: '' });
const logForm  = ref(blankLog());

// ── Table columns ────────────────────────────────────────────────
const medColumns = [
    { key: 'name',          label: 'Medication' },
    { key: 'dosage',        label: 'Dosage',     format: r => r.dosage ?? '—' },
    { key: 'frequency',     label: 'Frequency',  format: r => r.frequency ?? '—' },
    { key: 'prescribed_by', label: 'Prescriber', format: r => r.prescribed_by ?? '—' },
    { key: 'reminders',     label: 'Reminders',  format: r => r.reminder_times?.join(', ') || '—' },
];

const logColumns = [
    { key: 'taken_at',     label: 'Date',       format: r => new Date(r.taken_at).toLocaleString() },
    { key: 'medication',   label: 'Medication', format: r => r.medication?.name ?? '—' },
    { key: 'dosage_taken', label: 'Dose',       format: r => r.dosage_taken ?? '—' },
    { key: 'notes',        label: 'Notes',      format: r => r.notes ?? '—' },
];

// ── Due now panel ────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer;

const dueMeds = computed(() => {
    return meds.value.flatMap(med => {
        if (!med.reminder_times?.length || med.is_active === false) return [];
        return med.reminder_times
            .filter(t => {
                const [h, m] = t.split(':').map(Number);
                const remMin = h * 60 + m;
                const nowMin = now.value.getHours() * 60 + now.value.getMinutes();
                return Math.abs(remMin - nowMin) <= 30;
            })
            .map(t => ({ ...med, due_at: t }));
    });
});

// ── Browser Notifications ─────────────────────────────────────────
const notifGranted = ref(false);

async function requestNotifications() {
    if (!('Notification' in window)) return;
    const result = await Notification.requestPermission();
    notifGranted.value = result === 'granted';
}

function fireNotification(med) {
    if (!notifGranted.value) return;
    new Notification(`Time to take ${med.name}`, {
        body: med.dosage ? `Dosage: ${med.dosage}` : 'Log your dose in BioTracker.',
        icon: '/favicon.ico',
        tag:  `med-${med.id}`,
    });
}

const firedToday = new Set();
function checkReminders() {
    now.value = new Date();
    const hhmm = `${String(now.value.getHours()).padStart(2, '0')}:${String(now.value.getMinutes()).padStart(2, '0')}`;
    meds.value.forEach(med => {
        if (!med.reminder_times?.includes(hhmm)) return;
        const key = `${med.id}-${hhmm}`;
        if (!firedToday.has(key)) {
            firedToday.add(key);
            fireNotification(med);
        }
    });
}

// ── CRUD ──────────────────────────────────────────────────────────
async function load() {
    loading.value = true;
    const [mRes, lRes] = await Promise.all([get('/medications'), get('/medication-logs')]);
    meds.value = mRes.data.data;
    logs.value = lRes.data.data;
    loading.value = false;
}

async function saveMed() {
    error.value  = '';
    saving.value = true;
    try {
        const p = Object.fromEntries(Object.entries(medForm.value).filter(([k, v]) => k === 'reminder_times' ? true : v !== ''));
        await post('/medications', p);
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
        await post('/medication-logs', p);
        showLogForm.value = false;
        logForm.value = blankLog();
        await load();
    } catch (e) { error.value = e.response?.data?.message ?? 'Save failed.'; }
    finally { saving.value = false; }
}

async function takeNow(med) {
    try {
        await post('/medication-logs', {
            medication_id: med.id,
            taken_at:      new Date().toISOString().slice(0, 16),
            dosage_taken:  med.dosage ?? '',
        });
        await load();
    } catch { /* ignore */ }
}

async function removeMed(id) {
    if (!confirm('Delete this medication and all its dose logs?')) return;
    await del(`/medications/${id}`);
    await load();
}

async function removeLog(id) {
    if (!confirm('Delete this dose log?')) return;
    await del(`/medication-logs/${id}`);
    await load();
}

onMounted(async () => {
    notifGranted.value = (typeof Notification !== 'undefined') && Notification.permission === 'granted';
    await load();
    clockTimer = setInterval(checkReminders, 60_000);
    checkReminders();
    if (route.query.quickAdd === '1') showLogForm.value = true;
});

onUnmounted(() => clearInterval(clockTimer));
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold">Medications</h1>

    <!-- Notification permission banner -->
    <div v-if="!notifGranted && typeof Notification !== 'undefined' && Notification.permission !== 'denied'"
      class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-3">
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <p class="text-sm text-amber-200 flex-1">Enable notifications to receive medication reminders.</p>
      <button @click="requestNotifications"
        class="text-xs font-semibold px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-black rounded-lg transition-colors">
        Enable
      </button>
    </div>

    <!-- Due now panel -->
    <div v-if="dueMeds.length" class="bg-zinc-900 border border-teal-500/30 rounded-xl p-4 space-y-2">
      <h2 class="text-sm font-semibold text-teal-400 uppercase tracking-wide mb-3">Due Around Now</h2>
      <div v-for="item in dueMeds" :key="`${item.id}-${item.due_at}`"
        class="flex items-center justify-between gap-3 py-2 border-b border-zinc-800 last:border-0">
        <div>
          <span class="font-medium text-zinc-100">{{ item.name }}</span>
          <span v-if="item.dosage" class="text-xs text-zinc-500 ml-2">{{ item.dosage }}</span>
          <span class="text-xs text-teal-400 ml-2">@ {{ item.due_at }}</span>
        </div>
        <button @click="takeNow(item)"
          class="px-3 py-1 bg-teal-500 hover:bg-teal-400 text-white text-xs font-semibold rounded-lg transition-colors">
          Take now
        </button>
      </div>
    </div>

    <!-- My Medications -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-zinc-300">My Medications</h2>
        <button @click="showMedForm = !showMedForm"
          class="px-3 py-1.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-semibold rounded-lg transition-colors">
          {{ showMedForm ? 'Cancel' : '+ Add Medication' }}
        </button>
      </div>

      <div v-if="showMedForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-4">
        <form @submit.prevent="saveMed" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" autocomplete="off">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Name *</label>
            <input v-model="medForm.name" type="text" required placeholder="Metformin" autofocus
              autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Dosage</label>
            <input v-model="medForm.dosage" type="text" placeholder="500mg"
              autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Frequency</label>
            <input v-model="medForm.frequency" type="text" placeholder="Twice daily"
              autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Prescribed by</label>
            <input v-model="medForm.prescribed_by" type="text" placeholder="Dr Smith"
              autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
            <input v-model="medForm.notes" type="text" placeholder="With food, etc."
              autocomplete="off" class="input-field" />
          </div>

          <!-- Reminder times -->
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Reminder times</label>
            <div class="flex flex-wrap gap-2 mb-2">
              <span v-for="t in medForm.reminder_times" :key="t"
                class="flex items-center gap-1 text-xs bg-teal-500/15 border border-teal-500/40 text-teal-300 px-2 py-1 rounded-full">
                {{ t }}
                <button type="button" @click="removeReminder(t)"
                  class="ml-0.5 text-teal-500 hover:text-red-400 transition-colors leading-none">×</button>
              </span>
              <span v-if="!medForm.reminder_times.length" class="text-xs text-zinc-600 self-center">No reminders set</span>
            </div>
            <div class="flex gap-2">
              <input v-model="newReminder" type="time" class="input-field w-36" />
              <button type="button" @click="addReminder"
                class="px-3 py-2 bg-zinc-700 hover:bg-zinc-600 text-sm rounded-lg transition-colors">
                Add time
              </button>
            </div>
          </div>

          <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
          <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
            <button type="submit" :disabled="saving"
              class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" @click="showMedForm = false"
              class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <LogList :logs="meds" :loading="loading" :columns="medColumns" @delete="removeMed" />
    </section>

    <!-- Dose History -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-zinc-300">Dose History</h2>
        <button @click="showLogForm = !showLogForm"
          class="px-3 py-1.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-semibold rounded-lg transition-colors">
          {{ showLogForm ? 'Cancel' : '+ Log Dose' }}
        </button>
      </div>

      <div v-if="showLogForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-4">
        <form @submit.prevent="saveLog" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" autocomplete="off">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Medication *</label>
            <select v-model="logForm.medication_id" required class="input-field">
              <option value="">Select…</option>
              <option v-for="m in meds" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Taken at *</label>
            <input v-model="logForm.taken_at" type="datetime-local" required autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Dose taken</label>
            <input v-model="logForm.dosage_taken" type="text" placeholder="500mg" autocomplete="off" class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes</label>
            <input v-model="logForm.notes" type="text" placeholder="Optional" autocomplete="off" class="input-field" />
          </div>
          <div v-if="error" class="sm:col-span-2 lg:col-span-3 text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
          <div class="sm:col-span-2 lg:col-span-3 flex gap-3">
            <button type="submit" :disabled="saving"
              class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
              {{ saving ? 'Saving…' : 'Log' }}
            </button>
            <button type="button" @click="showLogForm = false"
              class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-sm rounded-lg transition-colors">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <LogList :logs="logs" :loading="loading" :columns="logColumns" @delete="removeLog" />
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '@/composables/useApi';
import PhotoDropzone from '@/components/PhotoDropzone.vue';

const { get, postForm, del } = useApi();
const route = useRoute();

const logs        = ref([]);
const loading     = ref(false);
const submitting  = ref(false);
const error       = ref('');
const showForm    = ref(false);
const foodTypeId  = ref(null);
const photos      = ref([]);

// Suggest a meal label based on current hour
function suggestMeal() {
    const h = new Date().getHours();
    if (h >= 5  && h < 10) return 'Breakfast';
    if (h >= 10 && h < 12) return 'Morning snack';
    if (h >= 12 && h < 15) return 'Lunch';
    if (h >= 15 && h < 18) return 'Afternoon snack';
    if (h >= 18 && h < 22) return 'Dinner';
    return 'Late snack';
}

const form = ref({
    meal_label: suggestMeal(),
    food_name:  '',
    quantity:   '',
    unit:       'g',
    calories:   '',
    notes:      '',
    logged_at:  new Date().toISOString().slice(0, 16),
});

const MEAL_OPTIONS = [
    'Breakfast', 'Morning snack', 'Lunch', 'Afternoon snack', 'Dinner', 'Late snack', 'Other',
];

const UNITS = ['g', 'ml', 'oz', 'fl oz', 'cup', 'tbsp', 'tsp', 'piece', 'serving', 'kcal'];

async function fetchLogs() {
    loading.value = true;
    try {
        const { data } = await get('/activity-logs', { per_page: 50 });
        // Filter to food type only
        logs.value = (data.data ?? data).filter(
            l => l.activity_type?.slug === 'food' || l.activity_type_id === foodTypeId.value
        );
    } catch {
        // silently fail
    } finally {
        loading.value = false;
    }
}

async function fetchFoodType() {
    try {
        const { data } = await get('/activity-types');
        const food = (data.data ?? data).find(t => t.slug === 'food');
        if (food) foodTypeId.value = food.id;
    } catch { /* ignore */ }
}

async function submit() {
    if (!foodTypeId.value) {
        error.value = 'Food activity type not found. Please re-seed the database.';
        return;
    }
    error.value  = '';
    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('activity_type_id', foodTypeId.value);
        fd.append('logged_at', form.value.logged_at);
        if (form.value.quantity) fd.append('quantity',  form.value.quantity);
        if (form.value.unit)     fd.append('unit',      form.value.unit);
        if (form.value.calories) fd.append('calories',  form.value.calories);
        if (form.value.notes)    fd.append('notes',     form.value.notes);

        // Pack food-specific metadata
        const meta = {
            food_name:  form.value.food_name,
            meal_label: form.value.meal_label,
        };
        fd.append('metadata', JSON.stringify(meta));

        photos.value.forEach(file => fd.append('photos[]', file));

        await postForm('/activity-logs', fd);

        // Reset form
        form.value = {
            meal_label: suggestMeal(),
            food_name:  '',
            quantity:   '',
            unit:       'g',
            calories:   '',
            notes:      '',
            logged_at:  new Date().toISOString().slice(0, 16),
        };
        photos.value = [];
        showForm.value = false;
        await fetchLogs();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to save food log.';
    } finally {
        submitting.value = false;
    }
}

async function remove(id) {
    if (!confirm('Delete this food entry?')) return;
    try {
        await del(`/activity-logs/${id}`);
        logs.value = logs.value.filter(l => l.id !== id);
    } catch { /* ignore */ }
}

// Total calories for visible logs
const totalCalories = computed(() =>
    logs.value.reduce((sum, l) => sum + (l.calories ?? 0), 0)
);

onMounted(async () => {
    await fetchFoodType();
    await fetchLogs();
    if (route.query.quickAdd === '1') showForm.value = true;
});
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Food Log</h1>
        <p class="text-sm text-zinc-500 mt-0.5">
          {{ logs.length }} entries · {{ totalCalories }} kcal logged
        </p>
      </div>
      <button
        @click="showForm = !showForm"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-white text-sm font-semibold rounded-lg transition-colors"
      >
        {{ showForm ? 'Cancel' : '+ Add Entry' }}
      </button>
    </div>

    <!-- Add form -->
    <div v-if="showForm" class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 space-y-4">
      <h2 class="font-semibold text-zinc-200">Log Food</h2>

      <form @submit.prevent="submit" class="space-y-4" autocomplete="off">

        <!-- Meal label + date/time row -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Meal</label>
            <select v-model="form.meal_label"
              class="input-field">
              <option v-for="m in MEAL_OPTIONS" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Time</label>
            <input v-model="form.logged_at" type="datetime-local" required
              autocomplete="off"
              class="input-field" />
          </div>
        </div>

        <!-- Food name -->
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Food / Drink name</label>
          <input v-model="form.food_name" type="text" placeholder="e.g. Chicken salad, Apple, Latte…"
            required autocomplete="off"
            class="input-field" />
        </div>

        <!-- Quantity + unit + calories row -->
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Quantity</label>
            <input v-model="form.quantity" type="number" min="0" step="any" placeholder="150"
              autocomplete="off"
              class="input-field" />
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Unit</label>
            <select v-model="form.unit" class="input-field">
              <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Calories (kcal)</label>
            <input v-model="form.calories" type="number" min="0" placeholder="350"
              autocomplete="off"
              class="input-field" />
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Notes <span class="text-zinc-600">(optional)</span></label>
          <input v-model="form.notes" type="text" placeholder="Ingredients, brand, etc."
            autocomplete="off"
            class="input-field" />
        </div>

        <!-- Photo dropzone -->
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Photos <span class="text-zinc-600">(optional)</span></label>
          <PhotoDropzone v-model="photos" :max-files="5" />
        </div>

        <div v-if="error" class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>

        <button type="submit" :disabled="submitting"
          class="w-full py-2.5 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors">
          {{ submitting ? 'Saving…' : 'Save Entry' }}
        </button>
      </form>
    </div>

    <!-- History -->
    <div class="space-y-3">
      <div v-if="loading" class="text-center py-12 text-zinc-500">Loading…</div>

      <div v-else-if="!logs.length" class="text-center py-12 text-zinc-600">
        No food entries yet. Add your first meal above.
      </div>

      <div v-for="log in logs" :key="log.id"
        class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <!-- Header row -->
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-500/15 text-teal-300 border border-teal-500/30">
                {{ log.metadata?.meal_label ?? 'Food' }}
              </span>
              <span class="text-xs text-zinc-500">
                {{ new Date(log.logged_at).toLocaleString() }}
              </span>
            </div>

            <!-- Food name -->
            <p class="font-medium text-zinc-100 mt-1 truncate">
              {{ log.metadata?.food_name ?? log.notes ?? '—' }}
            </p>

            <!-- Details row -->
            <div class="flex items-center gap-3 mt-1 flex-wrap text-xs text-zinc-500">
              <span v-if="log.quantity">{{ log.quantity }} {{ log.unit }}</span>
              <span v-if="log.calories" class="text-amber-400 font-medium">{{ log.calories }} kcal</span>
              <span v-if="log.notes && log.metadata?.food_name" class="truncate max-w-xs">{{ log.notes }}</span>
            </div>
          </div>

          <button @click="remove(log.id)"
            class="shrink-0 p-1.5 rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>

        <!-- Photos -->
        <div v-if="log.photos?.length" class="grid grid-cols-4 gap-2 mt-3">
          <a v-for="photo in log.photos" :key="photo.id"
            :href="photo.url" target="_blank" rel="noopener"
            class="block rounded-lg overflow-hidden aspect-square bg-zinc-800 hover:opacity-80 transition-opacity">
            <img :src="photo.thumb ?? photo.url" :alt="log.metadata?.food_name ?? 'photo'"
              class="w-full h-full object-cover" />
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

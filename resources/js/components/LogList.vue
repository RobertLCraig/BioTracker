<script setup>
defineProps({
    logs:    { type: Array,   default: () => [] },
    loading: { type: Boolean, default: false },
    columns: { type: Array,   default: () => [] },
});
defineEmits(['delete']);
</script>

<template>
  <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
    <div v-if="loading" class="p-6 text-sm text-zinc-500 text-center">Loading…</div>
    <div v-else-if="!logs.length" class="p-8 text-sm text-zinc-500 text-center">No entries yet. Add your first one above.</div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800">
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap"
            >
              {{ col.label }}
            </th>
            <th class="px-4 py-3 w-10"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in logs"
            :key="row.id"
            class="border-b border-zinc-800/50 hover:bg-zinc-800/30 transition-colors last:border-0"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-zinc-300 whitespace-nowrap max-w-[200px] truncate"
            >
              {{ col.format ? col.format(row) : (row[col.key] ?? '—') }}
            </td>
            <td class="px-4 py-3">
              <button
                @click="$emit('delete', row.id)"
                class="text-zinc-600 hover:text-red-400 transition-colors"
                title="Delete"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

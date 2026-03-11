<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // array of File objects
    maxFiles:   { type: Number, default: 5 },
    accept:     { type: String, default: 'image/*' },
});

const emit = defineEmits(['update:modelValue']);

const previews  = ref([]); // { url, name, file }
const dragging  = ref(false);
const fileInput = ref(null);

function addFiles(files) {
    const allowed = Array.from(files).filter(f => f.type.startsWith('image/'));
    const remaining = props.maxFiles - previews.value.length;
    const toAdd = allowed.slice(0, remaining);

    toAdd.forEach(file => {
        const url = URL.createObjectURL(file);
        previews.value.push({ url, name: file.name, file });
    });

    emit('update:modelValue', previews.value.map(p => p.file));
}

function remove(index) {
    URL.revokeObjectURL(previews.value[index].url);
    previews.value.splice(index, 1);
    emit('update:modelValue', previews.value.map(p => p.file));
}

function onDrop(e) {
    dragging.value = false;
    addFiles(e.dataTransfer.files);
}

function onFileInput(e) {
    addFiles(e.target.files);
    e.target.value = '';
}

function openPicker() {
    fileInput.value?.click();
}
</script>

<template>
  <div class="space-y-2">
    <!-- Drop zone -->
    <div
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
      @click="openPicker"
      :class="[
        'border-2 border-dashed rounded-xl px-4 py-6 text-center cursor-pointer transition-colors select-none',
        dragging
          ? 'border-teal-500 bg-teal-500/10'
          : 'border-zinc-700 hover:border-zinc-500 bg-zinc-800/40',
      ]"
    >
      <svg class="w-8 h-8 mx-auto mb-2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
      </svg>
      <p class="text-sm text-zinc-400">
        <span class="text-teal-400 font-medium">Click to upload</span> or drag &amp; drop
      </p>
      <p class="text-xs text-zinc-600 mt-1">PNG, JPG, WEBP up to 10 MB (max {{ maxFiles }})</p>
    </div>

    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      multiple
      class="hidden"
      @change="onFileInput"
    />

    <!-- Previews -->
    <div v-if="previews.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
      <div
        v-for="(p, i) in previews"
        :key="p.url"
        class="relative group rounded-lg overflow-hidden aspect-square bg-zinc-800"
      >
        <img :src="p.url" :alt="p.name" class="w-full h-full object-cover" />
        <button
          type="button"
          @click.stop="remove(i)"
          class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity"
        >
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

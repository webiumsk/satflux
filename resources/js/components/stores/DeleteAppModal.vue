<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
    @click.self="$emit('close')"
  >
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          Delete {{ appName }}?
        </h3>
        <p class="text-sm text-gray-600 mb-4">
          This action cannot be undone. This will permanently delete the app and all its data.
        </p>
        <p class="text-sm font-medium text-gray-700 mb-2">
          Please type <span class="font-mono text-red-600">DELETE</span> to confirm:
        </p>
        <input
          v-model="confirmText"
          type="text"
          placeholder="Type DELETE"
          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
          :class="{ 'border-red-500': confirmText && confirmText !== 'DELETE' }"
        />
        <div v-if="error" class="mt-2 text-sm text-red-600">
          {{ error }}
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button
            type="button"
            @click="$emit('close')"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleDelete"
            :disabled="deleting || confirmText !== 'DELETE'"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
          >
            {{ deleting ? 'Deleting...' : 'Delete App' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
  isOpen: boolean;
  appName: string;
  deleting?: boolean;
  error?: string;
}>();

const emit = defineEmits<{
  close: [];
  delete: [];
}>();

const confirmText = ref('');

function handleDelete() {
  if (confirmText.value !== 'DELETE') {
    return;
  }
  emit('delete');
}

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) {
    confirmText.value = '';
  }
});
</script>


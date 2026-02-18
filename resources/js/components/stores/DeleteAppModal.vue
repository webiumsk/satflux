<template>
  <Transition
    enter-active-class="ease-out duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="ease-in duration-200"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div class="relative w-full max-w-md bg-gray-800 border border-gray-700 shadow-2xl rounded-2xl overflow-hidden p-6 sm:p-8">
            <div class="flex items-start mb-6">
                <!-- Warning Icon -->
                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-500/10 sm:h-10 sm:w-10 mr-4">
                  <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                  </svg>
                </div>
                
                <div>
                   <h3 class="text-xl font-bold text-white mb-2">
                    Delete {{ appName }}?
                    </h3>
                    <p class="text-sm text-gray-400">
                    This action cannot be undone. This will permanently delete the app and all its data.
                    </p>
                </div>
            </div>

            <div class="space-y-4">
               <p class="text-sm font-medium text-gray-300">
                Please type <span class="font-mono text-red-400 font-bold bg-red-500/10 px-1 rounded">DELETE</span> to confirm:
                </p>
                
                <input
                v-model="confirmText"
                type="text"
                placeholder="Type DELETE"
                class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                :class="{ 'border-red-500 ring-1 ring-red-500': confirmText && confirmText !== 'DELETE' }"
                />
                
            </div>

            <div class="flex justify-end gap-3 mt-8">
            <button
                type="button"
                @click="$emit('close')"
                :disabled="deleting"
                class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
            >
                Cancel
            </button>
            <button
                type="button"
                @click="handleDelete"
                :disabled="deleting || confirmText !== 'DELETE'"
                class="inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-red-600 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-red-600/20 transition-all hover:scale-105"
            >
                <svg v-if="deleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ deleting ? 'Deleting...' : 'Delete App' }}
            </button>
            </div>
        </div>
      </Transition>
    </div>
  </Transition>
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

<template>
  <div v-show="show" class="fixed bottom-4 right-4 z-[9999] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-lg shadow-lg dark:text-gray-400 dark:bg-gray-800 transition-all duration-300 transform" :class="[bgClass, show ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0']" role="alert">
    <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg" :class="iconBgClass">
      <svg v-if="type === 'success'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
      </svg>
      <svg v-else-if="type === 'warning'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM10 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm1-4a1 1 0 0 1-2 0V6a1 1 0 0 1 2 0v5Z"/>
      </svg>
      <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z"/>
      </svg>
    </div>
    <div class="pl-2 text-sm font-normal">{{ message }}</div>
    <button @click="show = false" type="button" class="ml-auto -mx-1.5 -my-1.5 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-white/10 inline-flex h-8 w-8 text-white" aria-label="Close">
      <span class="sr-only">Close</span>
      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref('success');
let timeout: any = null;

const bgClass = computed(() => {
  if (type.value === 'warning') return 'bg-gray-800 border-l-4 border-orange-500';
  return type.value === 'success' ? 'bg-gray-800 border-l-4 border-green-500' : 'bg-gray-800 border-l-4 border-red-500';
});

const iconBgClass = computed(() => {
  if (type.value === 'warning') return 'text-orange-500 bg-orange-100 rounded-lg dark:bg-orange-900/50 dark:text-orange-400';
  return type.value === 'success' ? 'text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200' : 'text-red-500 bg-red-100 rounded-lg dark:bg-red-800 dark:text-red-200';
});

function showMessage(msg: string, msgType: string) {
    message.value = msg;
    type.value = msgType;
    show.value = true;
    
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => show.value = false, 5000);
}

// Watch the reactive page props directly
watch(() => page.props.flash, (flash: any) => {
  if (flash?.success) {
    showMessage(flash.success, 'success');
  } else if (flash?.error) {
    showMessage(flash.error, 'error');
  } else if (flash?.warning) {
    showMessage(flash.warning, 'warning');
  } else if (flash?.message) {
    showMessage(flash.message, 'success'); // Fallback for 'message' key
  }
}, { deep: true, immediate: true });
</script>

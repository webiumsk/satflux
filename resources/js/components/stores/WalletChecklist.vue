<template>
  <div class="space-y-4">
    <div
      v-for="item in checklistItems"
      :key="item.key"
      class="group relative flex items-start p-5 rounded-2xl border transition-all duration-200"
      :class="[
        item.is_completed 
          ? 'bg-indigo-500/5 border-indigo-500/20' 
          : 'bg-gray-900/30 border-gray-700 hover:border-gray-500 hover:bg-gray-900/50'
      ]"
    >
      <div class="flex items-center h-6">
        <div class="relative flex items-center justify-center">
          <input
            :id="`checklist-${item.key}`"
            type="checkbox"
            :checked="item.is_completed"
            @change="handleToggle(item.key, $event.target.checked)"
            class="h-6 w-6 rounded-lg border-2 border-gray-600 bg-transparent text-indigo-600 focus:ring-offset-gray-900 focus:ring-indigo-500 cursor-pointer transition-all appearance-none checked:bg-indigo-600 checked:border-indigo-600"
          />
          <svg 
            v-if="item.is_completed"
            class="absolute w-4 h-4 text-white pointer-events-none" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor" 
            stroke-width="4"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
      </div>
      <div class="ml-4 flex-1">
        <label
          :for="`checklist-${item.key}`"
          class="block text-base font-semibold cursor-pointer select-none transition-colors"
          :class="item.is_completed ? 'text-gray-400 line-through' : 'text-gray-200 group-hover:text-white'"
        >
          {{ item.description }}
          <span v-if="item.optional" class="ml-2 text-xs font-medium text-gray-500 group-hover:text-gray-400 uppercase tracking-wider">(Optional)</span>
        </label>
        
        <div v-if="item.link && !item.is_completed" class="mt-2">
            <a :href="item.link" target="_blank" class="text-xs font-bold text-indigo-400 hover:text-indigo-300 flex items-center">
                Documentation
                <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            </a>
        </div>
      </div>
      
      <!-- Indicator current/next -->
      <div v-if="!item.is_completed && isFirstUncomplete(item.key)" class="absolute -left-1 top-1/2 -translate-y-1/2 w-1.5 h-8 bg-indigo-500 rounded-full shadow-[0_0_12px_rgba(99,102,241,0.5)]"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';

interface ChecklistItem {
  key: string;
  description: string;
  link: string | null;
  completed_at: string | null;
  is_completed: boolean;
  optional?: boolean;
}

const props = defineProps<{
  storeId: string;
}>();

const checklistItems = ref<ChecklistItem[]>([]);
const loading = ref(false);

async function fetchChecklist() {
  loading.value = true;
  try {
    const response = await api.get(`/stores/${props.storeId}/checklist`);
    checklistItems.value = response.data.data || [];
  } finally {
    loading.value = false;
  }
}

async function handleToggle(itemKey: string, completed: boolean) {
  try {
    const response = await api.put(`/stores/${props.storeId}/checklist/${itemKey}`, {
      completed,
    });
    const updatedItem = response.data.data;
    const index = checklistItems.value.findIndex(item => item.key === itemKey);
    if (index !== -1) {
      checklistItems.value[index] = updatedItem;
    }
  } catch (error) {
    // Revert checkbox on error
    await fetchChecklist();
  }
}

function isFirstUncomplete(key: string) {
    const firstUncomplete = checklistItems.value.find(item => !item.is_completed);
    return firstUncomplete?.key === key;
}

onMounted(() => {
  fetchChecklist();
});
</script>

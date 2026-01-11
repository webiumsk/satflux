<template>
  <div class="space-y-4">
    <div
      v-for="item in checklistItems"
      :key="item.key"
      class="flex items-start p-4 border border-gray-200 rounded-lg"
      :class="{ 'bg-gray-50': item.is_completed }"
    >
      <input
        :id="`checklist-${item.key}`"
        type="checkbox"
        :checked="item.is_completed"
        @change="handleToggle(item.key, $event.target.checked)"
        class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
      />
      <div class="ml-3 flex-1">
        <label
          :for="`checklist-${item.key}`"
          class="text-sm font-medium text-gray-900 cursor-pointer"
          :class="{ 'line-through text-gray-500': item.is_completed }"
        >
          {{ item.description }}
          <span v-if="item.optional" class="text-xs text-gray-400 ml-1">(Optional)</span>
        </label>
        <div v-if="item.link" class="mt-1">
          <a
            :href="item.link"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-indigo-600 hover:text-indigo-500"
            @click.stop
          >
            Open in BTCPay →
          </a>
        </div>
      </div>
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

onMounted(() => {
  fetchChecklist();
});
</script>


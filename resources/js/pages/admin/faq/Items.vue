<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-white">{{ t('admin.faq.items.title') }}</h1>
        <p class="text-gray-400 mt-1">{{ t('admin.faq.items.description') }}</p>
      </div>
      <router-link
        to="/admin/faq/items/create"
        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t('admin.faq.items.create') }}
      </router-link>
    </div>

    <!-- Filters -->
    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.faq.items.search') }}</label>
          <input
            v-model="searchQuery"
            type="text"
            class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @input="handleSearch"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.faq.items.status') }}</label>
          <select
            v-model="publishedFilter"
            class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @change="loadItems"
          >
            <option value="">{{ t('admin.faq.items.all') }}</option>
            <option value="true">{{ t('admin.faq.items.published') }}</option>
            <option value="false">{{ t('admin.faq.items.unpublished') }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Items Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-900/50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.faq.items.question') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.faq.items.status') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.faq.items.views') }}</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase">{{ t('admin.faq.items.actions') }}</th>
            </tr>
          </thead>
          <tbody class="bg-gray-800 divide-y divide-gray-700">
            <tr v-if="loading">
              <td colspan="4" class="px-6 py-12 text-center">
                <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </td>
            </tr>
            <tr v-else-if="items.length === 0">
              <td colspan="4" class="px-6 py-12 text-center text-gray-400">{{ t('admin.faq.items.no_items') }}</td>
            </tr>
            <tr v-else v-for="item in items" :key="item.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm font-medium text-white">{{ item.question?.en || item.question }}</td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    item.is_published
                      ? 'bg-green-500/20 text-green-400'
                      : 'bg-yellow-500/20 text-yellow-400'
                  ]"
                >
                  {{ item.is_published ? t('admin.faq.items.published') : t('admin.faq.items.unpublished') }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-400">{{ item.view_count || 0 }}</td>
              <td class="px-6 py-4 text-right text-sm font-medium">
                <router-link
                  :to="`/admin/faq/items/${item.id}/edit`"
                  class="text-indigo-400 hover:text-indigo-300 mr-4"
                >
                  {{ t('admin.faq.items.edit') }}
                </router-link>
                <button
                  @click="deleteItem(item.id)"
                  class="text-red-400 hover:text-red-300"
                >
                  {{ t('admin.faq.items.delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { adminFaqApi } from '../../../services/api';

const router = useRouter();
const { t } = useI18n();

const loading = ref(false);
const items = ref<any[]>([]);
const searchQuery = ref('');
const publishedFilter = ref('');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const loadItems = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (publishedFilter.value) params.is_published = publishedFilter.value === 'true';
    if (searchQuery.value) params.search = searchQuery.value;
    
    const response = await adminFaqApi.items.index(params);
    items.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load items:', error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadItems();
  }, 300);
};

const deleteItem = async (id: string) => {
  if (!confirm(t('admin.faq.items.confirm_delete'))) return;
  
  try {
    await adminFaqApi.items.delete(id);
    loadItems();
  } catch (error) {
    console.error('Failed to delete item:', error);
    alert(t('admin.faq.items.delete_error'));
  }
};

onMounted(() => {
  loadItems();
});
</script>


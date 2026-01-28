<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-white">{{ t('admin.documentation.articles.title') }}</h1>
        <p class="text-gray-400 mt-1">{{ t('admin.documentation.articles.description') }}</p>
      </div>
      <router-link
        to="/admin/documentation/articles/create"
        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t('admin.documentation.articles.create') }}
      </router-link>
    </div>

    <!-- Filters -->
    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.documentation.articles.search') }}</label>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.documentation.articles.search_placeholder')"
            class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @input="handleSearch"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.documentation.articles.category') }}</label>
          <Select
            v-model="selectedCategory"
            :options="categoryOptions"
            :placeholder="t('admin.documentation.articles.all_categories')"
            @change="loadArticles"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.documentation.articles.status') }}</label>
          <Select
            v-model="publishedFilter"
            :options="publishOptions"
            :placeholder="t('admin.documentation.articles.all')"
            @change="loadArticles"
          />
        </div>
      </div>
    </div>

    <!-- Articles Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-900/50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.documentation.articles.title') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.documentation.articles.category') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.documentation.articles.status') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('admin.documentation.articles.created') }}</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase">{{ t('admin.documentation.articles.actions') }}</th>
            </tr>
          </thead>
          <tbody class="bg-gray-800 divide-y divide-gray-700">
            <tr v-if="loading">
              <td colspan="5" class="px-6 py-12 text-center">
                <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </td>
            </tr>
            <tr v-else-if="articles.length === 0">
              <td colspan="5" class="px-6 py-12 text-center text-gray-400">{{ t('admin.documentation.articles.no_articles') }}</td>
            </tr>
            <tr v-else v-for="article in articles" :key="article.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm font-medium text-white">{{ article.title?.en || article.title }}</td>
              <td class="px-6 py-4 text-sm text-gray-300">{{ article.category?.name?.en || article.category?.name || '-' }}</td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    article.is_published
                      ? 'bg-green-500/20 text-green-400'
                      : 'bg-yellow-500/20 text-yellow-400'
                  ]"
                >
                  {{ article.is_published ? t('admin.documentation.articles.published') : t('admin.documentation.articles.unpublished') }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(article.created_at) }}</td>
              <td class="px-6 py-4 text-right text-sm font-medium">
                <router-link
                  :to="`/admin/documentation/articles/${article.id}/edit`"
                  class="text-indigo-400 hover:text-indigo-300 mr-4"
                >
                  {{ t('admin.documentation.articles.edit') }}
                </router-link>
                <button
                  @click="deleteArticle(article.id)"
                  class="text-red-400 hover:text-red-300"
                >
                  {{ t('admin.documentation.articles.delete') }}
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
import { useI18n } from 'vue-i18n';
import { adminDocumentationApi } from '../../../services/api';
import Select from '../../../components/ui/Select.vue';
import { computed } from 'vue';

const { t } = useI18n();

const loading = ref(false);
const articles = ref<any[]>([]);
const categories = ref<any[]>([]);
const searchQuery = ref('');
const selectedCategory = ref('');
const publishedFilter = ref('');

const categoryOptions = computed(() => [
  { label: t('admin.documentation.articles.all_categories'), value: '' },
  ...categories.value.map(cat => ({
    label: cat.name?.en || cat.name,
    value: cat.id
  }))
]);

const publishOptions = [
  { label: t('admin.documentation.articles.all'), value: '' },
  { label: t('admin.documentation.articles.published'), value: 'true' },
  { label: t('admin.documentation.articles.unpublished'), value: 'false' },
];

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const loadArticles = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (selectedCategory.value) params.category_id = selectedCategory.value;
    if (publishedFilter.value) params.is_published = publishedFilter.value === 'true';
    if (searchQuery.value) params.search = searchQuery.value;
    
    const response = await adminDocumentationApi.articles.index(params);
    articles.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load articles:', error);
  } finally {
    loading.value = false;
  }
};

const loadCategories = async () => {
  try {
    const response = await adminDocumentationApi.categories.index();
    categories.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load categories:', error);
  }
};

const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadArticles();
  }, 300);
};

const deleteArticle = async (id: string) => {
  if (!confirm(t('admin.documentation.articles.confirm_delete'))) return;
  
  try {
    await adminDocumentationApi.articles.delete(id);
    loadArticles();
  } catch (error) {
    console.error('Failed to delete article:', error);
    alert(t('admin.documentation.articles.delete_error'));
  }
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString();
};

onMounted(() => {
  loadArticles();
  loadCategories();
});
</script>


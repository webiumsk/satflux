<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-10">
      <h1 class="text-3xl font-bold text-white mb-2">
        {{ t('documentation.title') }}
      </h1>
      <p class="text-gray-400">{{ t('documentation.description') }}</p>
    </div>

    <!-- Search and Category Filter -->
    <div class="mb-8 flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <SearchBar
          v-model="searchQuery"
          :placeholder="t('documentation.search_placeholder')"
          @search="handleSearch"
        />
      </div>
      <CategoryFilter
        v-model="selectedCategory"
        :categories="categories"
        @filter="handleCategoryFilter"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-24">
      <svg
        class="animate-spin h-10 w-10 text-indigo-500"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
    </div>

    <!-- Articles List -->
    <div v-else-if="articles.length > 0" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="article in articles"
        :key="article.id"
        class="group bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 cursor-pointer transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 hover:-translate-y-1"
        @click="$router.push(`/documentation/${article.slug}`)"
      >
        <div class="mb-4">
          <span
            v-if="article.category"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"
          >
            {{ article.category.name }}
          </span>
        </div>
        <h2 class="text-xl font-semibold text-white mb-3 group-hover:text-indigo-400 transition-colors">
          {{ article.title }}
        </h2>
        <p
          v-if="article.meta_description"
          class="text-gray-400 text-sm line-clamp-2 mb-4"
        >
          {{ article.meta_description }}
        </p>
        <div class="flex items-center text-xs text-gray-500">
          <span>{{ formatDate(article.created_at) }}</span>
        </div>
      </article>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="text-center py-20 bg-gray-800/50 backdrop-blur-sm rounded-3xl border border-gray-700/50 border-dashed"
    >
      <div class="w-20 h-20 bg-gray-700/50 rounded-full flex items-center justify-center mx-auto mb-6">
        <svg
          class="w-10 h-10 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      </div>
      <h3 class="text-xl font-medium text-white mb-2">
        {{ t('documentation.no_articles_found') }}
      </h3>
      <p class="text-gray-400">
        {{ t('documentation.try_different_search') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { documentationApi } from '../../services/api';
import SearchBar from '../../components/documentation/SearchBar.vue';
import CategoryFilter from '../../components/documentation/CategoryFilter.vue';

const { t } = useI18n();

const loading = ref(false);
const articles = ref<any[]>([]);
const categories = ref<any[]>([]);
const searchQuery = ref('');
const selectedCategory = ref<string | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const loadArticles = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (selectedCategory.value) {
      params.category_id = selectedCategory.value;
    }
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }
    const response = await documentationApi.index(params);
    articles.value = response.data.data || [];
    categories.value = response.data.categories || [];
  } catch (error) {
    console.error('Failed to load articles:', error);
    articles.value = [];
  } finally {
    loading.value = false;
  }
};

const handleSearch = (query: string) => {
  searchQuery.value = query;
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    loadArticles();
  }, 300);
};

const handleCategoryFilter = (categoryId: string | null) => {
  selectedCategory.value = categoryId;
  loadArticles();
};

const formatDate = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleDateString();
};

watch([searchQuery, selectedCategory], () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    loadArticles();
  }, 300);
});

onMounted(() => {
  loadArticles();
});
</script>


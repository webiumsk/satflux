<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-10">
      <h1 class="text-3xl font-bold text-white mb-2">
        {{ t('faq.title') }}
      </h1>
      <p class="text-gray-400">{{ t('faq.description') }}</p>
    </div>

    <!-- Search and Category Filter -->
    <div class="mb-8 flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <FaqSearch
          v-model="searchQuery"
          :placeholder="t('faq.search_placeholder')"
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

    <!-- FAQ Accordion -->
    <FaqAccordion
      v-else
      :items="filteredItems"
      :search-query="searchQuery"
      @helpful="handleHelpful"
    />

    <!-- Empty State -->
    <div
      v-if="!loading && filteredItems.length === 0"
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
            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </div>
      <h3 class="text-xl font-medium text-white mb-2">
        {{ t('faq.no_items_found') }}
      </h3>
      <p class="text-gray-400">
        {{ t('faq.try_different_search') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { faqApi } from '../../services/api';
import FaqAccordion from '../../components/faq/FaqAccordion.vue';
import FaqSearch from '../../components/faq/FaqSearch.vue';
import CategoryFilter from '../../components/documentation/CategoryFilter.vue';

const { t } = useI18n();

const loading = ref(false);
const items = ref<any[]>([]);
const categories = ref<any[]>([]);
const searchQuery = ref('');
const selectedCategory = ref<string | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const filteredItems = computed(() => {
  let filtered = items.value;
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(item => {
      return (
        item.question?.toLowerCase().includes(query) ||
        item.answer?.toLowerCase().includes(query)
      );
    });
  }
  
  return filtered;
});

const loadItems = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (selectedCategory.value) {
      params.category_id = selectedCategory.value;
    }
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }
    const response = await faqApi.index(params);
    items.value = response.data.data || [];
    categories.value = response.data.categories || [];
  } catch (error) {
    console.error('Failed to load FAQ items:', error);
    items.value = [];
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
    loadItems();
  }, 300);
};

const handleCategoryFilter = (categoryId: string | null) => {
  selectedCategory.value = categoryId;
  loadItems();
};

const handleHelpful = async (itemId: string) => {
  const item = items.value.find(i => i.id === itemId);
  if (!item) return;
  
  try {
    await faqApi.markHelpful(item.slug);
    item.helpful_count = (item.helpful_count || 0) + 1;
  } catch (error) {
    console.error('Failed to mark as helpful:', error);
  }
};

watch([searchQuery, selectedCategory], () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    loadItems();
  }, 300);
});

onMounted(() => {
  loadItems();
});
</script>


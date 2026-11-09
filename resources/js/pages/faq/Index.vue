<template>
  <div class="min-h-screen bg-gray-900">
    <PublicHeader />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
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
        v-else-if="!loadError"
        :items="filteredItems"
        :search-query="searchQuery"
        @helpful="handleHelpful"
      />

      <div v-if="!loading && loadError" role="alert" class="rounded-xl border border-gray-700 bg-gray-800 p-6 text-gray-300">
        <p>{{ t(loadError) }}</p>
        <button type="button" class="mt-3 text-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" @click="loadItems">
          {{ t('common.retry') }}
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-if="!loading && !loadError && filteredItems.length === 0"
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

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { faqApi } from '../../services/api';
import { classifyApiErrorForFlash, GLOBAL_API_ERROR_MESSAGE_KEYS } from '../../services/apiError';
import PublicHeader from '../../components/layout/PublicHeader.vue';
import AppFooter from '../../components/layout/AppFooter.vue';
import FaqAccordion from '../../components/faq/FaqAccordion.vue';
import FaqSearch from '../../components/faq/FaqSearch.vue';
import CategoryFilter from '../../components/documentation/CategoryFilter.vue';

const { t, locale } = useI18n();

const loading = ref(false);
const loadError = ref<string | null>(null);
interface FaqItem {
  id: string;
  slug: string;
  question: string;
  answer: string;
  view_count?: number;
  helpful_count?: number;
  [key: string]: unknown;
}
interface FaqCategory {
  id: string;
  name: string;
  [key: string]: unknown;
}
const items = ref<FaqItem[]>([]);
const categories = ref<FaqCategory[]>([]);
const searchQuery = ref('');
const selectedCategory = ref<string | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let activeRequest: AbortController | null = null;
let requestGeneration = 0;
let disposed = false;

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

/** Invalidate in-flight callbacks immediately and cancel scheduled/list work. */
const invalidatePendingLoad = () => {
  requestGeneration += 1;
  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  activeRequest?.abort();
  activeRequest = null;
};

/** Load a captured locale/filter snapshot, accepting only the current generation. */
const loadItems = async () => {
  if (disposed) return;
  invalidatePendingLoad();
  const generation = requestGeneration;
  const controller = new AbortController();
  activeRequest = controller;
  const params: { locale: string; category_id?: string; search?: string } = { locale: locale.value };
  if (selectedCategory.value) params.category_id = selectedCategory.value;
  if (searchQuery.value) params.search = searchQuery.value;
  loading.value = true;
  loadError.value = null;
  try {
    const response = await faqApi.index(params, { signal: controller.signal, skipErrorFlash: true });
    if (disposed || generation !== requestGeneration) return;
    items.value = response.data.data || [];
    categories.value = response.data.categories || [];
  } catch (error) {
    if (disposed || generation !== requestGeneration || controller.signal.aborted) return;
    const kind = classifyApiErrorForFlash(error);
    loadError.value = kind ? GLOBAL_API_ERROR_MESSAGE_KEYS[kind] : 'errors.generic';
    console.error('Failed to load FAQ items:', error);
    items.value = [];
    categories.value = [];
  } finally {
    if (!disposed && generation === requestGeneration) {
      activeRequest = null;
      loading.value = false;
    }
  }
};

/** Debounce text search while invalidating old responses before the delay begins. */
const scheduleSearch = () => {
  if (disposed) return;
  invalidatePendingLoad();
  searchTimeout = setTimeout(() => {
    searchTimeout = null;
    void loadItems();
  }, 300);
};

/** Coalesce Enter with model updates so one search intent produces one request. */
const handleSearch = (query: string) => {
  if (searchQuery.value !== query) searchQuery.value = query;
  else scheduleSearch();
};

/** Apply feedback only while the originating FAQ generation is still displayed. */
const handleHelpful = async (itemId: string) => {
  const item = items.value.find(i => i.id === itemId);
  if (!item) return;
  const generation = requestGeneration;
  try {
    await faqApi.markHelpful(item.slug);
    if (!disposed && generation === requestGeneration) {
      item.helpful_count = (item.helpful_count || 0) + 1;
    }
  } catch (error) {
    if (!disposed && generation === requestGeneration) console.error('Failed to mark as helpful:', error);
  }
};

watch([locale, selectedCategory, searchQuery], ([nextLocale, nextCategory], [previousLocale, previousCategory]) => {
  if (nextLocale !== previousLocale || nextCategory !== previousCategory) {
    if (nextLocale !== previousLocale) {
      items.value = [];
      categories.value = [];
    }
    void loadItems();
  } else scheduleSearch();
}, { flush: 'sync' });

onMounted(() => { void loadItems(); });
onBeforeUnmount(() => {
  disposed = true;
  invalidatePendingLoad();
});

</script>

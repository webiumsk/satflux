<template>
  <div class="min-h-screen bg-gray-900">
    <PublicHeader />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-white mb-4">{{ t('support.title') }}</h1>
        <p class="text-xl text-gray-400">{{ t('support.subtitle') }}</p>
      </div>

      <!-- Quick Links -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <!-- Documentation Link -->
        <router-link
          to="/documentation"
          class="bg-gray-800 border border-gray-700 hover:border-indigo-500/50 rounded-xl p-6 transition-all group"
        >
          <div class="flex items-center justify-center w-12 h-12 bg-indigo-500/10 rounded-lg mb-4">
            <svg
              class="w-6 h-6 text-indigo-400"
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
          <h3 class="text-xl font-semibold text-white mb-2 group-hover:text-indigo-400 transition-colors">
            {{ t('support.documentation') }}
          </h3>
          <p class="text-gray-400 text-sm">
            {{ t('support.documentation_description') }}
          </p>
        </router-link>

        <!-- FAQ Link -->
        <router-link
          to="/faq"
          class="bg-gray-800 border border-gray-700 hover:border-green-500/50 rounded-xl p-6 transition-all group"
        >
          <div class="flex items-center justify-center w-12 h-12 bg-green-500/10 rounded-lg mb-4">
            <svg
              class="w-6 h-6 text-green-400"
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
          <h3 class="text-xl font-semibold text-white mb-2 group-hover:text-green-400 transition-colors">
            {{ t('support.faq') }}
          </h3>
          <p class="text-gray-400 text-sm">
            {{ t('support.faq_description') }}
          </p>
        </router-link>

        <!-- Support Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
          <div
            class="flex items-center justify-center w-12 h-12 bg-purple-500/10 rounded-lg mb-4"
          >
            <svg
              class="w-6 h-6 text-purple-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">
            {{ t('support.technical_support') }}
          </h3>
          <p class="text-gray-400 text-sm mb-4">
            {{ t('support.technical_support_description') }}
          </p>
          <a
            href="mailto:support@satflux.io"
            class="text-purple-400 hover:text-purple-300 font-medium text-sm"
          >
            support@satflux.io
          </a>
        </div>
      </div>

      <!-- FAQ Section -->
      <div class="mb-12">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-2xl font-bold text-white mb-2">{{ t('support.frequently_asked_questions') }}</h2>
            <p class="text-gray-400">{{ t('support.faq_section_description') }}</p>
          </div>
          <router-link
            to="/faq"
            class="text-indigo-400 hover:text-indigo-300 text-sm font-medium"
          >
            {{ t('support.view_all_faq') }} →
          </router-link>
        </div>

        <!-- FAQ Search -->
        <div class="mb-6">
          <FaqSearch
            v-model="searchQuery"
            :placeholder="t('faq.search_placeholder')"
            @search="handleSearch"
          />
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
          <svg
            class="animate-spin h-8 w-8 text-indigo-500"
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
          :items="faqItems"
          :search-query="searchQuery"
          @helpful="handleHelpful"
        />

        <!-- Empty State -->
        <div
          v-if="!loading && faqItems.length === 0"
          class="text-center py-12 bg-gray-800/50 rounded-xl border border-gray-700/50"
        >
          <p class="text-gray-400">{{ t('faq.no_items_found') }}</p>
        </div>
      </div>

      <!-- Additional Support Options -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Pricing Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
          <div
            class="flex items-center justify-center w-12 h-12 bg-indigo-500/10 rounded-lg mb-4"
          >
            <svg
              class="w-6 h-6 text-indigo-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">
            {{ t('support.pricing_subscriptions') }}
          </h3>
          <p class="text-gray-400 text-sm mb-4">
            {{ t('support.pricing_description') }}
          </p>
          <router-link
            to="/#pricing"
            class="text-indigo-400 hover:text-indigo-300 font-medium text-sm"
          >
            {{ t('support.view_pricing') }} →
          </router-link>
        </div>

        <!-- Login Card (if not authenticated) -->
        <div
          v-if="!isAuthenticated"
          class="bg-gray-800 border border-gray-700 rounded-xl p-6"
        >
          <div
            class="flex items-center justify-center w-12 h-12 bg-blue-500/10 rounded-lg mb-4"
          >
            <svg
              class="w-6 h-6 text-blue-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">
            {{ t('support.are_you_logged_in') }}
          </h3>
          <p class="text-gray-400 text-sm mb-4">
            {{ t('support.login_description') }}
          </p>
          <div class="flex flex-col gap-2">
            <router-link
              to="/login"
              class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
            >
              {{ t('auth.sign_in') }}
            </router-link>
            <router-link
              to="/register"
              class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 transition-colors"
            >
              {{ t('support.create_account') }}
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../store/auth';
import { faqApi } from '../services/api';
import PublicHeader from "../components/layout/PublicHeader.vue";
import AppFooter from "../components/layout/AppFooter.vue";
import FaqAccordion from '../components/faq/FaqAccordion.vue';
import FaqSearch from '../components/faq/FaqSearch.vue';

const { t } = useI18n();
const authStore = useAuthStore();

const isAuthenticated = computed(() => authStore.isAuthenticated);

const loading = ref(false);
const faqItems = ref<any[]>([]);
const searchQuery = ref('');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const loadFaqItems = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }
    // Limit to first 10 items for preview
    const response = await faqApi.index(params);
    const allItems = response.data.data || [];
    faqItems.value = allItems.slice(0, 10); // Show first 10
  } catch (error) {
    console.error('Failed to load FAQ items:', error);
    faqItems.value = [];
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
    loadFaqItems();
  }, 300);
};

const handleHelpful = async (itemId: string) => {
  const item = faqItems.value.find(i => i.id === itemId);
  if (!item || !item.slug) return;
  
  try {
    await faqApi.markHelpful(item.slug);
    item.helpful_count = (item.helpful_count || 0) + 1;
  } catch (error) {
    console.error('Failed to mark as helpful:', error);
  }
};

onMounted(() => {
  loadFaqItems();
});
</script>

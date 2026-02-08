<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">{{ t('admin.dashboard.title') }}</h1>
      <p class="text-gray-400 mt-1">{{ t('admin.dashboard.description') }}</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Users Card -->
      <div
        v-if="userRole === 'admin'"
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group hover:border-indigo-500/50 transition-all cursor-pointer"
        @click="$router.push('/admin/users')"
      >
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="w-24 h-24 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('admin.dashboard.users') }}</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : userCount }}</h3>
          <div class="mt-4">
            <span class="text-sm font-medium text-indigo-400 hover:text-indigo-300 flex items-center">
              {{ t('admin.dashboard.manage_users') }}
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
              </svg>
            </span>
          </div>
        </div>
      </div>

      <!-- Documentation Card -->
      <div
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group hover:border-blue-500/50 transition-all cursor-pointer"
        @click="$router.push('/admin/documentation')"
      >
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="w-24 h-24 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('admin.dashboard.documentation') }}</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : docCount }}</h3>
          <div class="mt-4">
            <span class="text-sm font-medium text-blue-400 hover:text-blue-300 flex items-center">
              {{ t('admin.dashboard.manage_documentation') }}
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
              </svg>
            </span>
          </div>
        </div>
      </div>

      <!-- FAQ Card -->
      <div
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group hover:border-green-500/50 transition-all cursor-pointer"
        @click="$router.push('/admin/faq')"
      >
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <svg class="w-24 h-24 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">{{ t('admin.dashboard.faq') }}</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : faqCount }}</h3>
          <div class="mt-4">
            <span class="text-sm font-medium text-green-400 hover:text-green-300 flex items-center">
              {{ t('admin.dashboard.manage_faq') }}
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
              </svg>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Admin Sections Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- User Management (Admin only) -->
      <div
        v-if="userRole === 'admin'"
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-indigo-500/50 transition-all group cursor-pointer"
        @click="$router.push('/admin/users')"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="p-3 bg-indigo-500/10 rounded-lg">
            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">{{ t('admin.dashboard.user_management') }}</h3>
        <p class="text-gray-400 text-sm mb-4">{{ t('admin.dashboard.user_management_desc') }}</p>
        <span class="text-sm text-indigo-400 font-medium">{{ t('admin.dashboard.view_section') }}</span>
      </div>

      <!-- Documentation Articles -->
      <div
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-blue-500/50 transition-all group cursor-pointer"
        @click="$router.push('/admin/documentation')"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="p-3 bg-blue-500/10 rounded-lg">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">{{ t('admin.dashboard.documentation_articles') }}</h3>
        <p class="text-gray-400 text-sm mb-4">{{ t('admin.dashboard.documentation_articles_desc') }}</p>
        <span class="text-sm text-blue-400 font-medium">{{ t('admin.dashboard.view_section') }}</span>
      </div>

      <!-- Documentation Categories -->
      <div
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-blue-500/50 transition-all group cursor-pointer"
        @click="$router.push('/admin/documentation/categories')"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="p-3 bg-blue-500/10 rounded-lg">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">{{ t('admin.dashboard.documentation_categories') }}</h3>
        <p class="text-gray-400 text-sm mb-4">{{ t('admin.dashboard.documentation_categories_desc') }}</p>
        <span class="text-sm text-blue-400 font-medium">{{ t('admin.dashboard.view_section') }}</span>
      </div>

      <!-- FAQ Items -->
      <div
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-green-500/50 transition-all group cursor-pointer"
        @click="$router.push('/admin/faq')"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="p-3 bg-green-500/10 rounded-lg">
            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <svg class="w-5 h-5 text-gray-400 group-hover:text-green-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">{{ t('admin.dashboard.faq_items') }}</h3>
        <p class="text-gray-400 text-sm mb-4">{{ t('admin.dashboard.faq_items_desc') }}</p>
        <span class="text-sm text-green-400 font-medium">{{ t('admin.dashboard.view_section') }}</span>
      </div>

      <!-- Support Tools (if support role) -->
      <div
        v-if="userRole === 'support' || userRole === 'admin'"
        class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-purple-500/50 transition-all group cursor-pointer"
        @click="$router.push('/support/wallet-connections')"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="p-3 bg-purple-500/10 rounded-lg">
            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">{{ t('admin.dashboard.wallet_connections') }}</h3>
        <p class="text-gray-400 text-sm mb-4">{{ t('admin.dashboard.wallet_connections_desc') }}</p>
        <span class="text-sm text-purple-400 font-medium">{{ t('admin.dashboard.view_section') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import { adminDocumentationApi, adminFaqApi } from '../../services/api';
import api from '../../services/api';

const { t } = useI18n();
const authStore = useAuthStore();

const loading = ref(false);
const userCount = ref(0);
const docCount = ref(0);
const faqCount = ref(0);

const userRole = computed(() => authStore.user?.role || '');

const loadStats = async () => {
  loading.value = true;
  try {
    // Load documentation count
    try {
      const docResponse = await adminDocumentationApi.articles.index();
      docCount.value = docResponse.data.meta?.total || docResponse.data.data?.length || 0;
    } catch (error) {
      console.error('Failed to load documentation count:', error);
    }

    // Load FAQ count
    try {
      const faqResponse = await adminFaqApi.items.index();
      faqCount.value = faqResponse.data.meta?.total || faqResponse.data.data?.length || 0;
    } catch (error) {
      console.error('Failed to load FAQ count:', error);
    }

    // Load user count (admin only)
    if (userRole.value === 'admin') {
      try {
        const userResponse = await api.get('/admin/users', { params: { per_page: 1 } });
        userCount.value = userResponse.data.meta?.total || 0;
      } catch (error) {
        console.error('Failed to load user count:', error);
      }
    }
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadStats();
});
</script>


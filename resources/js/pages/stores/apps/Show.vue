<template>
  <AppLayout>
    <div class="flex bg-gray-900 overflow-hidden min-h-screen">
      <!-- Sidebar -->
      <StoreSidebar
        :store="store"
        :apps="apps"
        @show-settings="handleShowSettings"
        @show-section="handleShowSection"
      />

      <!-- Main Content -->
      <div class="flex-1 flex flex-col overflow-hidden bg-gray-900 border-l border-gray-800">
        
        <div v-if="app" class="flex-1 overflow-hidden flex flex-col">
            <!-- Child Components handle their own header and content scrolling, 
                 or we can lift the scroll container here if child components are just content.
                 Existing components seem to handle form layout.
                 We will pass app and store to them.
            -->
            
            <PointOfSaleShow 
                v-if="app.app_type === 'PointOfSale'" 
                :app="app" 
                :store="store" 
            />
            
            <PayButtonShow
                v-else-if="app.app_type === 'PaymentButton'"
                :app="app"
                :store="store"
            />

            <TicketsShow
                v-else-if="app.app_type === 'Tickets'"
                :app="app"
                :store="store"
            />

            <!-- Fallback for other types -->
            <div v-else class="flex items-center justify-center h-full bg-gray-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="bg-gray-800 shadow rounded-lg p-6 border border-gray-700">
                        <h1 class="text-3xl font-bold text-white mb-4">{{ t('apps.app_type_not_supported') }}</h1>
                        <p class="text-sm text-gray-400">Type: {{ app.app_type }}</p>
                        <p class="mt-4 text-gray-300">{{ t('apps.app_type_not_supported_description') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center h-screen bg-gray-900">
           <div class="h-16 w-16 bg-gray-800 rounded-full flex items-center justify-center mb-4">
                <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-gray-400 font-medium">App not found</p>
            <Link :href="`/stores/${store.id}/apps`" class="mt-4 text-indigo-400 hover:text-indigo-300 transition-colors text-sm">Return to Apps List</Link>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '../../../components/layout/AppLayout.vue';
import StoreSidebar from '../../../components/stores/StoreSidebar.vue';
import PointOfSaleShow from '../PointOfSaleShow.vue';
import PayButtonShow from '../PayButtonShow.vue';
import TicketsShow from '../TicketsShow.vue';

const { t } = useI18n();

const props = defineProps<{
  store: any;
  app: any;
  apps: any[];
}>();

function handleShowSettings() {
  router.visit(`/stores/${props.store.id}?section=settings`);
}

function handleShowSection(section: string) {
  router.visit(`/stores/${props.store.id}?section=${section}`);
}
</script>

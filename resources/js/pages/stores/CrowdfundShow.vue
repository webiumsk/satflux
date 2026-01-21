<template>
  <AppShowLayout ref="layoutRef">
    <template #default="{ app, store }">
      <!-- Header -->
      <AppShowHeader
        :title="app.name || 'Crowdfund'"
        :subtitle="`Crowdfund - ${store.name}`"
        :app-url="btcpayAppUrl"
        open-button-text="Open Crowdfund"
        form-id="crowdfund-form"
        save-button-text="Save Settings"
        saving-text="Saving..."
        :saving="formRef?.saving"
        :error="formRef?.error"
        :success="formRef?.success"
      >
        <template #actions>
            <button
              type="button"
              @click="showDeleteModal = true"
              :disabled="deleting"
              class="inline-flex items-center px-4 py-2 border border-red-500/30 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 transition-colors"
            >
               <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              Delete App
            </button>
        </template>
      </AppShowHeader>

      <CrowdfundForm
        v-if="app"
        ref="formRef"
        :app="app"
        :store="store"
      />

    </template>
  </AppShowLayout>

  <DeleteAppModal
    :is-open="showDeleteModal"
    :app-name="layoutRef?.app?.name || ''"
    :deleting="deleting"
    :error="deleteError"
    @close="showDeleteModal = false"
    @delete="handleDelete"
  />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import AppShowHeader from '../../components/stores/AppShowHeader.vue';
import DeleteAppModal from '../../components/stores/DeleteAppModal.vue';
import CrowdfundForm from './CrowdfundForm.vue';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);

const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const formRef = ref<InstanceType<typeof CrowdfundForm> | null>(null);

const showDeleteModal = ref(false);
const deleteError = ref('');
const deleting = ref(false);

const btcpayAppUrl = computed(() => {
  const app = layoutRef.value?.app;
  if (!app) return '';
  const baseUrl = import.meta.env.VITE_BTCPAY_BASE_URL || 'https://pay.dvadsatjeden.org';
  
  let id = app.btcpay_app_id || 
              (app.config && app.config.id) ||
              (app.config && app.config.appId);
  
  if (!id && app.btcpay_app_url) {
    const urlParts = app.btcpay_app_url.split('/');
    id = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }

  if (!id) return '';
  return `${baseUrl}/apps/${id}/crowdfund`;
});

async function handleDelete() {
  const app = layoutRef.value?.app;
  if (!app) return;
  
  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    router.push({ name: 'stores-show', params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}
</script>

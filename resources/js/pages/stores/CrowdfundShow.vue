<template>
  <AppShowLayout ref="layoutRef">
    <template #default="{ app, store }">
      <!-- Header -->
      <AppShowHeader
        title="Update Crowdfund"
        :app-url="app.btcpay_app_url"
        open-button-text="Open Crowdfund"
        form-id="crowdfund-form"
        save-button-text="Update Crowdfund"
        saving-text="Saving..."
        :saving="crowdfundFormRef?.saving || false"
        :error="crowdfundFormRef?.error || ''"
        :success="crowdfundFormRef?.success || ''"
      />

      <!-- Crowdfund Form -->
      <CrowdfundForm 
        ref="crowdfundFormRef"
        :app="app" 
        :store="store" 
      />

      <!-- Delete Button (outside form) -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <div class="flex justify-start">
          <button
            type="button"
            @click="showDeleteModal = true"
            :disabled="crowdfundFormRef?.saving || deleting"
            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
          >
            Delete App
          </button>
        </div>
      </div>
    </template>
  </AppShowLayout>

  <!-- Delete Confirmation Modal -->
  <DeleteAppModal
    :is-open="showDeleteModal"
    :app-name="layoutRef?.app?.name || ''"
    :deleting="deleting"
    :error="deleteError"
    @close="showDeleteModal = false; deleteError = ''"
    @delete="handleDelete"
  />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import CrowdfundForm from './CrowdfundForm.vue';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import AppShowHeader from '../../components/stores/AppShowHeader.vue';
import DeleteAppModal from '../../components/stores/DeleteAppModal.vue';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const crowdfundFormRef = ref<InstanceType<typeof CrowdfundForm> | null>(null);
const showDeleteModal = ref(false);
const deleteError = ref('');
const deleting = ref(false);

async function handleDelete() {
  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    
    // Redirect to store page after successful deletion
    router.push({ name: 'stores-show', params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}

// Expose showDeleteModal to CrowdfundForm
defineExpose({
  showDeleteModal,
});
</script>


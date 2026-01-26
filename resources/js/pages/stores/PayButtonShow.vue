<template>
  <AppShowLayout ref="layoutRef">
    <template #default="{ app, store }">
      <!-- Header -->
      <AppShowHeader
        :title="app.name || 'Pay Button'"
        :subtitle="`Pay Button - ${store.name}`"
        :app-url="btcpayAppUrl"
        open-button-text="Open Pay Button"
        form-id="paybutton-form"
        save-button-text="Generate Code"
        saving-text="Generating..."
        :saving="formRef?.generating"
        :error="formRef?.error"
        :success="formRef?.success"
      />

      <PayButtonForm
        v-if="app"
        ref="formRef"
        :app="app"
        :store="store"
        @delete="showDeleteModal = true"
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
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAppsStore } from "../../store/apps";
import AppShowLayout from "../../components/stores/AppShowLayout.vue";
import AppShowHeader from "../../components/stores/AppShowHeader.vue";
import DeleteAppModal from "../../components/stores/DeleteAppModal.vue";
import PayButtonForm from "./PayButtonForm.vue";

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);

const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const formRef = ref<InstanceType<typeof PayButtonForm> | null>(null);

const showDeleteModal = ref(false);
const deleteError = ref("");
const deleting = ref(false);

const btcpayAppUrl = computed(() => {
  const app = layoutRef.value?.app;
  if (!app) return "";
  const baseUrl = import.meta.env.VITE_BTCPAY_BASE_URL || "https://satflux.org";

  let id =
    app.btcpay_app_id ||
    (app.config && app.config.id) ||
    (app.config && app.config.appId);

  if (!id && app.btcpay_app_url) {
    const urlParts = app.btcpay_app_url.split("/");
    id = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }

  if (!id) return "";
  return `${baseUrl}/apps/${id}/payment-button`;
});

async function handleDelete() {
  const app = layoutRef.value?.app;
  if (!app) return;

  deleting.value = true;
  deleteError.value = "";

  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    router.push({ name: "stores-show", params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || "Failed to delete app";
  } finally {
    deleting.value = false;
  }
}
</script>




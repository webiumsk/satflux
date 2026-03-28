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
      />

      <CrowdfundForm
        v-if="app"
        ref="formRef"
        :app="app"
        :store="store"
        :archiving="archiving"
        @delete="showDeleteModal = true"
        @archive="canArchiveApp ? handleArchive() : showArchiveUpgrade()"
        @unarchive="handleUnarchive"
      />
    </template>
  </AppShowLayout>

  <UpgradeModal
    :show="showArchiveUpgradeModal"
    :message="t('stores.archive_app_available_in_pro_message')"
    :limits="[]"
    recommended-plan="pro"
    :upgrade-button-text="t('stores.upgrade_to_pro')"
    @close="showArchiveUpgradeModal = false"
  />

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
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { useAppsStore } from "../../store/apps";
import { useFlashStore } from "../../store/flash";
import AppShowLayout from "../../components/stores/AppShowLayout.vue";
import AppShowHeader from "../../components/stores/AppShowHeader.vue";
import DeleteAppModal from "../../components/stores/DeleteAppModal.vue";
import UpgradeModal from "../../components/stores/UpgradeModal.vue";
import CrowdfundForm from "./CrowdfundForm.vue";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);

const planCode = computed(() => (authStore.user?.plan?.code ?? "free") as string);
const userRole = computed(() => (authStore.user?.role ?? "") as string);
const canArchiveApp = computed(
  () =>
    planCode.value === "pro" ||
    planCode.value === "enterprise" ||
    userRole.value === "admin" ||
    userRole.value === "support",
);

const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const formRef = ref<InstanceType<typeof CrowdfundForm> | null>(null);

const showDeleteModal = ref(false);
const showArchiveUpgradeModal = ref(false);
const deleteError = ref("");
const deleting = ref(false);
const archiving = ref(false);

const btcpayAppUrl = computed(() => {
  const app = layoutRef.value?.app;
  if (!app) return "";
  const baseUrl = import.meta.env.VITE_BTCPAY_BASE_URL || "https://satflux.io";

  let id =
    app.btcpay_app_id ||
    (app.config && app.config.id) ||
    (app.config && app.config.appId);

  if (!id && app.btcpay_app_url) {
    const urlParts = app.btcpay_app_url.split("/");
    id = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }

  if (!id) return "";
  return `${baseUrl}/apps/${id}/crowdfund`;
});

function showArchiveUpgrade() {
  showArchiveUpgradeModal.value = true;
}

async function handleArchive() {
  archiving.value = true;
  try {
    await appsStore.archiveApp(storeId.value, appId.value);
    flashStore.success(t("stores.app_archived"));
    router.push({ name: "stores-show", params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || "Failed to archive app";
  } finally {
    archiving.value = false;
  }
}

async function handleUnarchive() {
  archiving.value = true;
  try {
    const updatedApp = await appsStore.unarchiveApp(storeId.value, appId.value);
    flashStore.success(t("stores.app_unarchived"));
    const layoutApp = layoutRef.value?.app;
    if (layoutApp && typeof layoutApp === "object" && "value" in layoutApp && updatedApp) {
      layoutApp.value = updatedApp;
    }
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || "Failed to unarchive app";
  } finally {
    archiving.value = false;
  }
}

async function handleDelete() {
  const app = layoutRef.value?.app;
  if (!app) return;

  deleting.value = true;
  deleteError.value = "";

  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    router.push({ name: "stores-show", params: { id: storeId.value } });
  } catch (err: any) {
    const msg = err.response?.data?.message || "Failed to delete app";
    deleteError.value = msg;
    flashStore.error(msg);
  } finally {
    deleting.value = false;
  }
}
</script>

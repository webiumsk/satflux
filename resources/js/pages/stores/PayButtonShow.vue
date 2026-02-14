<template>
  <AppShowLayout ref="layoutRef" :store="store" :app="app">
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
      />

      <!-- Archive/Unarchive & Delete Buttons -->
      <div v-if="app" class="mt-6 pt-6 border-t border-gray-700/50 flex flex-wrap gap-3">
        <button
          type="button"
          @click="app?.archived ? handleUnarchive() : (canArchiveApp ? handleArchive() : showArchiveUpgrade())"
          :disabled="deleting || archiving"
          class="inline-flex items-center px-4 py-2 border rounded-xl text-sm font-medium transition-colors"
          :class="app?.archived
            ? 'border-green-600 text-green-400 hover:bg-green-600 hover:text-white'
            : 'border-amber-600 text-amber-400 hover:bg-amber-600 hover:text-white'"
        >
          <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              v-if="app?.archived"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
            />
          </svg>
          {{ archiving
            ? (app?.archived ? t('stores.unarchiving') : t('stores.archiving'))
            : (app?.archived ? t('stores.unarchive_app') : t('stores.archive_app')) }}
        </button>
        <button
          type="button"
          @click="showDeleteModal = true"
          :disabled="deleting"
          class="inline-flex items-center px-4 py-2 border border-red-600 rounded-xl text-sm font-medium text-red-400 hover:bg-red-600 hover:text-white transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          {{ t('stores.delete_app') }}
        </button>
      </div>
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
import PayButtonForm from "./PayButtonForm.vue";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const props = defineProps<{ store?: any; app?: any }>();
const storeId = computed(() => props.store?.id ?? route.params.id as string);
const appId = computed(() => props.app?.id ?? route.params.appId as string);

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
const formRef = ref<InstanceType<typeof PayButtonForm> | null>(null);

const showDeleteModal = ref(false);
const showArchiveUpgradeModal = ref(false);
const deleteError = ref("");
const deleting = ref(false);
const archiving = ref(false);

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
    deleteError.value = err.response?.data?.message || "Failed to delete app";
  } finally {
    deleting.value = false;
  }
}
</script>




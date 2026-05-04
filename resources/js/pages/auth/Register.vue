<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden"
  >
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gray-900">
      <div
        class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"
      ></div>
      <div
        class="absolute bottom-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000"
      ></div>
    </div>

    <div class="max-w-md w-full space-y-8 relative z-10">
      <div class="text-center">
        <router-link to="/" class="inline-block">
          <span
            class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-500 tracking-tight"
            ><span class="uppercase">satflux</span>.io</span
          >
        </router-link>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
          {{ t("auth.create_account") }}
        </h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t("auth.join_future_payments") }}
        </p>
      </div>

      <div
        class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl"
      >
        <div
          class="grid gap-1 mb-6 rounded-xl border border-gray-600 p-1"
          :class="showPasswordlessTabs ? 'grid-cols-3' : 'grid-cols-2'"
        >
          <button
            type="button"
            class="py-2.5 px-1 text-xs sm:text-sm font-semibold rounded-lg transition-colors text-center leading-tight"
            :class="
              authMethodTab === 'guest'
                ? 'bg-indigo-600 text-white shadow'
                : 'text-gray-400 hover:text-white hover:bg-gray-700/60'
            "
            @click="authMethodTab = 'guest'"
          >
            {{ t("auth.tab_guest") }}
          </button>
          <button
            type="button"
            class="py-2.5 px-1 text-xs sm:text-sm font-semibold rounded-lg transition-colors text-center leading-tight"
            :class="
              authMethodTab === 'email'
                ? 'bg-indigo-600 text-white shadow'
                : 'text-gray-400 hover:text-white hover:bg-gray-700/60'
            "
            @click="authMethodTab = 'email'"
          >
            {{ t("auth.tab_email") }}
          </button>
          <button
            v-if="showPasswordlessTabs"
            type="button"
            class="py-2.5 px-1 text-xs sm:text-sm font-semibold rounded-lg transition-colors text-center leading-tight"
            :class="
              authMethodTab === 'other'
                ? 'bg-indigo-600 text-white shadow'
                : 'text-gray-400 hover:text-white hover:bg-gray-700/60'
            "
            @click="authMethodTab = 'other'"
          >
            {{ t("auth.tab_lightning_nostr") }}
          </button>
        </div>

        <div v-show="authMethodTab === 'guest'" class="space-y-4 mb-2">
          <p class="text-sm text-gray-300 leading-relaxed">
            {{ t("auth.guest_mode_short_intro") }}
          </p>
          <div
            class="rounded-lg border border-amber-500/25 bg-amber-950/30 px-3 py-2.5"
            role="note"
          >
            <p class="text-xs font-semibold text-amber-200/95 mb-1">
              {{ t("auth.guest_mode_limits_heading") }}
            </p>
            <p class="text-xs text-amber-100/85 leading-relaxed">
              {{ t("auth.guest_mode_limitations") }}
            </p>
          </div>
          <div
            class="rounded-xl border border-indigo-400/50 bg-gradient-to-r from-indigo-500/15 to-purple-500/15 p-4 space-y-3 shadow-inner"
          >
            <button
              type="button"
              :disabled="guestLoading"
              class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-lg disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-gray-800"
              @click="showGuestBackupWizard = true"
            >
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              {{
                guestLoading
                  ? t("auth.starting_guest_session")
                  : t("auth.continue_without_registration")
              }}
            </button>
            <p class="text-xs text-indigo-100/90 text-center">
              {{ t("auth.guest_cta_hint") }}
            </p>
            <button
              type="button"
              class="w-full font-medium text-indigo-200 hover:text-white text-sm focus:outline-none focus:underline rounded"
              @click="showGuestRestoreModal = true"
            >
              {{ t("auth.restore_guest_session") }}
            </button>
          </div>
        </div>

        <div v-show="authMethodTab === 'email'">
          <RegistrationForm :show-alternative-auth="false" />
        </div>

        <div
          v-show="authMethodTab === 'other' && showPasswordlessTabs"
          class="space-y-4"
        >
          <p class="text-sm text-gray-300 text-center">
            {{ t("auth.register_passwordless_hint") }}
          </p>
          <router-link
            :to="{ path: '/login', query: { tab: 'other' } }"
            class="group relative w-full flex justify-center items-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all shadow-lg shadow-indigo-600/20"
          >
            {{ t("auth.go_to_passwordless_login") }}
          </router-link>
        </div>
      </div>
    </div>

    <GuestBackupWizardModal
      :open="showGuestBackupWizard"
      @close="showGuestBackupWizard = false"
      @done="handleGuestEnrolled"
    />
    <GuestRestoreModal
      :open="showGuestRestoreModal"
      @close="showGuestRestoreModal = false"
      @success="redirectAfterGuestRestore"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import RegistrationForm from "../../components/auth/RegistrationForm.vue";
import GuestBackupWizardModal from "../../components/auth/GuestBackupWizardModal.vue";
import GuestRestoreModal from "../../components/auth/GuestRestoreModal.vue";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";
import { storeGuestMnemonic } from "../../services/guestRecovery";

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const guestLoading = ref(false);
const showGuestBackupWizard = ref(false);
const showGuestRestoreModal = ref(false);
const authMethodTab = ref<"guest" | "email" | "other">("guest");
const lnurlAuthEnabled = ref(false);
const nostrAuthEnabled = ref(false);

const showPasswordlessTabs = computed(
  () => lnurlAuthEnabled.value || nostrAuthEnabled.value,
);

watch(showPasswordlessTabs, (on: boolean) => {
  if (!on && authMethodTab.value === "other") {
    authMethodTab.value = "guest";
  }
});

onMounted(async () => {
  try {
    const [lnurlRes, nostrRes] = await Promise.all([
      api.get<{ enabled: boolean }>(`/lnurl-auth/enabled?_=${Date.now()}`),
      api.get<{ enabled: boolean }>(`/nostr-auth/enabled?_=${Date.now()}`),
    ]);
    lnurlAuthEnabled.value = lnurlRes.data?.enabled === true;
    nostrAuthEnabled.value = nostrRes.data?.enabled === true;
  } catch {
    lnurlAuthEnabled.value = false;
    nostrAuthEnabled.value = false;
  }
});

function redirectAfterGuestRestore(payload: { store_id?: string | null }) {
  const storeId = payload?.store_id;
  if (storeId) {
    router.replace(`/stores/${storeId}/wallet-connection`);
    return;
  }
  router.replace("/stores/create");
}

async function handleGuestEnrolled(payload: {
  recoveryPublicKeyHex: string;
  mnemonic: string;
}) {
  showGuestBackupWizard.value = false;
  guestLoading.value = true;
  try {
    const response = await authStore.continueAsGuest(
      payload.recoveryPublicKeyHex,
    );
    storeGuestMnemonic(payload.mnemonic);
    // Be resilient to slight response shape differences.
    let storeId =
      response?.store_id ??
      response?.data?.store_id ??
      null;

    if (!storeId) {
      try {
        const storesRes = await api.get("/stores");
        const firstStoreId = storesRes?.data?.data?.[0]?.id;
        storeId =
          typeof firstStoreId === "string" || typeof firstStoreId === "number"
            ? String(firstStoreId)
            : null;
      } catch {
        storeId = null;
      }
    }

    if (storeId) {
      router.replace(`/stores/${storeId}/wallet-connection`);
      return;
    }
    router.replace("/stores/create");
  } catch (err: any) {
    flashStore.error(
      err.response?.data?.message || "Unable to start guest session.",
    );
  } finally {
    guestLoading.value = false;
  }
}
</script>


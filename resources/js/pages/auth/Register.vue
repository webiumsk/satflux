<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden"
  >
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
          {{ t("auth.seed_first_register_subtitle") }}
        </p>
      </div>

      <div
        class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl space-y-4"
      >
        <p class="text-sm text-gray-300 leading-relaxed">
          {{ t("auth.seed_mode_short_intro") }}
        </p>
        <div
          class="rounded-lg border border-amber-500/25 bg-amber-950/30 px-3 py-2.5"
          role="note"
        >
          <p class="text-xs font-semibold text-amber-200/95 mb-1">
            {{ t("auth.seed_mode_limits_heading") }}
          </p>
          <p class="text-xs text-amber-100/85 leading-relaxed">
            {{ t("auth.seed_mode_limitations") }}
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
            {{
              guestLoading
                ? t("auth.starting_guest_session")
                : t("auth.create_with_recovery_phrase")
            }}
          </button>
          <button
            type="button"
            class="w-full font-medium text-indigo-200 hover:text-white text-sm focus:outline-none focus:underline rounded"
            @click="showGuestRestoreModal = true"
          >
            {{ t("auth.restore_with_recovery_phrase") }}
          </button>
        </div>
        <p class="text-center text-sm text-gray-400">
          {{ t("auth.already_have_email_account") }}
          <router-link
            :to="{ path: '/login', query: { tab: 'email' } }"
            class="font-medium text-indigo-400 hover:text-indigo-300"
          >
            {{ t("auth.sign_in") }}
          </router-link>
        </p>
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
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import GuestBackupWizardModal from "../../components/auth/GuestBackupWizardModal.vue";
import GuestRestoreModal from "../../components/auth/GuestRestoreModal.vue";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";
import { storeGuestMnemonic } from "../../services/guestRecovery";
import { initEvoluFromAccountSeedIfNeeded } from "../../services/accountSeed";

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const guestLoading = ref(false);
const showGuestBackupWizard = ref(false);
const showGuestRestoreModal = ref(false);

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
    const response = await authStore.continueAsGuest(payload.recoveryPublicKeyHex);
    storeGuestMnemonic(payload.mnemonic);
    await initEvoluFromAccountSeedIfNeeded(payload.mnemonic);
    let storeId = response?.store_id ?? response?.data?.store_id ?? null;

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
      err.response?.data?.message || "Unable to start session.",
    );
  } finally {
    guestLoading.value = false;
  }
}
</script>

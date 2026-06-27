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
        <AuthSeedGuestPanel
          variant="register"
          :primary-label="
            guestLoading
              ? t('auth.starting_guest_session')
              : t('auth.create_with_recovery_phrase')
          "
          :primary-disabled="guestLoading"
          @primary="showGuestBackupWizard = true"
          @secondary="showGuestRestoreModal = true"
        />
        <p class="text-center text-sm text-gray-400 pt-1">
          {{ t("auth.already_have_email_account") }}
          <router-link
            to="/login?legacy=1"
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
import AuthSeedGuestPanel from "../../components/auth/AuthSeedGuestPanel.vue";
import { useAuthStore } from "../../store/auth";
import { useStoresStore } from "../../store/stores";
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
  const storesStore = useStoresStore();
  void storesStore.fetchStores().then(() => {
    const storeId =
      payload?.store_id
      ?? storesStore.stores[0]?.id
      ?? null;
    if (storeId) {
      router.replace(`/stores/${storeId}/wallet-connection`);
      return;
    }
    router.replace("/stores/create");
  });
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

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
          v-if="!pendingEnrollment"
          variant="register"
          :primary-label="
            guestLoading
              ? t('auth.starting_guest_session')
              : t('auth.create_with_recovery_phrase')
          "
          :primary-disabled="guestLoading || configLoading || !registrationConfig || configError"
          @primary="showGuestBackupWizard = true"
          @secondary="showGuestRestoreModal = true"
        />
        <p v-if="configLoading" role="status" class="text-sm text-gray-300">{{ t("auth.turnstile_config_loading") }}</p>
        <div v-if="configError" role="alert" class="space-y-3">
          <p class="text-sm text-amber-200">{{ t("auth.turnstile_unavailable") }}</p>
          <button type="button" :disabled="configLoading" class="w-full rounded-lg border border-indigo-400/50 px-4 py-2 text-sm font-medium text-indigo-200 hover:bg-indigo-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400 disabled:opacity-50" @click="pendingEnrollment ? retryRegistration() : loadRegistrationConfig()">
            {{ t("auth.turnstile_retry") }}
          </button>
        </div>
        <section v-if="pendingEnrollment" class="space-y-4" aria-labelledby="registration-verification-title">
          <h3 id="registration-verification-title" class="text-lg font-semibold text-white">{{ t(registrationConfig?.enabled ? "auth.turnstile_title" : "auth.create_account") }}</h3>
          <p v-if="registrationConfig?.enabled" class="text-sm text-gray-300">{{ t("auth.turnstile_description") }}</p>
          <p v-if="guestLoading" role="status" class="text-sm text-indigo-200">{{ t("auth.starting_guest_session") }}</p>
          <TurnstileChallenge
            v-if="challengeActive && registrationConfig?.enabled && registrationConfig.siteKey"
            :key="challengeAttempt"
            :site-key="registrationConfig.siteKey"
            @verified="submitEnrollment"
          />
          <div v-if="registrationError" role="alert" class="space-y-3">
            <p class="text-sm text-amber-200">{{ registrationError }}</p>
            <button type="button" :disabled="guestLoading || configLoading" class="w-full rounded-lg border border-indigo-400/50 px-4 py-2 text-sm font-medium text-indigo-200 hover:bg-indigo-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400 disabled:opacity-50" @click="retryRegistration">
              {{ t("auth.turnstile_retry") }}
            </button>
          </div>
          <button type="button" :disabled="guestLoading" class="w-full rounded-lg px-4 py-2 text-sm font-medium text-gray-300 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400 disabled:opacity-50" @click="cancelRegistration">
            {{ t("auth.turnstile_back") }}
          </button>
        </section>
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
      v-if="!pendingEnrollment"
      :open="showGuestBackupWizard"
      @close="showGuestBackupWizard = false"
      @done="handleGuestEnrolled"
    />
    <GuestRestoreModal
      :open="showGuestRestoreModal"
      @close="showGuestRestoreModal = false"
      @success="redirectAfterGuestRestore"
    />
    <PasskeyEnrollOfferModal
      :open="showPasskeyOffer"
      context="register"
      @done="finishPasskeyOffer"
      @skip="finishPasskeyOffer"
    />
  </div>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import GuestBackupWizardModal from "../../components/auth/GuestBackupWizardModal.vue";
import GuestRestoreModal from "../../components/auth/GuestRestoreModal.vue";
import PasskeyEnrollOfferModal from "../../components/auth/PasskeyEnrollOfferModal.vue";
import AuthSeedGuestPanel from "../../components/auth/AuthSeedGuestPanel.vue";
import TurnstileChallenge from "../../components/auth/TurnstileChallenge.vue";
import { useAuthStore } from "../../store/auth";
import { useStoresStore } from "../../store/stores";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";
import { storeGuestMnemonic } from "../../services/guestRecovery";
import {
  initEvoluFromAccountSeedIfNeeded,
  isEvoluUnavailableError,
} from "../../services/accountSeed";
import { isPasskeyPrfSupported } from "../../services/deviceUnlock/passkeyPrf";

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const guestLoading = ref(false);
const showGuestBackupWizard = ref(false);
const showGuestRestoreModal = ref(false);
const showPasskeyOffer = ref(false);
const passkeyOfferRedirectPath = ref("");
const configLoading = ref(true);
const configError = ref(false);
const registrationConfig = ref<{ enabled: boolean; siteKey: string | null } | null>(null);
const pendingEnrollment = ref(false);
const challengeActive = ref(false);
const challengeAttempt = ref(0);
const registrationError = ref("");
// The confirmed phrase stays in this closure while verification or submission is retried.
let enrollment: { recoveryPublicKeyHex: string; mnemonic: string } | null = null;
let active = true;
let configGeneration = 0;

async function loadRegistrationConfig(): Promise<boolean> {
  const current = ++configGeneration;
  configLoading.value = true;
  configError.value = false;
  try {
    const { data } = await api.get("/config");
    if (!active || current !== configGeneration) return false;
    const settings: unknown = data?.turnstile;
    if (!settings || typeof settings !== "object" || !("enabled" in settings)
      || typeof settings.enabled !== "boolean" || !("action" in settings) || settings.action !== "register") {
      throw new Error("Registration configuration is unavailable.");
    }
    const siteKey = "site_key" in settings && typeof settings.site_key === "string" && settings.site_key.trim()
      ? settings.site_key.trim() : null;
    registrationConfig.value = { enabled: settings.enabled, siteKey };
    if (settings.enabled && !siteKey) throw new Error("Registration verification is unavailable.");
    return true;
  } catch {
    if (active && current === configGeneration) {
      registrationConfig.value = null;
      configError.value = true;
    }
    return false;
  } finally {
    if (active && current === configGeneration) configLoading.value = false;
  }
}

function cancelRegistration() {
  if (guestLoading.value) return;
  challengeActive.value = false;
  enrollment = null;
  pendingEnrollment.value = false;
  registrationError.value = "";
}

async function retryRegistration() {
  if (!active || guestLoading.value || configLoading.value || !enrollment) return;
  registrationError.value = "";
  if (!await loadRegistrationConfig() || !active || !enrollment) return;
  if (registrationConfig.value?.enabled) {
    challengeAttempt.value += 1;
    challengeActive.value = true;
  } else {
    await submitEnrollment();
  }
}

function redirectAfterGuestRestore(_payload: { store_id?: string | null }) {
  // A restored account is not a fresh guest: it may already have a connected
  // wallet or be fully upgraded. Go home and let the router's guest hard-gate
  // decide - it redirects to the wallet connection only when the account is
  // still a guest AND the primary store's wallet is not ready.
  // Path-based on purpose: the public marketing router has no "home" route
  // (a named location throws in matcher.resolve before any guard runs), but
  // its guard turns unknown paths into a full page load of the app bundle.
  void useStoresStore()
    .fetchStores()
    .finally(() => {
      router.replace("/dashboard");
    });
}

async function handleGuestEnrolled(payload: {
  recoveryPublicKeyHex: string;
  mnemonic: string;
}) {
  if (!active || guestLoading.value || pendingEnrollment.value) return;
  showGuestBackupWizard.value = false;
  enrollment = payload;
  pendingEnrollment.value = true;
  registrationError.value = "";
  if (!registrationConfig.value || configError.value || configLoading.value) return;
  if (registrationConfig.value.enabled) {
    challengeActive.value = true;
    return;
  }
  await submitEnrollment();
}

async function submitEnrollment(turnstileToken?: string) {
  if (!active || !enrollment || guestLoading.value || configLoading.value || configError.value || !registrationConfig.value) return;
  if (registrationConfig.value.enabled && (!challengeActive.value || !turnstileToken)) return;
  const payload = enrollment;
  // A submitted token is consumed even if the network/provisioning result is uncertain.
  challengeActive.value = false;
  guestLoading.value = true;
  registrationError.value = "";
  try {
    const response = await authStore.continueAsGuest(payload.recoveryPublicKeyHex, turnstileToken);
    if (!active) return;
    storeGuestMnemonic(payload.mnemonic);
    try {
      await initEvoluFromAccountSeedIfNeeded(payload.mnemonic);
    } catch (evoluError) {
      // The session is valid; a dead local-first storage (private window
      // without OPFS) only disables invoicing, it must not fail registration.
      if (!isEvoluUnavailableError(evoluError)) {
        throw evoluError;
      }
    }
    if (!active) return;
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

    if (!active) return;
    enrollment = null;
    pendingEnrollment.value = false;
    await redirectOrOfferPasskey(
      storeId ? `/stores/${storeId}/wallet-connection` : "/stores/create",
    );
  } catch (rawError) {
    if (!active) return;
    const err = asApiError(rawError);
    const code = err.response?.data?.code;
    registrationError.value = code === "turnstile_failed"
      ? t("auth.turnstile_error")
      : code === "turnstile_unavailable"
        ? t("auth.turnstile_unavailable")
        : err.response?.data?.message || t("auth.turnstile_registration_error");
    flashStore.error(registrationError.value);
  } finally {
    if (active) guestLoading.value = false;
  }
}

/**
 * First authenticated instant after registration = the one moment the
 * session phrase is guaranteed unlocked, so offer the account passkey now
 * ("sign in without your 24 words next time"). The redirect is only
 * deferred, never blocked: done and skip both land on the same target.
 */
async function redirectOrOfferPasskey(targetPath: string): Promise<void> {
  let offerPasskey = false;
  try {
    offerPasskey = await isPasskeyPrfSupported();
  } catch {
    offerPasskey = false;
  }
  if (!offerPasskey) {
    router.replace(targetPath);
    return;
  }
  passkeyOfferRedirectPath.value = targetPath;
  showPasskeyOffer.value = true;
}

function finishPasskeyOffer(): void {
  showPasskeyOffer.value = false;
  router.replace(passkeyOfferRedirectPath.value || "/stores/create");
}

onMounted(() => { void loadRegistrationConfig(); });
onBeforeUnmount(() => {
  active = false;
  configGeneration += 1;
  enrollment = null;
  challengeActive.value = false;
});
</script>

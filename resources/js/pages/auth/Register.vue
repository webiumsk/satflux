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

      <div class="rounded-xl border border-indigo-400/60 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 p-3 space-y-2 shadow-lg shadow-indigo-900/30">
              <button
                type="button"
                :disabled="guestLoading"
                class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-lg disabled:opacity-50"
                @click="showGuestBackupWizard = true"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                class="w-full font-medium text-indigo-200 hover:text-white text-sm"
                @click="showGuestRestoreModal = true"
              >
                {{ t("auth.restore_guest_session") }}
              </button>
            </div>

      <div
        class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl"
      >
        <div
          v-if="lnurlAuthEnabled || nostrAuthEnabled"
          class="flex rounded-xl border border-gray-600 p-1 mb-6 gap-1"
        >
          <button
            type="button"
            class="flex-1 py-2.5 text-sm font-semibold rounded-lg transition-colors"
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
            type="button"
            class="flex-1 py-2.5 text-sm font-semibold rounded-lg transition-colors"
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

        <div v-show="authMethodTab === 'email' || (!lnurlAuthEnabled && !nostrAuthEnabled)">
          <RegistrationForm :show-alternative-auth="false" />
          
        </div>

        <div
          v-show="authMethodTab === 'other' && (lnurlAuthEnabled || nostrAuthEnabled)"
          class="space-y-4"
        >
          <button
            v-if="lnurlAuthEnabled"
            type="button"
            class="group relative w-full flex justify-center items-center py-3 px-4 border border-gray-600 text-sm font-bold rounded-xl text-white bg-gray-700/50 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all"
            @click="router.push('/login')"
          >
            <svg
              class="w-5 h-5 mr-2 text-yellow-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 10V3L4 14h7v7l9-11h-7z"
              />
            </svg>
            {{ t("auth.lightning_login") }}
          </button>
          <button
            v-if="nostrAuthEnabled"
            type="button"
            class="group relative w-full flex justify-center items-center py-3 px-4 border border-gray-600 text-sm font-bold rounded-xl text-white bg-gray-700/50 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all"
            @click="router.push('/login')"
          >
            <span class="mr-2 text-lg">
              <svg class="w-5 h-5 text-purple-400" viewBox="0 0 750 750" fill="currentColor" aria-hidden="true">
                <g transform="matrix(0.1, 0, 0, -0.1, 0, 0)">
                  <path d="m5404,-1199c-53,-24 -107,-88 -129,-154c-10,-30 -20,-94 -23,-154c-3,-85 -1,-114 17,-172c40,-132 139,-253 321,-391c176,-134 230,-214 238,-352c11,-187 -57,-312 -211,-388c-82,-40 -140,-47 -266,-30c-85,11 -104,11 -153,-4c-31,-9 -67,-16 -80,-16c-14,0 -95,43 -182,95c-274,164 -364,189 -681,190c-253,0 -318,-10 -526,-81c-135,-46 -177,-64 -499,-222c-113,-55 -237,-110 -275,-121c-110,-35 -246,-51 -422,-51c-182,0 -202,-6 -182,-58c6,-15 16,-34 23,-42c29,-34 28,-39 -11,-28c-21,5 -45,16 -53,23c-43,38 -206,45 -326,15c-204,-53 -392,-228 -465,-436c-24,-67 -25,-122 -3,-131c10,-3 47,9 88,29c39,19 86,39 103,42c41,9 42,3 8,-100c-31,-96 -33,-162 -4,-198c11,-14 27,-26 35,-26c8,0 58,41 111,90c66,62 104,90 120,90c16,0 23,-4 19,-12c-39,-94 -47,-231 -15,-251c18,-11 26,-8 154,67c107,63 263,108 423,123c50,5 57,2 115,-37c34,-23 98,-59 143,-81c98,-47 342,-129 479,-159c55,-13 104,-26 108,-30c12,-11 -72,-117 -164,-207c-125,-122 -270,-235 -338,-261c-106,-41 -159,-103 -186,-217c-9,-38 -39,-90 -120,-206c-60,-85 -156,-221 -213,-304c-262,-374 -251,-362 -355,-385c-68,-15 -138,-53 -175,-94c-37,-42 -56,-100 -41,-127c11,-22 26,-23 65,-9c36,14 40,7 15,-28c-35,-50 -90,-172 -101,-228c-8,-40 -8,-68 0,-108c11,-54 40,-94 59,-81c6,3 29,43 52,88c46,93 78,135 200,262c47,50 110,128 139,175c90,145 218,323 429,595c196,253 230,293 274,310c85,34 152,104 171,179c11,41 23,52 239,215l229,172l125,45c69,24 129,44 134,44c11,0 -20,-72 -36,-85c-8,-5 -36,-70 -64,-143c-56,-146 -64,-207 -35,-268c9,-18 31,-43 49,-54c51,-32 101,-26 177,20c50,30 144,64 399,144c458,143 474,148 522,144l42,-3l11,-62c13,-72 35,-110 71,-124c39,-15 55,4 48,60c-10,80 4,80 86,-2c77,-77 140,-117 184,-117c34,0 42,24 21,67c-38,81 -231,366 -264,391c-32,25 -43,27 -126,27c-99,0 -55,12 -706,-194c-124,-40 -234,-74 -244,-77c-25,-8 -19,7 38,94c34,51 58,75 93,95c72,39 133,85 133,101c0,8 11,28 25,44l24,29l168,6c332,13 537,72 705,205c146,114 207,264 208,508c0,81 4,125 13,136c6,8 59,44 117,79c260,158 390,290 498,504c74,148 100,334 68,484c-37,175 -124,292 -342,460c-136,105 -165,138 -171,195c-6,53 7,64 112,94c55,15 107,20 235,21c206,3 233,18 102,56c-34,10 -59,22 -55,26c4,4 35,9 68,11c45,2 60,7 60,18c0,11 -46,30 -155,66c-126,41 -171,61 -235,104c-128,87 -204,106 -281,71z" />
                </g>
              </svg>
            </span>
            {{ t("auth.nostr_login") }}
          </button>
          <p class="text-xs text-gray-400 text-center">
            {{ t("auth.register_passwordless_hint") }}
          </p>
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
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import RegistrationForm from "../../components/auth/RegistrationForm.vue";
import GuestBackupWizardModal from "../../components/auth/GuestBackupWizardModal.vue";
import GuestRestoreModal from "../../components/auth/GuestRestoreModal.vue";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const guestLoading = ref(false);
const showGuestBackupWizard = ref(false);
const showGuestRestoreModal = ref(false);
const authMethodTab = ref<"email" | "other">("email");
const lnurlAuthEnabled = ref(false);
const nostrAuthEnabled = ref(false);

void (async () => {
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
})();

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
}) {
  showGuestBackupWizard.value = false;
  guestLoading.value = true;
  try {
    const response = await authStore.continueAsGuest(
      payload.recoveryPublicKeyHex,
    );
    // Be resilient to slight response shape differences.
    let storeId =
      response?.store_id ??
      response?.data?.store_id ??
      null;

    if (!storeId) {
      try {
        const storesRes = await api.get("/stores");
        const firstStoreId = storesRes?.data?.data?.[0]?.id;
        storeId = typeof firstStoreId === "string" ? firstStoreId : null;
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


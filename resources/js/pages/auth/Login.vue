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
          {{ t("auth.welcome_back") }}
        </h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t("auth.sign_in_to_account") }}
        </p>
      </div>

      <div
        class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl"
      >
        <div
          v-if="route.query.email_verified === '1'"
          class="mb-6 rounded-lg bg-green-500/10 border border-green-500/20 p-4"
        >
          <div class="flex items-start">
            <svg
              class="w-5 h-5 text-green-400 mt-0.5 mr-3 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              ></path>
            </svg>
            <p class="text-sm text-green-400">
              {{ t("auth.email_verified") }}
            </p>
          </div>
        </div>

        <form class="space-y-6" @submit.prevent="handleLogin">
          <div>
            <label
              for="email"
              class="block text-sm font-medium text-gray-300"
              >{{ t("auth.email") }}</label
            >
            <div class="mt-1">
              <input
                id="email"
                v-model="form.email"
                name="email"
                type="email"
                autocomplete="email"
                required
                class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                :placeholder="t('auth.email_placeholder')"
              />
            </div>
          </div>

          <div>
            <label
              for="password"
              class="block text-sm font-medium text-gray-300"
              >{{ t("auth.password") }}</label
            >
            <div class="mt-1">
              <input
                id="password"
                v-model="form.password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                :placeholder="t('auth.password_placeholder')"
              />
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input
                id="remember-me"
                v-model="form.remember"
                name="remember-me"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 rounded bg-gray-700"
              />
              <label for="remember-me" class="ml-2 block text-sm text-gray-300">
                {{ t("auth.remember_me") }}
              </label>
            </div>

            <div class="text-sm">
              <router-link
                to="/password/reset"
                class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors"
              >
                {{ t("auth.forgot_password") }}
              </router-link>
            </div>
          </div>

          <div class="space-y-4">
            <button
              type="submit"
              :disabled="loading"
              class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
            >
              {{ loading ? t("auth.signing_in") : t("auth.sign_in") }}
            </button>

            <div v-if="lnurlAuthEnabled || nostrAuthEnabled">
              <div class="relative mb-4">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-gray-600"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="px-2 bg-gray-800 text-gray-400">{{
                    t("auth.or_continue_with")
                  }}</span>
                </div>
              </div>

              <button
                v-if="lnurlAuthEnabled"
                type="button"
                @click="handleLnurlAuth"
                :disabled="lnurlLoading"
                class="group relative w-full flex justify-center items-center py-3 px-4 border border-gray-600 text-sm font-bold rounded-xl text-white bg-gray-700/50 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all"
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
                {{
                  lnurlLoading
                    ? t("auth.generating")
                    : t("auth.lightning_login")
                }}
              </button>
              <button
                v-if="nostrAuthEnabled"
                type="button"
                @click="showNostrModal = true"
                class="mt-3 group relative w-full flex justify-center items-center py-3 px-4 border border-gray-600 text-sm font-bold rounded-xl text-white bg-gray-700/50 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all"
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
            </div>
          </div>

          <div class="text-center text-sm">
            <span class="text-gray-400">{{ t("auth.dont_have_account") }}</span>
            <router-link
              to="/register"
              class="font-medium text-indigo-400 hover:text-indigo-300 ml-1 transition-colors"
            >
              {{ t("auth.sign_up") }}
            </router-link>
          </div>
        </form>
      </div>
    </div>

    <NostrAuthModal
      :open="showNostrModal"
      mode="login"
      @close="showNostrModal = false"
    />

    <!-- LNURL-auth QR step: use shared modal -->
    <LnurlQrModal
      v-if="showLnurlModal && !showEmailStep"
      :open="true"
      :title="t('auth.scan_with_lightning_wallet')"
      :lnurl="lnurlAuthUrl"
      :error="lnurlError"
      :polling="lnurlPolling"
      :expires-in-seconds="300"
      @close="closeLnurlModal"
      @regenerate="requestNewAuthChallenge"
    />

    <!-- LNURL-auth Email step (complete registration) -->
    <div
      v-if="showLnurlModal && showEmailStep"
      class="fixed z-50 inset-0 overflow-y-auto"
      @click.self="closeLnurlModal"
    >
      <div
        class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <div
          class="fixed inset-0 bg-gray-900 bg-opacity-90 transition-opacity backdrop-blur-sm"
          @click="closeLnurlModal"
        ></div>
        <div
          class="inline-block align-bottom bg-gray-800 border border-gray-700 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
          <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                <!-- Email Input Step -->
                <div>
                  <h3 class="text-lg leading-6 font-bold text-white mb-4">
                    {{ t("auth.complete_registration") }}
                  </h3>
                  <p class="text-sm text-gray-400 mb-6">
                    {{ t("auth.provide_email_to_complete") }}
                  </p>
                  <form @submit.prevent="handleCompleteRegistration">
                    <div class="mb-6">
                      <label
                        for="lnurl-email"
                        class="block text-sm font-medium text-gray-300 mb-2"
                      >
                        {{ t("auth.email") }}
                      </label>
                      <input
                        id="lnurl-email"
                        v-model="emailForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                        :placeholder="t('auth.email_placeholder')"
                      />
                      <div v-if="emailError" class="mt-2 text-sm text-red-400">
                        {{ emailError }}
                      </div>
                    </div>
                    <div class="flex justify-end gap-3">
                      <button
                        type="button"
                        @click="closeLnurlModal"
                        class="inline-flex justify-center rounded-lg border border-gray-600 px-4 py-2 bg-gray-700 text-base font-medium text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm transition-colors"
                      >
                        {{ t("common.cancel") }}
                      </button>
                      <button
                        type="submit"
                        :disabled="emailLoading"
                        class="inline-flex justify-center rounded-lg border border-transparent px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 sm:text-sm transition-colors"
                      >
                        {{
                          emailLoading ? t("auth.saving") : t("common.continue")
                        }}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { usePage } from "@inertiajs/vue3";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";
import api from "../../services/api";
import LnurlQrModal from "../../components/auth/LnurlQrModal.vue";
import NostrAuthModal from "../../components/auth/NostrAuthModal.vue";

const { t } = useI18n();

// LNURL / Nostr: fetch from server
const lnurlEnabledFromServer = ref<boolean | null>(null);
const nostrEnabledFromServer = ref<boolean | null>(null);
onMounted(async () => {
  try {
    const [lnurlRes, nostrRes] = await Promise.all([
      api.get<{ enabled: boolean }>(`/lnurl-auth/enabled?_=${Date.now()}`),
      api.get<{ enabled: boolean }>(`/nostr-auth/enabled?_=${Date.now()}`),
    ]);
    lnurlEnabledFromServer.value = lnurlRes.data?.enabled === true;
    nostrEnabledFromServer.value = nostrRes.data?.enabled === true;
  } catch {
    // Leave null so fallback is used
  }
});

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const flashStore = useFlashStore();

const form = ref({
  email: "",
  password: "",
  remember: false,
});

const loading = ref(false);

const showLnurlModal = ref(false);
const showNostrModal = ref(false);
const lnurlAuthUrl = ref("");
const lnurlLoading = ref(false);
const lnurlPolling = ref(false);
const lnurlError = ref("");
const lnurlK1 = ref("");
const showEmailStep = ref(false);
const emailForm = ref({
  email: "",
  user_id: null as number | null,
});
const emailLoading = ref(false);
const emailError = ref("");
let pollingInterval: number | null = null;

// LNURL auth: prefer server response; only then data attribute / Vite (so false on server hides the option)
const page = usePage();
const lnurlAuthEnabled = computed(() => {
  if (lnurlEnabledFromServer.value !== null) return lnurlEnabledFromServer.value;
  const el = document.getElementById("app");
  const dataVal = el?.getAttribute("data-lnurl-auth-enabled");
  if (dataVal !== null && dataVal !== undefined) return dataVal === "true";
  if (page.props?.app?.lnurlAuthEnabled !== undefined) return page.props.app.lnurlAuthEnabled === true;
  return import.meta.env.VITE_LNURL_AUTH_ENABLED === "true";
});
const nostrAuthEnabled = computed(() => nostrEnabledFromServer.value === true);

async function handleLogin() {
  loading.value = true;

  try {
    await authStore.login(
      form.value.email,
      form.value.password,
      form.value.remember,
    );
    // Redirect to dashboard after login
    const redirect = router.currentRoute.value.query.redirect as string;
    router.push(redirect || "/dashboard");
  } catch (err: any) {
    flashStore.error(
      err.response?.data?.message || "Login failed. Please try again.",
    );
  } finally {
    loading.value = false;
  }
}

async function fetchAuthChallengeAndOpen(): Promise<boolean> {
  try {
    const response = await api.post("/lnurl-auth/challenge");
    const raw = response.data ?? {};
    const challengeData = typeof raw === "object" && raw !== null && "data" in raw ? raw.data : raw;
    const k1 = challengeData?.k1 ?? challengeData?.K1;
    const lnurl = challengeData?.lnurl ?? challengeData?.lnurlAuthUrl;

    if (!k1 || !lnurl) {
      lnurlError.value = t("auth.error_occurred");
      return false;
    }

    lnurlK1.value = k1;
    lnurlAuthUrl.value = lnurl;
    showLnurlModal.value = true;
    startPolling(k1);
    return true;
  } catch (err: any) {
    lnurlError.value =
      err.response?.data?.error || "Failed to generate challenge";
    return false;
  }
}

async function handleLnurlAuth() {
  lnurlLoading.value = true;
  lnurlError.value = "";
  showEmailStep.value = false;
  emailError.value = "";

  try {
    await fetchAuthChallengeAndOpen();
  } finally {
    lnurlLoading.value = false;
  }
}

async function requestNewAuthChallenge() {
  lnurlError.value = "";
  stopPolling();
  await fetchAuthChallengeAndOpen();
}

function startPolling(k1: string) {
  lnurlPolling.value = true;
  const startTime = Date.now();
  const timeout = 300000; // 5 minutes

  const doPoll = async () => {
    if (Date.now() - startTime > timeout) {
      lnurlError.value = t("account.challenge_expired");
      stopPolling();
      return;
    }
    try {
      const statusResponse = await api.get(
        `/lnurl-auth/challenge-status/${k1}?_=${Date.now()}`,
      );
      const raw = statusResponse.data ?? {};
      const data = typeof raw === "object" && raw !== null && "data" in raw ? raw.data : raw;
      const payload = typeof data === "object" && data !== null ? data : {};
      const status = payload.status;
      const user_id = payload.user_id != null ? Number(payload.user_id) : null;

      if (!status && typeof payload === "object" && payload !== null && Object.keys(payload).length > 0) {
        console.warn("[LNURL] challenge-status response missing .status:", payload);
      }

      if (status === "authenticated") {
        stopPolling();
        closeLnurlModal();
        await authStore.fetchUser();
        router.push("/dashboard");
      } else if (status === "pending_email" && (user_id || payload.k1 || lnurlK1.value)) {
        stopPolling();
        emailForm.value.user_id = user_id ?? null;
        showEmailStep.value = true;
        await nextTick();
      } else if (status === "expired") {
        lnurlError.value = t("account.challenge_expired");
        stopPolling();
      } else if (status === "error") {
        lnurlError.value = (payload as { message?: string }).message || t("auth.error_occurred");
        stopPolling();
      }
    } catch (err: any) {
      // 403 = LNURL disabled, stop. Network errors (ERR_NETWORK_CHANGED, etc.) = keep polling
      if (err.response?.status === 403) {
        lnurlError.value = t("auth.error_occurred");
        stopPolling();
      } else {
        // Transient network error – clear error on next successful poll
        lnurlError.value = "";
      }
    }
  };

  doPoll();
  pollingInterval = window.setInterval(doPoll, 1000);
}

async function handleCompleteRegistration() {
  const hasUserId = emailForm.value.user_id != null;
  const hasK1 = !!lnurlK1.value;
  if (!hasUserId && !hasK1) {
    emailError.value = t("auth.invalid_session");
    return;
  }

  emailLoading.value = true;
  emailError.value = "";

  try {
    await api.post("/lnurl-auth/complete-registration", {
      ...(hasUserId ? { user_id: emailForm.value.user_id } : { k1: lnurlK1.value }),
      email: emailForm.value.email,
    });

    // Registration completed, show success message and close modal
    closeLnurlModal();
    flashStore.success(t("auth.registration_successful"));
  } catch (err: any) {
    if (err.response?.data?.errors?.email) {
      emailError.value = err.response.data.errors.email[0];
    } else {
      emailError.value =
        err.response?.data?.message ||
        "Failed to complete registration. Please try again.";
    }
  } finally {
    emailLoading.value = false;
  }
}

function stopPolling() {
  if (pollingInterval !== null) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  lnurlPolling.value = false;
}

function closeLnurlModal() {
  stopPolling();
  showLnurlModal.value = false;
  showEmailStep.value = false;
  lnurlError.value = "";
  lnurlAuthUrl.value = "";
  lnurlK1.value = "";
  emailForm.value = {
    email: "",
    user_id: null,
  };
  emailError.value = "";
}

onUnmounted(() => {
  stopPolling();
});
</script>

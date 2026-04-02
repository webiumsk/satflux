<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden"
  >
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gray-900">
      <div
        class="absolute top-0 -left-6 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"
      ></div>
      <div
        class="absolute bottom-0 -right-6 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000"
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
          {{
            isSetNewPassword
              ? t("auth.set_new_password")
              : t("auth.reset_password")
          }}
        </h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{
            isSetNewPassword
              ? t("auth.enter_new_password_hint")
              : t("auth.enter_email_reset")
          }}
        </p>
      </div>

      <div
        class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl"
      >
        <!-- Request reset link form -->
        <form
          v-if="!isSetNewPassword"
          class="space-y-6"
          @submit.prevent="handleSendResetLink"
        >
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
                placeholder="you@example.com"
              />
            </div>
          </div>

          <div>
            <button
              type="submit"
              :disabled="loading"
              class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
            >
              {{ loading ? t("auth.sending") : t("auth.send_reset_link") }}
            </button>
          </div>
        </form>

        <!-- Set new password form (from email link) -->
        <form v-else class="space-y-6" @submit.prevent="handleSetNewPassword">
          <div>
            <label
              for="email-display"
              class="block text-sm font-medium text-gray-300"
              >{{ t("auth.email") }}</label
            >
            <div class="mt-1">
              <input
                id="email-display"
                :value="resetEmail"
                type="email"
                readonly
                class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm text-gray-400 bg-gray-700/30 sm:text-sm cursor-not-allowed"
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
                autocomplete="new-password"
                required
                minlength="8"
                class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                :placeholder="t('auth.password_placeholder')"
              />
            </div>
          </div>
          <div>
            <label
              for="password_confirmation"
              class="block text-sm font-medium text-gray-300"
              >{{ t("auth.confirm_password") }}</label
            >
            <div class="mt-1">
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                minlength="8"
                class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                :placeholder="t('auth.password_placeholder')"
              />
            </div>
          </div>

          <div>
            <button
              type="submit"
              :disabled="loading"
              class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
            >
              {{
                loading ? t("auth.resetting") : t("auth.reset_password_button")
              }}
            </button>
          </div>
        </form>

        <div class="mt-6 text-center text-sm">
          <router-link
            to="/login"
            class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors"
          >
            <span aria-hidden="true"> &larr;</span>
            {{ t("auth.back_to_login") }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import axios from "axios";
import api from "../../services/api";
import { useFlashStore } from "../../store/flash";

const { t } = useI18n();
const route = useRoute();
const flashStore = useFlashStore();

const form = ref({
  email: "",
  password: "",
  password_confirmation: "",
});

const loading = ref(false);

const resetToken = computed(() => (route.query.token as string) || "");
const resetEmail = computed(() => (route.query.email as string) || "");

const isSetNewPassword = computed(() =>
  Boolean(resetToken.value && resetEmail.value),
);

// Ensure CSRF cookie is set before first POST
onMounted(() => {
  axios.get("/sanctum/csrf-cookie", { withCredentials: true }).catch(() => {});
});

// Pre-fill email when coming from link
watch(
  () => route.query.email,
  (email: string | string[] | undefined) => {
    if (email && typeof email === "string") form.value.email = email;
  },
  { immediate: true },
);

async function handleSendResetLink() {
  loading.value = true;

  try {
    await api.post("/auth/password/reset-link", {
      email: form.value.email,
    });
    flashStore.success(t("auth.password_reset_sent"));
  } catch (err: any) {
    flashStore.error(
      err.response?.data?.message || t("auth.failed_send_reset"),
    );
  } finally {
    loading.value = false;
  }
}

async function handleSetNewPassword() {
  if (form.value.password !== form.value.password_confirmation) {
    flashStore.error(t("validation.password_confirmation"));
    return;
  }
  if (form.value.password.length < 8) {
    flashStore.error(t("validation.password_min"));
    return;
  }
  loading.value = true;

  try {
    await api.post("/auth/password/reset", {
      token: resetToken.value,
      email: resetEmail.value,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    });
    flashStore.success(t("auth.password_reset_successful"));
    form.value.password = "";
    form.value.password_confirmation = "";
    setTimeout(() => {
      window.location.href = "/login";
    }, 2500);
  } catch (err: any) {
    flashStore.error(
      err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.message ||
        t("auth.reset_failed"),
    );
  } finally {
    loading.value = false;
  }
}
</script>

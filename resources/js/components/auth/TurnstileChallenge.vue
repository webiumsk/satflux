<template>
  <div class="min-w-0 space-y-3">
    <p v-if="loading" role="status" class="text-sm text-gray-300">{{ t("auth.turnstile_loading") }}</p>
    <div ref="container" class="flex justify-center" :aria-label="t('auth.turnstile_title')" />
    <div v-if="error" role="alert" class="space-y-3">
      <p class="text-sm text-amber-200">{{ t(error) }}</p>
      <button type="button" class="w-full rounded-lg border border-indigo-400/50 px-4 py-2 text-sm font-medium text-indigo-200 hover:bg-indigo-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400" @click="start">
        {{ t("auth.turnstile_retry") }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { loadTurnstile, type TurnstileApi } from "../../services/turnstile";

const props = defineProps<{ siteKey: string }>();
const emit = defineEmits<{ verified: [token: string] }>();
const { t, locale } = useI18n();
const container = ref<HTMLElement | null>(null);
const loading = ref(true);
const error = ref("");
let active = true;
let generation = 0;
let api: TurnstileApi | null = null;
let widgetId: string | null = null;
let challengeTimeout: number | undefined;

function removeWidget() {
  window.clearTimeout(challengeTimeout);
  if (api && widgetId !== null) {
    try { api.remove(widgetId); } catch { /* An already removed widget needs no cleanup. */ }
  }
  widgetId = null;
}

/** Each manual retry creates a fresh one-use challenge; old callbacks stay inert. */
async function start() {
  const current = ++generation;
  removeWidget();
  loading.value = true;
  error.value = "";
  let issued = false;
  const fail = (message = "auth.turnstile_error") => {
    if (!active || current !== generation || issued) return;
    window.clearTimeout(challengeTimeout);
    loading.value = false;
    error.value = message;
  };
  try {
    api = await loadTurnstile();
    if (!active || current !== generation || !container.value) return;
    widgetId = api.render(container.value, {
      sitekey: props.siteKey,
      action: "register",
      theme: "dark",
      size: "compact",
      language: locale.value || "auto",
      retry: "never",
      "refresh-expired": "manual",
      "refresh-timeout": "manual",
      callback: (token) => {
        if (!active || current !== generation || issued || error.value) return;
        if (typeof token !== "string" || !token || token.length > 2048) {
          fail();
          return;
        }
        issued = true;
        window.clearTimeout(challengeTimeout);
        emit("verified", token);
      },
      "expired-callback": () => fail("auth.turnstile_expired"),
      "error-callback": () => { fail(); return true; },
      "timeout-callback": () => fail("auth.turnstile_expired"),
    });
    loading.value = false;
    if (!issued) challengeTimeout = window.setTimeout(() => fail("auth.turnstile_expired"), 300_000);
  } catch {
    fail();
  }
}

onMounted(() => { void start(); });
onBeforeUnmount(() => {
  active = false;
  generation += 1;
  removeWidget();
});
</script>

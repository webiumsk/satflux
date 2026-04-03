<template>
  <div
    class="sticky top-0 z-20 w-full border-b border-gray-800 bg-gray-900/90 backdrop-blur-md"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 lg:py-4">
      <div
        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
      >
        <div class="flex min-w-0 items-center">
          <button
            @click="goBack"
            class="mr-3 text-gray-400 transition-colors hover:text-white"
          >
            <svg
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
          </button>
          <div>
            <h1 class="mb-0.5 text-xl font-bold text-white lg:text-2xl">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-gray-400">{{ subtitle }}</p>
          </div>
        </div>

        <div class="flex w-full items-center justify-end gap-2.5 md:w-auto md:gap-3" data-onboarding="pos-4">
          <!-- Actions slot for custom buttons (e.g., Delete App) -->
          <slot name="actions"></slot>

          <a
            v-if="appUrl"
            :href="appUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="btcpay-open-link inline-flex items-center rounded-xl border border-orange-400/40 bg-orange-500/20 px-3.5 py-1.5 text-sm font-medium text-orange-100 shadow-[inset_0_0_12px_rgba(249,115,22,0.15)] transition-all hover:border-orange-400/60 hover:bg-orange-500/30 hover:text-orange-50 hover:shadow-[inset_0_0_16px_rgba(249,115,22,0.25)]"
          >
            <svg
              class="w-4 h-4 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
              />
            </svg>
            {{ openButtonText }}
          </a>
          <button
            v-if="appUrl"
            type="button"
            :title="t('stores.show_app_qr')"
            class="inline-flex items-center justify-center rounded-xl border border-transparent p-1.5 text-gray-400 transition-colors hover:border-gray-600 hover:bg-gray-700 hover:text-white"
            @click="showQrModal = true"
          >
            <svg
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
              />
            </svg>
          </button>
          <button
            type="submit"
            :form="formId"
            :disabled="saving"
            class="inline-flex items-center rounded-xl border border-transparent bg-indigo-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-lg shadow-indigo-600/20 transition-all hover:scale-105 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg
              v-if="saving"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            {{ saving ? savingText : saveButtonText }}
          </button>
        </div>
      </div>
    </div>

    <UrlQrModal
      :open="showQrModal"
      :url="appUrl || ''"
      :title="qrModalTitle || t('stores.app_url_qr')"
      @close="showQrModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, inject } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import UrlQrModal from "../ui/UrlQrModal.vue";

const props = defineProps<{
  title: string;
  subtitle?: string;
  appUrl?: string;
  openButtonText: string;
  formId: string;
  saveButtonText: string;
  savingText?: string;
  saving: boolean;
  qrModalTitle?: string;
}>();

const { t } = useI18n();
const isInertia = inject<boolean>("inertia", false);
const vueRouter = !isInertia ? useRouter() : null;
const showQrModal = ref(false);

function goBack() {
  if (vueRouter) {
    vueRouter.back();
  } else {
    window.history.back();
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    >
      <div
        class="absolute inset-0 bg-black/70 backdrop-blur-sm"
        aria-hidden="true"
      />

      <div
        class="relative w-full max-w-lg bg-gray-800 border border-amber-500/40 rounded-2xl shadow-2xl overflow-hidden"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @click.stop
      >
        <div class="px-6 py-5 border-b border-gray-700 bg-amber-500/10">
          <div class="flex items-start gap-3">
            <div
              class="h-10 w-10 rounded-xl bg-amber-500/20 flex items-center justify-center flex-shrink-0"
            >
              <svg
                class="w-5 h-5 text-amber-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>
            <div>
              <h2 :id="titleId" class="text-lg font-bold text-white">
                {{ t("stores.blink_migration_modal_title") }}
              </h2>
              <p v-if="storeName" class="text-xs text-gray-400 mt-0.5">
                {{ storeName }}
              </p>
            </div>
          </div>
        </div>

        <div class="px-6 py-5 space-y-4">
          <p class="text-sm text-gray-300 leading-relaxed">
            {{ t("stores.blink_migration_modal_body") }}
          </p>

          <router-link
            :to="walletConnectionPath"
            class="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
            @click="emit('close')"
          >
            {{ t("stores.blink_migration_go_wallet") }}
          </router-link>
        </div>

        <div
          class="px-6 py-4 border-t border-gray-700 flex flex-col sm:flex-row gap-3 sm:justify-end bg-gray-900/40"
        >
          <button
            type="button"
            class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-300 border border-gray-600 hover:bg-gray-700 transition-colors disabled:opacity-50"
            :disabled="loading"
            @click="emit('outside-eu')"
          >
            {{ t("stores.blink_migration_outside_eu") }}
          </button>
          <button
            type="button"
            class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 transition-colors disabled:opacity-50"
            :disabled="loading"
            @click="emit('acknowledge')"
          >
            {{ t("stores.blink_migration_acknowledge") }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{
  open: boolean;
  storeId: string;
  storeName?: string | null;
  loading?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  acknowledge: [];
  "outside-eu": [];
}>();

const { t } = useI18n();

const titleId = "blink-migration-alert-title";
const walletConnectionPath = computed(
  () => `/stores/${props.storeId}/wallet-connection`,
);
</script>

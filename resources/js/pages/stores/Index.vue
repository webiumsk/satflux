<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex justify-between items-center mb-10">
      <div>
        <h1 class="text-3xl font-bold text-white mb-2">
          {{ t("stores.title") }}
        </h1>
        <p class="text-gray-400">
          {{ t("stores.manage_stores") }}
          <span v-if="limits?.stores?.max != null" class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs">
            {{ limits.stores.current }} / {{ limits.stores.max }} used
          </span>
          <span v-else-if="limits?.stores?.unlimited" class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs">
            Unlimited
          </span>
        </p>
      </div>
      <router-link
        to="/stores/create"
        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
      >
        <svg
          class="w-5 h-5 mr-2"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        {{ t("stores.create_store") }}
      </router-link>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-24">
      <svg
        class="animate-spin h-10 w-10 text-indigo-500"
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
    </div>

    <div v-else>
      <div
        v-if="stores.length === 0"
        class="text-center py-20 bg-gray-800/50 backdrop-blur-sm rounded-3xl border border-gray-700/50 border-dashed"
      >
        <div
          class="w-20 h-20 bg-gray-700/50 rounded-full flex items-center justify-center mx-auto mb-6"
        >
          <svg
            class="w-10 h-10 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
            />
          </svg>
        </div>
        <h3 class="text-xl font-medium text-white mb-2">
          {{ t("stores.no_stores_yet") }}
        </h3>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
          {{ t("stores.create_first_store_description") }}
        </p>
        <router-link
          to="/stores/create"
          class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
        >
          {{ t("stores.create_first_store") }}
        </router-link>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="store in stores"
          :key="store.id"
          class="group bg-gray-800 hover:bg-gray-750 border border-gray-700 hover:border-indigo-500/50 rounded-2xl p-6 cursor-pointer transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-900/10 hover:-translate-y-1 relative overflow-hidden"
          @click="$router.push(`/stores/${store.id}`)"
        >
          <!-- Hover Gradient Glow -->
          <div
            class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 opacity-0 group-hover:opacity-100 transition-opacity"
          ></div>

          <div class="flex items-start justify-between relative z-10">
            <div class="flex-1">
              <h3
                class="text-xl font-bold text-white mb-2 group-hover:text-indigo-300 transition-colors"
              >
                {{ store.name }}
              </h3>

              <div class="space-y-3 mt-4">
                <div class="flex items-center text-sm">
                  <span class="text-gray-500 w-24">{{
                    t("stores.wallet")
                  }}</span>
                  <span class="text-gray-300 font-medium">{{
                    store.wallet_type === "blink"
                      ? "Blink"
                      : store.wallet_type === "aqua_boltz"
                        ? "Aqua (Boltz)"
                        : t("stores.not_set")
                  }}</span>
                </div>

                <div class="flex items-center text-sm">
                  <span class="text-gray-500 w-24">{{
                    t("stores.status")
                  }}</span>
                  <div class="flex flex-wrap gap-1.5">
                    <span
                      class="px-2 py-0.5 rounded-md text-xs font-semibold"
                      :class="getWalletConnectionStatusBadgeClass(store)"
                    >
                      {{ getWalletConnectionStatusText(store) }}
                    </span>
                    <span
                      v-if="store.archived"
                      class="px-2 py-0.5 rounded-md text-xs font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20"
                    >
                      {{ t("stores.archived") }}
                    </span>
                  </div>
                </div>

                <div
                  class="flex items-center text-xs text-gray-500 pt-2 border-t border-gray-700/50 mt-4"
                >
                  <svg
                    class="w-3.5 h-3.5 mr-1.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  {{ t("common.created") }} {{ formatDate(store.created_at) }}
                </div>
              </div>
            </div>

            <div
              v-if="store.logo_url"
              class="ml-4 flex-shrink-0 bg-white/5 rounded-lg p-2 backdrop-blur-sm border border-white/10"
            >
              <img
                :src="store.logo_url"
                :alt="`${store.name} logo`"
                class="h-10 w-10 object-contain"
              />
            </div>
            <div
              v-else
              class="ml-4 flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center border border-gray-600 text-gray-400 font-bold text-lg"
            >
              {{ store.name.charAt(0).toUpperCase() }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { storeToRefs } from "pinia";
import { useStoresStore } from "../../store/stores";
import { useAccountLimits } from "../../composables/useAccountLimits";

const { t } = useI18n();

const storesStore = useStoresStore();
const { stores, loading } = storeToRefs(storesStore);
const { limits, load: loadLimits } = useAccountLimits();

function getWalletConnectionStatusBadgeClass(store: any): string {
  if (!store.wallet_connection) {
    return "bg-red-500/10 text-red-400 border border-red-500/20";
  }

  const status = store.wallet_connection.status;
  switch (status) {
    case "connected":
      return "bg-green-500/10 text-green-400 border border-green-500/20";
    case "needs_support":
      return "bg-blue-500/10 text-blue-400 border border-blue-500/20";
    case "pending":
    default:
      return "bg-yellow-500/10 text-yellow-400 border border-yellow-500/20";
  }
}

function getWalletConnectionStatusText(store: any): string {
  if (!store.wallet_connection) {
    return t("stores.not_config");
  }

  const status = store.wallet_connection.status;
  switch (status) {
    case "connected":
      return t("stores.connected");
    case "needs_support":
      return t("stores.support");
    case "pending":
      return t("stores.pending");
    default:
      return t("stores.unknown");
  }
}

function formatDate(dateString: string): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return "-";

  // European format: DD.MM.YYYY
  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
}

onMounted(async () => {
  await Promise.all([storesStore.fetchStores(), loadLimits()]);
});
</script>

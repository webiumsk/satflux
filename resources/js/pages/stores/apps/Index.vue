<template>
  <AppLayout>
    <div class="flex min-h-0 flex-1 overflow-hidden bg-gray-900">
      <!-- Sidebar -->
      <StoreSidebar
        :store="store"
        :apps="apps"
        @show-settings="handleShowSettings"
        @show-section="handleShowSection"
      />

      <!-- Main Content -->
      <div
        class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-l border-gray-800 bg-gray-900"
      >
        <!-- Header -->
        <div
          class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800"
        >
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 lg:py-6">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-white mb-1">
                  {{
                    showArchived
                      ? t("apps.archived_apps_title")
                      : t("apps.title")
                  }}
                </h1>
                <p class="text-sm text-gray-400">
                  {{
                    showArchived
                      ? t("apps.archived_apps_description")
                      : t("apps.manage_apps")
                  }}
                  <span class="text-indigo-400">{{
                    store?.name || "this store"
                  }}</span>
                </p>
              </div>
              <Link
                v-if="!showArchived"
                :href="`/stores/${store.id}/apps/create`"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
              >
                <svg
                  class="-ml-1 mr-2 h-5 w-5"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                    clip-rule="evenodd"
                  />
                </svg>
                {{ t("apps.create_app") }}
              </Link>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div
          class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar"
        >
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <ArchivedStoreBanner :store="store" />

            <!-- Archived Apps Table -->
            <div
              v-if="showArchived && displayedApps.length > 0"
              class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden"
            >
              <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                  <tr>
                    <th
                      class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider"
                    >
                      {{ t("apps.name") }}
                    </th>
                    <th
                      class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider"
                    >
                      {{ t("apps.app_type") }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <tr
                    v-for="app in displayedApps"
                    :key="app.id"
                    class="hover:bg-gray-700/50 cursor-pointer transition-colors"
                  >
                    <td class="px-6 py-4">
                      <Link
                        :href="`/stores/${store.id}/apps/${app.id}`"
                        class="flex items-center gap-2"
                      >
                        <span class="text-white font-medium">{{
                          app.name
                        }}</span>
                        <span
                          class="px-2 py-0.5 text-xs font-medium rounded bg-sky-500/20 text-sky-400 border border-sky-500/30"
                          >{{ t("apps.archived") }}</span
                        >
                      </Link>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-400">
                      {{ app.app_type }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div
              v-else-if="displayedApps && displayedApps.length > 0"
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            >
              <Link
                v-for="app in displayedApps"
                :key="app.id"
                :href="`/stores/${store.id}/apps/${app.id}`"
                class="bg-gray-800 rounded-xl border border-gray-700 p-6 hover:border-indigo-500/50 hover:bg-gray-800/80 transition-all group relative overflow-hidden"
              >
                <div
                  class="absolute top-0 right-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <svg
                    class="w-5 h-5 text-gray-400 group-hover:text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5l7 7-7 7"
                    />
                  </svg>
                </div>

                <div class="flex items-center mb-4">
                  <div
                    class="p-3 rounded-lg bg-gray-700/50 text-gray-300 group-hover:bg-indigo-500/10 group-hover:text-indigo-400 transition-colors"
                  >
                    <svg
                      v-if="app.app_type === 'PointOfSale'"
                      class="w-6 h-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                      />
                    </svg>
                    <svg
                      v-else-if="app.app_type === 'PaymentButton'"
                      class="w-6 h-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                      />
                    </svg>
                    <svg
                      v-else
                      class="w-6 h-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                      />
                    </svg>
                  </div>
                  <div class="ml-4">
                    <h3
                      class="text-lg font-medium text-white group-hover:text-indigo-400 transition-colors"
                    >
                      {{ app.name }}
                    </h3>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">
                      {{ app.app_type }}
                    </p>
                  </div>
                </div>

                <div class="text-sm text-gray-400 line-clamp-2">
                  {{ app.config?.description || "No description provided." }}
                </div>
              </Link>
            </div>

            <div v-else class="text-center py-16">
              <div
                class="bg-gray-800/50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6"
              >
                <svg
                  class="w-10 h-10 text-gray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                  />
                </svg>
              </div>
              <template v-if="showArchived">
                <h3 class="text-xl font-medium text-white mb-2">
                  {{ t("apps.no_archived_apps") }}
                </h3>
                <p class="text-gray-400 mb-8 max-w-sm mx-auto">
                  {{ t("apps.no_archived_apps_description") }}
                </p>
                <Link
                  :href="`/stores/${store.id}/apps`"
                  class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                  {{ t("apps.back_to_apps") }}
                </Link>
              </template>
              <template v-else>
                <h3 class="text-xl font-medium text-white mb-2">
                  {{ t("apps.no_apps_yet") }}
                </h3>
                <p class="text-gray-400 mb-8 max-w-sm mx-auto">
                  {{ t("apps.no_apps_description") }}
                </p>
                <Link
                  :href="`/stores/${store.id}/apps/create`"
                  class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                  {{ t("apps.create_first_app") }}
                </Link>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import AppLayout from "../../../components/layout/AppLayout.vue";
import StoreSidebar from "../../../components/stores/StoreSidebar.vue";
import ArchivedStoreBanner from "../../../components/stores/ArchivedStoreBanner.vue";

const { t } = useI18n();
const page = usePage();

const props = defineProps<{
  store: any;
  apps: any[];
}>();

const showArchived = computed(() => {
  try {
    const url = new URL(page.url, window.location.origin);
    return url.searchParams.get("archived") === "1";
  } catch {
    return false;
  }
});

const displayedApps = computed(() =>
  showArchived.value
    ? props.apps.filter((a: any) => a.archived)
    : props.apps.filter((a: any) => !a.archived),
);

function handleShowSettings() {
  router.visit(`/stores/${props.store.id}?section=settings`);
}

function handleShowSection(section: string) {
  router.visit(`/stores/${props.store.id}?section=${section}`);
}
</script>

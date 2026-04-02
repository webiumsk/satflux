<template>
  <!-- Loading store: avoid blank page when navigating to this route -->
  <div
    v-if="!store && !error"
    class="flex min-h-0 flex-1 items-center justify-center bg-gray-900"
  >
    <div class="flex flex-col items-center">
      <svg
        class="animate-spin h-10 w-10 text-indigo-500 mb-4"
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
      <p class="text-gray-400">{{ t("common.loading") }}</p>
    </div>
  </div>
  <div
    v-else-if="error"
    class="flex min-h-0 flex-1 items-center justify-center bg-gray-900"
  >
    <div class="text-center px-4">
      <p class="text-red-400 mb-4">{{ error }}</p>
      <button @click="loadStore" class="text-indigo-400 hover:text-indigo-300">
        {{ t("common.retry") }}
      </button>
    </div>
  </div>
  <div v-else class="flex min-h-0 flex-1 overflow-hidden bg-gray-900">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
      @open-setup-wizard="handleOpenSetupWizard"
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
          <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4"
          >
            <div>
              <h1 class="text-2xl font-bold text-white mb-1">
                {{ t("stores.lightning_address_page_title") }}
              </h1>
              <p class="text-sm text-gray-400">
                {{ t("stores.manage_lightning_addresses_for") }}
                <span class="text-indigo-400">{{
                  store?.name || t("stores.this_store")
                }}</span>
                <span
                  v-if="limit && limit.max != null"
                  class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs"
                >
                  {{ limit.current }} / {{ limit.max }} {{ t("stores.used") }}
                </span>
                <span
                  v-else-if="limit && limit.unlimited"
                  class="text-gray-500 ml-1 bg-gray-800 px-2 py-0.5 rounded-full text-xs"
                >
                  {{ t("stores.unlimited") }}
                </span>
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button
                @click="openAddForm"
                :disabled="addCheckLoading"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105 disabled:opacity-70 disabled:cursor-wait"
              >
                <svg
                  v-if="addCheckLoading"
                  class="animate-spin h-4 w-4 mr-2"
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
                <svg
                  v-else
                  class="h-4 w-4 mr-2"
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
                {{
                  addCheckLoading
                    ? t("common.loading")
                    : t("stores.add_address")
                }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div
        class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar"
      >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <!-- Addresses List -->
          <div
            v-if="!loading && addresses.length > 0"
            class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
          >
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800/50">
                  <tr>
                    <th
                      scope="col"
                      class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                    >
                      {{ t("stores.address") }}
                    </th>
                    <th
                      scope="col"
                      class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                    >
                      {{ t("stores.settings_tab_settings") }}
                    </th>
                    <th
                      scope="col"
                      class="px-6 py-4 text-right text-xs font-medium text-gray-400 uppercase tracking-wider"
                    >
                      {{ t("common.actions") }}
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                  <tr
                    v-for="address in addresses"
                    :key="address.username"
                    class="hover:bg-gray-700/30 transition-colors"
                  >
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="relative flex-grow max-w-sm">
                          <input
                            :value="formatLnAddress(address.username)"
                            readonly
                            class="block w-full border-gray-600 rounded-lg bg-gray-900/50 text-white font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 py-2 px-3 pr-10"
                          />
                          <button
                            @click="
                              copyToClipboard(formatLnAddress(address.username))
                            "
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-white"
                            title="Copy to clipboard"
                          >
                            <svg
                              class="h-4 w-4"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                              />
                            </svg>
                          </button>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm text-gray-300">
                        <span
                          v-if="
                            address.min || address.max || address.currencyCode
                          "
                        >
                          <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300 border border-gray-600 mr-2"
                          >
                            {{ address.min || "0" }} -
                            {{ address.max || "∞" }} sats
                          </span>
                          <span
                            v-if="address.currencyCode"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-900/30 text-indigo-300 border border-indigo-500/30"
                          >
                            {{ address.currencyCode }}
                          </span>
                        </span>
                        <span v-else class="text-gray-500 italic">{{
                          t("stores.default_limits")
                        }}</span>
                      </div>
                    </td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
                    >
                      <button
                        type="button"
                        @click="showQrForAddress(address)"
                        class="text-gray-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-gray-700 mr-1"
                        :title="t('stores.show_address_qr')"
                      >
                        <svg
                          class="h-5 w-5"
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
                        @click="confirmDelete(address)"
                        class="text-red-400 hover:text-red-300 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                        :title="t('stores.delete_address')"
                      >
                        <svg
                          class="h-5 w-5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                          />
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Loading State -->
          <div
            v-if="loading"
            class="flex flex-col items-center justify-center py-24"
          >
            <svg
              class="animate-spin h-10 w-10 text-indigo-500 mb-4"
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
            <p class="text-gray-400">
              {{ t("stores.loading_lightning_addresses") }}
            </p>
          </div>

          <!-- Empty State -->
          <div
            v-if="!loading && addresses.length === 0"
            class="bg-gray-800/50 border border-gray-700 rounded-2xl p-12 text-center"
          >
            <div
              class="mx-auto h-16 w-16 text-gray-600 bg-gray-800 rounded-full flex items-center justify-center mb-4"
            >
              <svg
                class="h-8 w-8 text-yellow-500"
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
            </div>
            <h3 class="text-lg font-medium text-white mb-2">
              {{ t("stores.no_lightning_addresses_yet") }}
            </h3>
            <p class="text-gray-400 mb-6 max-w-sm mx-auto">
              {{
                t("stores.create_lightning_address_description", {
                  domain: displayLightningDomain || "…",
                })
              }}
            </p>
            <button
              @click="openAddForm"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
            >
              {{ t("stores.add_your_first_address") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Form Modal -->
    <div
      v-if="showForm"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <div
          class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity"
          aria-hidden="true"
          @click="closeForm"
        ></div>
        <span
          class="hidden sm:inline-block sm:align-middle sm:h-screen"
          aria-hidden="true"
          >&#8203;</span
        >

        <div
          class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-gray-700"
        >
          <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
              <h3
                class="text-xl font-bold text-white leading-6"
                id="modal-title"
              >
                {{ t("stores.add_lightning_address") }}
              </h3>
              <button
                @click="closeForm"
                class="text-gray-400 hover:text-white transition-colors"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div>
                <label
                  for="username"
                  class="block text-sm font-medium text-gray-300 mb-1"
                >
                  Username <span class="text-red-400">*</span>
                </label>
                <div class="mt-1 flex rounded-xl shadow-sm">
                  <input
                    id="username"
                    v-model="form.username"
                    type="text"
                    required
                    class="flex-1 min-w-0 block w-full px-4 py-3 rounded-none rounded-l-xl border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10"
                    placeholder="username"
                  />
                  <span
                    class="inline-flex items-center px-4 rounded-r-xl border border-l-0 border-gray-600 bg-gray-700 text-gray-400 text-sm font-medium"
                  >
                    {{
                      displayLightningDomain ? `@${displayLightningDomain}` : ""
                    }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-400">
                  Full address:
                  <span class="text-white font-mono">{{
                    formatLnAddress(form.username || "username")
                  }}</span>
                </p>
              </div>

              <!-- Advanced Settings Accordion -->
              <div class="border-t border-gray-700 pt-4">
                <button
                  type="button"
                  @click="showAdvancedSettings = !showAdvancedSettings"
                  class="w-full flex items-center justify-between text-left group"
                >
                  <h3
                    class="text-sm font-medium text-gray-300 group-hover:text-white transition-colors"
                  >
                    Advanced Settings
                  </h3>
                  <svg
                    :class="[
                      'w-5 h-5 text-gray-500 group-hover:text-gray-300 transition-transform transform',
                      showAdvancedSettings ? 'rotate-180' : '',
                    ]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </button>

                <div v-show="showAdvancedSettings" class="mt-4 space-y-5">
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-3">
                      <label
                        for="currencyCode"
                        class="block text-sm font-medium text-gray-300 mb-1"
                      >
                        Currency Code
                      </label>
                      <input
                        id="currencyCode"
                        v-model="form.currencyCode"
                        type="text"
                        list="currency-selection-suggestion"
                        class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g., USD, EUR"
                      />
                      <datalist id="currency-selection-suggestion">
                        <option
                          v-for="currency in currencies"
                          :key="currency.code"
                          :value="currency.code"
                        >
                          {{ currency.code }} - {{ currency.name }}
                        </option>
                      </datalist>
                      <p class="mt-1 text-xs text-gray-500">
                        Leave empty to use store default ({{
                          store?.default_currency || "EUR"
                        }})
                      </p>
                    </div>

                    <div>
                      <label
                        for="min"
                        class="block text-sm font-medium text-gray-300 mb-1"
                      >
                        Min (sats)
                      </label>
                      <input
                        id="min"
                        v-model="form.min"
                        type="text"
                        class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Min"
                      />
                    </div>

                    <div>
                      <label
                        for="max"
                        class="block text-sm font-medium text-gray-300 mb-1"
                      >
                        Max (sats)
                      </label>
                      <input
                        id="max"
                        v-model="form.max"
                        type="text"
                        class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Max"
                      />
                    </div>
                  </div>

                  <div>
                    <label
                      for="invoiceMetadata"
                      class="flex items-center text-sm font-medium text-gray-300 mb-1"
                    >
                      Invoice Metadata
                      <InfoTooltip
                        class="ml-1 text-gray-400 transition-colors hover:text-white"
                      >
                        <div class="text-xs max-w-xs text-white">
                          JSON Metadata to attach to invoices created via this
                          address.
                        </div>
                      </InfoTooltip>
                    </label>
                    <textarea
                      id="invoiceMetadata"
                      v-model="form.invoiceMetadata"
                      rows="3"
                      class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                      placeholder='{"orderId": "123"}'
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                      Enter a valid JSON object.
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  @click="closeForm"
                  class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
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
                  {{ saving ? t("auth.saving") : t("stores.add_address") }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <div
          class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity"
          aria-hidden="true"
          @click="closeDeleteModal"
        ></div>
        <span
          class="hidden sm:inline-block sm:align-middle sm:h-screen"
          aria-hidden="true"
          >&#8203;</span
        >

        <div
          class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-700"
        >
          <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10 border border-red-500/20"
              >
                <svg
                  class="h-6 w-6 text-red-500"
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
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3
                  class="text-lg leading-6 font-bold text-white mb-2"
                  id="modal-title"
                >
                  {{ t("stores.remove_lightning_address") }}
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-400">
                    {{ t("stores.remove_lightning_address_confirm_before")
                    }}<strong class="text-white font-mono">{{
                      addressToDelete
                        ? formatLnAddress(addressToDelete.username)
                        : ""
                    }}</strong
                    >{{ t("stores.remove_lightning_address_confirm_after") }}
                  </p>
                </div>
                <div
                  v-if="deleteError"
                  class="mt-4 rounded-xl bg-red-500/10 border border-red-500/20 p-3"
                >
                  <div class="text-sm text-red-400">{{ deleteError }}</div>
                </div>
              </div>
            </div>
          </div>
          <div
            class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50"
          >
            <button
              @click="handleDelete"
              :disabled="deleting"
              class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ deleting ? "Removing..." : "Remove" }}
            </button>
            <button
              @click="closeDeleteModal"
              class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QR code modal for Lightning Address -->
    <UrlQrModal
      :open="qrModalAddress !== null"
      :url="qrModalAddress || ''"
      :title="qrModalAddress || ''"
      :show-download="true"
      :download-filename="qrModalDownloadFilename"
      @close="qrModalAddress = null"
    />

    <!-- Upgrade Modal -->
    <UpgradeModal
      :show="showUpgradeModal"
      :message="`You have reached the maximum number of Lightning Addresses (${limit?.max || 1}) for your current plan.`"
      :limits="
        limit
          ? [
              {
                feature: 'ln_addresses',
                current: limit.current,
                max: limit.max,
              },
            ]
          : []
      "
      recommended-plan="pro"
      upgrade-button-text="Upgrade to Pro"
      @close="showUpgradeModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useStoresStore } from "../../store/stores";
import { useAppsStore } from "../../store/apps";
import { useFlashStore } from "../../store/flash";
import StoreSidebar from "../../components/stores/StoreSidebar.vue";
import InfoTooltip from "../../components/ui/InfoTooltip.vue";
import UpgradeModal from "../../components/stores/UpgradeModal.vue";
import UrlQrModal from "../../components/ui/UrlQrModal.vue";
import { currencies } from "../../data/currencies";
import api from "../../services/api";
import { useBtcPayUrl } from "../../composables/useBtcPayUrl";

const { t } = useI18n();
const { displayLightningDomain, load: loadBtcpayConfig } = useBtcPayUrl();

function formatLnAddress(username: string) {
  const d = displayLightningDomain.value;
  return d ? `${username}@${d}` : username;
}
const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();
const flashStore = useFlashStore();

const storeId = route.params.id as string;
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const addresses = ref<any[]>([]);
const store = ref<any>(null);
const limit = ref<{
  max: number | null;
  current: number;
  unlimited: boolean;
} | null>(null);
const deleteError = ref("");
const showForm = ref(false);
const showDeleteModal = ref(false);
const showUpgradeModal = ref(false);
const addCheckLoading = ref(false);
const addressToDelete = ref<any>(null);
const qrModalAddress = ref<string | null>(null);
const showAdvancedSettings = ref(false);

const qrModalDownloadFilename = computed(() => {
  if (!qrModalAddress.value) return "lightning-address.png";
  const username = qrModalAddress.value
    .replace(/@.*$/, "")
    .replace(/[^a-zA-Z0-9_-]/g, "_");
  return `lightning-address-${username || "qrcode"}.png`;
});

function showQrForAddress(address: any) {
  qrModalAddress.value = formatLnAddress(address.username);
}

const allApps = computed(() => appsStore.apps);

const canAddAddress = computed(() => {
  if (!limit.value) return false; // Unknown until loaded – openAddForm will load first
  if (limit.value.unlimited) return true;
  return limit.value.current < (limit.value.max ?? 0);
});

const form = ref({
  username: "",
  currencyCode: "",
  min: "",
  max: "",
  invoiceMetadata: "",
});

onMounted(async () => {
  await Promise.all([
    loadBtcpayConfig(),
    loadStore(),
    loadAddresses(),
    appsStore.fetchApps(storeId),
  ]);
});

// Watch for route changes to reload apps if store ID changes
watch(
  () => route.params.id,
  async (newStoreId) => {
    if (newStoreId && newStoreId !== storeId) {
      await appsStore.fetchApps(newStoreId as string);
    }
  },
  { immediate: false },
);

async function loadStore() {
  try {
    const response = await api.get(`/stores/${storeId}`);
    store.value = response.data.data;
  } catch (err: any) {
    flashStore.error("Failed to load store");
  }
}

async function loadAddresses() {
  loading.value = true;
  try {
    const response = await api.get(`/stores/${storeId}/lightning-addresses`);
    addresses.value = response.data.data || [];
    if (response.data.limit) {
      limit.value = response.data.limit;
    }
  } catch (err: any) {
    flashStore.error(
      err.response?.data?.message || "Failed to load lightning addresses",
    );
  } finally {
    loading.value = false;
  }
}

async function openAddForm() {
  // Ensure limit is loaded before deciding (so we show modal immediately when at limit)
  if (!limit.value) {
    addCheckLoading.value = true;
    await loadAddresses();
    addCheckLoading.value = false;
  }
  if (!canAddAddress.value) {
    showUpgradeModal.value = true;
    return;
  }

  form.value = {
    username: "",
    currencyCode: "",
    min: "",
    max: "",
    invoiceMetadata: "",
  };
  showAdvancedSettings.value = false;
  showForm.value = true;
  flashStore.clear();
}

function closeForm() {
  showForm.value = false;
}

async function handleSubmit() {
  saving.value = true;
  flashStore.clear();
  try {
    const data: any = {
      username: form.value.username,
    };

    // Always send currencyCode, min, max - send as null if empty
    data.currencyCode =
      form.value.currencyCode && form.value.currencyCode.trim()
        ? form.value.currencyCode.trim()
        : null;
    data.min =
      form.value.min && form.value.min.trim() ? form.value.min.trim() : null;
    data.max =
      form.value.max && form.value.max.trim() ? form.value.max.trim() : null;

    // Parse invoiceMetadata JSON string - only include if provided and not empty
    if (form.value.invoiceMetadata && form.value.invoiceMetadata.trim()) {
      try {
        const parsed = JSON.parse(form.value.invoiceMetadata.trim());
        if (
          typeof parsed === "object" &&
          parsed !== null &&
          !Array.isArray(parsed)
        ) {
          // Only include if the object has properties
          if (Object.keys(parsed).length > 0) {
            data.invoiceMetadata = parsed;
          }
          // If empty object, don't include it in the request
        } else {
          flashStore.error(
            "Invoice Metadata must be a valid JSON object (not an array or primitive value)",
          );
          saving.value = false;
          return;
        }
      } catch (e) {
        flashStore.error(
          "Invalid JSON format in Invoice Metadata. Please check your syntax.",
        );
        saving.value = false;
        return;
      }
    }
    // If no metadata provided, don't include it in the request

    await api.post(
      `/stores/${storeId}/lightning-addresses/${form.value.username}`,
      data,
    );

    await loadAddresses();
    closeForm();
    flashStore.success(t("stores.lightning_address_added"));
  } catch (err: any) {
    console.error("Failed to save address:", err);
    const errorMessage =
      err.response?.data?.message || t("stores.lightning_address_save_failed");

    // Check if error is about limit reached
    if (
      err.response?.status === 403 &&
      errorMessage.includes("maximum number")
    ) {
      closeForm();
      showUpgradeModal.value = true;
    } else {
      flashStore.error(errorMessage);
    }
  } finally {
    saving.value = false;
  }
}

function confirmDelete(address: any) {
  addressToDelete.value = address;
  showDeleteModal.value = true;
  deleteError.value = "";
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  addressToDelete.value = null;
  deleteError.value = "";
}

async function handleDelete() {
  if (!addressToDelete.value) return;

  deleting.value = true;
  deleteError.value = "";

  try {
    await api.delete(
      `/stores/${storeId}/lightning-addresses/${addressToDelete.value.username}`,
    );
    await loadAddresses();
    closeDeleteModal();
    flashStore.success(t("stores.lightning_address_removed"));
  } catch (err: any) {
    console.error("Failed to delete address:", err);
    const msg =
      err.response?.data?.message ||
      t("stores.lightning_address_delete_failed");
    flashStore.error(msg);
    deleteError.value = "";
  } finally {
    deleting.value = false;
  }
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    flashStore.success(t("common.copied"));
  } catch (err) {
    console.error("Failed to copy:", err);
    flashStore.error(t("common.copy_failed"));
  }
}

function handleShowSettings() {
  router.push({
    name: "stores-show",
    params: { id: storeId },
    query: { section: "settings" },
  });
}

function handleOpenSetupWizard() {
  router.push({
    name: "stores-show",
    params: { id: storeId },
    query: { setup: "1" },
  });
}

function handleShowSection(section: string) {
  router.push({
    name: "stores-show",
    params: { id: storeId },
    query: { section },
  });
}
</script>

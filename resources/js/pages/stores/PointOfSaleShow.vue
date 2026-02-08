<template>
  <AppShowLayout ref="layoutRef" :store="store" :app="app">
    <template #default="{ app, store }">
      <!-- Header -->
      <AppShowHeader
        :title="app.name || 'Point of Sale'"
        :subtitle="`PoS - ${store.name}`"
        :app-url="btcpayAppUrl"
        open-button-text="Open PoS"
        form-id="pos-settings-form"
        save-button-text="Save Settings"
        saving-text="Saving..."
        :saving="saving"
        :error="''"
        :success="''"
      />

      <!-- Content Container -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form
          id="pos-settings-form"
          @submit.prevent="handleSubmit"
          class="space-y-6"
        >
          <div
            class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
          >
            <div class="p-6 sm:p-8 space-y-6">
              <!-- App Name and Title -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label
                    for="appName"
                    class="block text-sm font-medium text-gray-300 mb-1"
                  >
                    App Name <span class="text-red-400">*</span>
                  </label>
                  <input
                    id="appName"
                    v-model="form.appName"
                    type="text"
                    required
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>

                <div>
                  <label
                    for="title"
                    class="block text-sm font-medium text-gray-300 mb-1"
                  >
                    Title display to customer
                  </label>
                  <input
                    id="title"
                    v-model="form.title"
                    type="text"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>
              </div>

              <!-- Default View Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">
                  {{ $t('apps.pos_style') }}
                </label>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <!-- Light (Keypad) -->
                  <div
                    @click="form.defaultView = 'Light'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Light'
                        ? 'border-indigo-500 bg-indigo-500/10'
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800',
                    ]"
                  >
                    <div
                      :class="[
                        'p-3 rounded-full',
                        form.defaultView === 'Light'
                          ? 'bg-indigo-500 text-white'
                          : 'bg-gray-700 text-gray-400',
                      ]"
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
                          d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                        />
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-white">{{ $t('apps.pos_style_light') }}</span>
                    <div
                      v-if="form.defaultView === 'Light'"
                      class="absolute top-2 right-2 text-indigo-500"
                    >
                      <svg
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                  </div>

                  <!-- Static (Product List) -->
                  <div
                    @click="form.defaultView = 'Static'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Static'
                        ? 'border-indigo-500 bg-indigo-500/10'
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800',
                    ]"
                  >
                    <div
                      :class="[
                        'p-3 rounded-full',
                        form.defaultView === 'Static'
                          ? 'bg-indigo-500 text-white'
                          : 'bg-gray-700 text-gray-400',
                      ]"
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
                          d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-white"
                      >{{ $t('apps.pos_style_static') }}</span
                    >
                    <div
                      v-if="form.defaultView === 'Static'"
                      class="absolute top-2 right-2 text-indigo-500"
                    >
                      <svg
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                  </div>

                  <!-- Cart (Product List + Cart) -->
                  <div
                    @click="form.defaultView = 'Cart'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Cart'
                        ? 'border-indigo-500 bg-indigo-500/10'
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800',
                    ]"
                  >
                    <div
                      :class="[
                        'p-3 rounded-full',
                        form.defaultView === 'Cart'
                          ? 'bg-indigo-500 text-white'
                          : 'bg-gray-700 text-gray-400',
                      ]"
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
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-white"
                      >{{ $t('apps.pos_style_cart') }}</span
                    >
                    <div
                      v-if="form.defaultView === 'Cart'"
                      class="absolute top-2 right-2 text-indigo-500"
                    >
                      <svg
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                  </div>

                  <!-- Print -->
                  <div
                    @click="form.defaultView = 'Print'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Print'
                        ? 'border-indigo-500 bg-indigo-500/10'
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800',
                    ]"
                  >
                    <div
                      :class="[
                        'p-3 rounded-full',
                        form.defaultView === 'Print'
                          ? 'bg-indigo-500 text-white'
                          : 'bg-gray-700 text-gray-400',
                      ]"
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
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                        />
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-white"
                      >{{ $t('apps.pos_style_print') }}</span
                    >
                    <div
                      v-if="form.defaultView === 'Print'"
                      class="absolute top-2 right-2 text-indigo-500"
                    >
                      <svg
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <div
                v-if="
                  form.defaultView === 'Static' || form.defaultView === 'Cart'
                "
              >
                <label
                  for="description"
                  class="block text-sm font-medium text-gray-300 mb-1"
                >
                  Description
                </label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                ></textarea>
              </div>

              <!-- Keypad Settings -->
              <div v-if="form.defaultView === 'Light'" class="mt-6">
                <div
                  class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
                >
                  <input
                    id="showItems"
                    v-model="form.showItems"
                    type="checkbox"
                    class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                  />
                  <label
                    for="showItems"
                    class="ml-3 block text-sm font-medium text-gray-200"
                  >
                    Display item selection for keypad
                  </label>
                </div>
              </div>

              <!-- Products Editor -->
              <div v-if="shouldShowProductsEditor">
                <PointOfSaleProductsEditor
                  v-model:products="products"
                  :currency="form.currency || store?.default_currency || 'EUR'"
                  @add="addProduct"
                  @edit="editProduct"
                />
              </div>

              <!-- Advanced Settings Accordion -->
              <div class="">
                <div class="">
                  <button
                    type="button"
                    @click="showAdvancedSettings = !showAdvancedSettings"
                    class="border border-gray-700/50 w-full px-6 py-4 flex items-center justify-between text-left bg-gray-700/50 hover:bg-gray-700 transition-colors rounded-t-xl"
                  >
                    <span class="text-xl font-medium text-orange-500"
                      >Extra Settings</span
                    >
                    <svg
                      class="h-5 w-5 text-orange-500 transform transition-transform duration-200"
                      :class="{ 'rotate-180': showAdvancedSettings }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 9l-7 7-7-7"
                      />
                    </svg>
                  </button>
                  <div
                    v-show="showAdvancedSettings"
                    class="border border-gray-700/50 pt-6 rounded-b-xl overflow-hidden p-6py-6 space-y-6"
                  >
                    <div class="px-6">
                      <!-- Checkout Settings -->
                      <div>
                        <h3 class="text-lg font-medium text-white mb-4">
                          Checkout Settings
                        </h3>
                        <div
                          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 py-4"
                        >
                          <!-- Customer Data -->
                          <div>
                            <label
                              for="requestCustomerData"
                              class="block text-sm font-medium text-gray-300 mb-1"
                            >
                              Request customer data
                            </label>
                            <Select
                              id="requestCustomerData"
                              v-model="form.requestCustomerData"
                              :options="customerDataOptions"
                              placeholder="Do not request"
                            />
                          </div>

                          <!-- Currency -->
                          <div>
                            <label
                              for="currency"
                              class="block text-sm font-medium text-gray-300 mb-1"
                            >
                              Currency
                            </label>
                            <input
                              id="currency"
                              v-model="form.currency"
                              type="text"
                              list="currency-selection-suggestion"
                              placeholder="Select or type currency"
                              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
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
                              Uses store default ({{
                                store?.default_currency || "EUR"
                              }}) if empty
                            </p>
                          </div>

                          <!-- Tax Rate -->
                          <div>
                            <label
                              for="defaultTaxRate"
                              class="block text-sm font-medium text-gray-300 mb-1"
                            >
                              Default Tax Rate
                            </label>
                            <div class="flex rounded-xl shadow-sm">
                              <input
                                id="defaultTaxRate"
                                v-model.number="form.defaultTaxRate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="flex-1 min-w-0 block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-l-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                                placeholder="0.00"
                              />
                              <span
                                class="inline-flex items-center px-4 rounded-r-xl border border-l-0 border-gray-600 bg-gray-700 text-gray-300 text-sm font-medium"
                              >
                                %
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-4">
                        <!-- Tips -->
                        <div>
                          <div class="flex items-center mb-4">
                            <input
                              id="enableTips"
                              v-model="form.enableTips"
                              type="checkbox"
                              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                            />
                            <label
                              for="enableTips"
                              class="ml-3 block text-sm font-medium text-white"
                            >
                              Enable tips
                            </label>
                          </div>

                          <div v-if="form.enableTips">
                            <label
                              for="tipsMessage"
                              class="block text-xs font-medium text-gray-400 mb-1"
                            >
                              Tip Message <span class="text-red-400">*</span>
                            </label>
                            <input
                              id="tipsMessage"
                              v-model="form.tipsMessage"
                              type="text"
                              required
                              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                            />
                          </div>
                        </div>

                        <!-- Custom Payments - Only available for Static (Product list) and Print display -->
                        <div
                          v-if="
                            form.defaultView === 'Static' ||
                            form.defaultView === 'Print'
                          "
                          class=""
                        >
                          <div class="flex items-center mb-4">
                            <input
                              id="showCustomAmount"
                              v-model="form.showCustomAmount"
                              type="checkbox"
                              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                            />
                            <label
                              for="showCustomAmount"
                              class="ml-3 block text-sm font-medium text-white"
                            >
                              Allow custom amount
                            </label>
                          </div>

                          <div v-if="form.showCustomAmount">
                            <label
                              for="customAmountPayButtonText"
                              class="block text-xs font-medium text-gray-400 mb-1"
                            >
                              Pay Button Text
                              <span class="text-red-400">*</span>
                            </label>
                            <input
                              id="customAmountPayButtonText"
                              v-model="form.customAmountPayButtonText"
                              type="text"
                              required
                              class="block w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                            />
                          </div>
                        </div>

                        <!-- Discounts - Only available for Light (Keypad) and Cart (Product list with cart) -->
                        <div
                          v-if="
                            form.defaultView === 'Light' ||
                            form.defaultView === 'Cart'
                          "
                          class=""
                        >
                          <div class="flex items-center">
                            <input
                              id="showDiscount"
                              v-model="form.showDiscount"
                              type="checkbox"
                              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                            />
                            <label
                              for="showDiscount"
                              class="ml-3 block text-sm font-medium text-white"
                            >
                              Allow discount entry (%)
                            </label>
                          </div>
                          <p class="mt-2 text-xs text-gray-500 ml-8">
                            Not recommended for self-checkout.
                          </p>
                        </div>
                      </div>

                      <!-- Cart Options -->
                      <div
                        v-if="form.defaultView === 'Cart'"
                        class="border-t border-gray-700/50 pt-6"
                      >
                        <h3 class="text-lg font-medium text-white mb-4">
                          Cart Settings
                        </h3>
                        <div class="flex flex-col sm:flex-row gap-6">
                          <div
                            class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl flex-1"
                          >
                            <input
                              id="showSearch"
                              v-model="form.showSearch"
                              type="checkbox"
                              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                            />
                            <label
                              for="showSearch"
                              class="ml-3 block text-sm font-medium text-white"
                            >
                              Display search bar
                            </label>
                          </div>

                          <div
                            class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl flex-1"
                          >
                            <input
                              id="showCategories"
                              v-model="form.showCategories"
                              type="checkbox"
                              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                            />
                            <label
                              for="showCategories"
                              class="ml-3 block text-sm font-medium text-white"
                            >
                              Display categories
                            </label>
                          </div>
                        </div>
                      </div>

                      <!-- Button Text -->
                      <div
                        v-if="
                          form.defaultView === 'Static' ||
                          form.defaultView === 'Cart'
                        "
                        class="border-t border-gray-700/50 pt-6"
                      >
                        <label
                          for="fixedAmountPayButtonText"
                          class="block text-sm font-medium text-gray-300 mb-1"
                        >
                          Buy Button Text <span class="text-red-400">*</span>
                        </label>
                        <input
                          id="fixedAmountPayButtonText"
                          v-model="form.fixedAmountPayButtonText"
                          type="text"
                          required
                          placeholder="Buy for {0}"
                          class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                          Use {0} as placeholder for price.
                        </p>
                      </div>
                    </div>
                    <!-- Additional Options (Accordion style) -->
                    <div
                      class="border-t border-gray-700/50 p-6 space-y-4 bg-gray-700/50"
                    >
                      <div class="mb-4">
                        <div class="flex items-center gap-2">
                          <h3 class="text-xl font-bold text-white">
                            Advanced Options
                          </h3>
                          <button
                            v-if="!canEditAdvancedOptions"
                            type="button"
                            @click="showProUpgradeNotice = !showProUpgradeNotice"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 hover:bg-indigo-500/30 transition-colors"
                          >
                            {{ t("stores.available_in_pro") }}
                            <svg
                              class="w-3.5 h-3.5 transition-transform duration-200"
                              :class="{ 'rotate-180': showProUpgradeNotice }"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                              />
                            </svg>
                          </button>
                        </div>
                        <Transition
                          enter-active-class="transition ease-out duration-200"
                          enter-from-class="opacity-0 -translate-y-1"
                          enter-to-class="opacity-100 translate-y-0"
                          leave-active-class="transition ease-in duration-150"
                          leave-from-class="opacity-100 translate-y-0"
                          leave-to-class="opacity-0 -translate-y-1"
                        >
                          <div
                            v-if="!canEditAdvancedOptions && showProUpgradeNotice"
                            class="mt-3 p-3 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-sm text-indigo-200"
                          >
                            <p class="mb-2">
                              {{ t("stores.pos_advanced_options_pro_only") }}
                            </p>
                            <component
                              :is="isInertia ? InertiaLink : RouterLink"
                              :href="isInertia ? '/account' : undefined"
                              :to="!isInertia ? '/account' : undefined"
                              class="inline-flex items-center font-medium text-indigo-300 hover:text-indigo-200 underline underline-offset-2"
                            >
                              {{ t("stores.upgrade_to_pro") }}
                              <svg
                                class="w-4 h-4 ml-1"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                              >
                                <path
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                />
                              </svg>
                            </component>
                          </div>
                        </Transition>
                      </div>

                      <!-- HTML Headers -->
                      <div
                        class="border border-gray-700 rounded-xl overflow-hidden"
                      >
                        <button
                          type="button"
                          @click="
                            accordionSections.htmlHeaders =
                              !accordionSections.htmlHeaders
                          "
                          class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                          <span class="font-medium text-white"
                            >HTML Headers</span
                          >
                          <svg
                            class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                            :class="{
                              'rotate-180': accordionSections.htmlHeaders,
                            }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M19 9l-7 7-7-7"
                            />
                          </svg>
                        </button>
                        <div
                          v-show="accordionSections.htmlHeaders"
                          class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4"
                        >
                          <div>
                            <label
                              for="htmlLang"
                              class="block text-sm font-medium text-gray-300 mb-1"
                              >HTML Lang</label
                            >
                            <input
                              id="htmlLang"
                              v-model="form.htmlLang"
                              type="text"
                              placeholder="en"
                              :disabled="!canEditAdvancedOptions"
                              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                            />
                          </div>
                          <div>
                            <label
                              for="htmlMetaTags"
                              class="block text-sm font-medium text-gray-300 mb-1"
                              >Meta Tags</label
                            >
                            <textarea
                              id="htmlMetaTags"
                              v-model="form.htmlMetaTags"
                              rows="3"
                              :disabled="!canEditAdvancedOptions"
                              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                            ></textarea>
                          </div>
                        </div>
                      </div>

                      <!-- Redirects -->
                      <div
                        class="border border-gray-700 rounded-xl overflow-hidden"
                      >
                        <button
                          type="button"
                          @click="
                            accordionSections.redirects =
                              !accordionSections.redirects
                          "
                          class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                          <span class="font-medium text-white">Redirects</span>
                          <svg
                            class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                            :class="{
                              'rotate-180': accordionSections.redirects,
                            }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M19 9l-7 7-7-7"
                            />
                          </svg>
                        </button>
                        <div
                          v-show="accordionSections.redirects"
                          class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4"
                        >
                          <div>
                            <label
                              for="redirectUrl"
                              class="block text-sm font-medium text-gray-300 mb-1"
                              >Redirect URL</label
                            >
                            <input
                              id="redirectUrl"
                              v-model="form.redirectUrl"
                              type="url"
                              placeholder="https://example.com/thanks"
                              :disabled="!canEditAdvancedOptions"
                              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                            />
                          </div>
                          <div>
                            <label
                              for="redirectAutomatically"
                              class="block text-sm font-medium text-gray-300 mb-1"
                              >Redirect Automatically</label
                            >
                            <div
                              :class="{
                                'pointer-events-none opacity-60': !canEditAdvancedOptions,
                              }"
                            >
                              <Select
                                id="redirectAutomatically"
                                v-model="form.redirectAutomatically"
                                :options="redirectOptions"
                                placeholder="Use Store Settings"
                              />
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Notifications -->
                      <div
                        class="border border-gray-700 rounded-xl overflow-hidden"
                      >
                        <button
                          type="button"
                          @click="
                            accordionSections.notifications =
                              !accordionSections.notifications
                          "
                          class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                          <span class="font-medium text-white"
                            >Notification URL Callbacks</span
                          >
                          <svg
                            class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                            :class="{
                              'rotate-180': accordionSections.notifications,
                            }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M19 9l-7 7-7-7"
                            />
                          </svg>
                        </button>
                        <div
                          v-show="accordionSections.notifications"
                          class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4"
                        >
                          <div>
                            <label
                              for="notificationUrl"
                              class="block text-sm font-medium text-gray-300 mb-1"
                              >Callback Notification URL</label
                            >
                            <input
                              id="notificationUrl"
                              v-model="form.notificationUrl"
                              type="url"
                              placeholder="https://example.com/callback"
                              :disabled="!canEditAdvancedOptions"
                              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                            />
                          </div>
                          <div
                            class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl"
                          >
                            <p class="text-sm text-red-400 font-bold mb-2">
                              ⚠️ Security Warning
                            </p>
                            <p class="text-xs text-red-300">
                              Never trust any field other than the invoice ID.
                              Verify details with your backend.
                            </p>
                          </div>
                        </div>
                      </div>

                      <!-- Embeds -->
                      <div
                        class="border border-gray-700 rounded-xl overflow-hidden"
                      >
                        <button
                          type="button"
                          @click="
                            accordionSections.embed = !accordionSections.embed
                          "
                          class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                          <span class="font-medium text-white"
                            >Embed Codes</span
                          >
                          <svg
                            class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                            :class="{ 'rotate-180': accordionSections.embed }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M19 9l-7 7-7-7"
                            />
                          </svg>
                        </button>
                        <div
                          v-show="accordionSections.embed"
                          :class="[
                            'px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-6',
                            !canEditAdvancedOptions &&
                              'select-none pointer-events-none opacity-75',
                          ]"
                        >
                          <div>
                            <p class="text-sm font-medium text-gray-300 mb-2">
                              Button Embed Code
                            </p>
                            <div
                              class="bg-gray-950 p-4 rounded-xl border border-gray-700 overflow-x-auto"
                            >
                              <pre class="text-xs text-gray-400 font-mono">{{
                                embedFormCode
                              }}</pre>
                            </div>
                          </div>
                          <div>
                            <p class="text-sm font-medium text-gray-300 mb-2">
                              Iframe Embed Code
                            </p>
                            <div
                              class="bg-gray-950 p-4 rounded-xl border border-gray-700 overflow-x-auto"
                            >
                              <pre class="text-xs text-gray-400 font-mono">{{
                                embedIframeCode
                              }}</pre>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Delete Button -->
                <div class="border-t border-gray-700/50 pt-6">
                  <button
                    type="button"
                    @click="showDeleteModal = true"
                    :disabled="saving || deleting"
                    class="inline-flex items-center px-4 py-2 border border-red-600 rounded-xl text-sm font-medium text-red-400 hover:bg-red-600 hover:text-white transition-colors"
                  >
                    <svg
                      class="w-4 h-4 mr-2"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                      />
                    </svg>
                    Delete App
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </template>
  </AppShowLayout>

  <!-- Delete Confirmation Modal -->
  <DeleteAppModal
    :is-open="showDeleteModal"
    :app-name="layoutRef?.app?.name || ''"
    :deleting="deleting"
    :error="deleteError"
    @close="showDeleteModal = false"
    @delete="handleDelete"
  />

  <!-- Product Edit Drawer -->
  <ProductEditDrawer
    :is-open="showProductDrawer"
    :product="currentProduct"
    :currency="form.currency || layoutRef?.store?.default_currency || 'EUR'"
    :store-id="storeId"
    @close="showProductDrawer = false"
    @save="handleProductSave"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch, watchEffect, inject } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { router as inertiaRouter, Link as InertiaLink } from "@inertiajs/vue3";
import { RouterLink } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useAppsStore } from "../../store/apps";
import { useFlashStore } from "../../store/flash";
import ProductEditDrawer from "../../components/stores/ProductEditDrawer.vue";
import PointOfSaleProductsEditor from "../../components/stores/PointOfSaleProductsEditor.vue";
import AppShowLayout from "../../components/stores/AppShowLayout.vue";
import Select from "../../components/ui/Select.vue";
import AppShowHeader from "../../components/stores/AppShowHeader.vue";
import DeleteAppModal from "../../components/stores/DeleteAppModal.vue";
import { currencies } from "../../data/currencies";

const { t } = useI18n();
const flashStore = useFlashStore();
const isInertia = inject<boolean>("inertia", false);
const route = !isInertia ? useRoute() : null;
const vueRouter = !isInertia ? useRouter() : null;
const authStore = useAuthStore();
const appsStore = useAppsStore();

const props = defineProps<{ store?: any; app?: any }>();

const planCode = computed(
  () => (authStore.user?.plan?.code ?? "free") as string,
);
const canEditAdvancedOptions = computed(
  () => planCode.value === "pro" || planCode.value === "enterprise",
);

const storeId = computed(() => (props.store?.id ?? route?.params?.id ?? "") as string);
const appId = computed(() => (props.app?.id ?? route?.params?.appId ?? "") as string);
const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const saving = ref(false);
const error = ref("");
const success = ref("");

const customerDataOptions = [
  { label: 'Do not request', value: '' },
  { label: 'Email only', value: 'email' },
  { label: 'Name only', value: 'name' },
  { label: 'Email and Name', value: 'email_name' },
];

const redirectOptions = [
  { label: 'Use Store Settings', value: '' },
  { label: 'Yes', value: 'true' },
  { label: 'No', value: 'false' },
];

const showDeleteModal = ref(false);
const deleteError = ref("");
const deleting = ref(false);
const showAdvancedSettings = ref(false);

// Products editor state
const products = ref<any[]>([]);
const showProductDrawer = ref(false);
const editingProductIndex = ref<number | null>(null);

const form = ref({
  appName: "",
  title: "",
  description: "",
  defaultView: "Light",
  currency: "",
  showItems: false,
  showCustomAmount: false,
  showDiscount: false,
  showSearch: false,
  showCategories: false,
  enableTips: false,
  tipsMessage: "Do you want to leave a tip?",
  defaultTaxRate: 0,
  requestCustomerData: "",
  fixedAmountPayButtonText: "Buy for {0}",
  customAmountPayButtonText: "Pay",
  htmlLang: "",
  htmlMetaTags: "",
  redirectUrl: "",
  redirectAutomatically: "",
  notificationUrl: "",
});

const accordionSections = ref({
  htmlHeaders: false,
  redirects: false,
  notifications: false,
  embed: false,
});

const showProUpgradeNotice = ref(false);

// Computed property to determine if products editor should be shown
const shouldShowProductsEditor = computed(() => {
  if (
    form.value.defaultView === "Static" ||
    form.value.defaultView === "Cart" ||
    form.value.defaultView === "Print"
  ) {
    return true;
  }
  if (form.value.defaultView === "Light" && form.value.showItems) {
    return true;
  }
  return false;
});

// Computed property for BTCPay app URL
const btcpayAppUrl = computed(() => {
  const app = layoutRef.value?.app;
  if (!app) return "";
  const baseUrl = (import.meta as any).env.VITE_BTCPAY_BASE_URL || "https://satflux.org";
  let id =
    app.btcpay_app_id ||
    (app.config && app.config.id) ||
    (app.config && app.config.appId);

  if (!id && app.btcpay_app_url) {
    const urlParts = app.btcpay_app_url.split("/");
    id = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }

  if (!id) return "";
  return `${baseUrl}/apps/${id}/pos`;
});

const embedFormCode = computed(() => {
  if (!btcpayAppUrl.value) return "";
  return `<form method="POST" action="${btcpayAppUrl.value}">
  <input type="hidden" name="email" value="customer@example.com" />
  <input type="hidden" name="orderId" value="CustomOrderId" />
  <input type="hidden" name="notificationUrl" value="https://example.com/callbacks" />
  <input type="hidden" name="redirectUrl" value="https://example.com/thankyou" />
  <button type="submit" name="choiceKey" value="produkt">Buy now</button>
</form>`;
});

const embedIframeCode = computed(() => {
  if (!btcpayAppUrl.value) return "";
  return `<iframe src='${btcpayAppUrl.value}' style='max-width: 100%; border: 0;'></iframe>`;
});

// Helper function to generate product ID
function generateProductId(title: string): string {
  if (!title) return "";
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function addProduct() {
  editingProductIndex.value = null;
  showProductDrawer.value = true;
}

function editProduct(index: number) {
  editingProductIndex.value = index;
  showProductDrawer.value = true;
}

function handleProductSave(product: any) {
  if (editingProductIndex.value !== null) {
    products.value[editingProductIndex.value] = product;
  } else {
    products.value.push(product);
  }
  editingProductIndex.value = null;
}

const currentProduct = computed(() => {
  if (
    editingProductIndex.value !== null &&
    products.value[editingProductIndex.value]
  ) {
    return products.value[editingProductIndex.value];
  }
  return null;
});

// Helper function to parse boolean values from BTCPay API response
// BTCPay might return booleans, strings ("true"/"false"), numbers (1/0), or null/undefined
function parseBoolean(value: any, defaultValue: boolean = false): boolean {
  if (value === null || value === undefined) {
    return defaultValue;
  }
  if (typeof value === "boolean") {
    return value;
  }
  if (typeof value === "string") {
    const lower = value.toLowerCase().trim();
    return lower === "true" || lower === "1" || lower === "yes";
  }
  if (typeof value === "number") {
    return value !== 0;
  }
  return Boolean(value);
}

async function loadApp(clearErrors: boolean = true) {
  const app = layoutRef.value?.app;
  const store = layoutRef.value?.store;

  if (!app || !store) return;

  const hadSuccess = success.value;
  // Don't clear errors if we're reloading after successful submit
  if (clearErrors && !hadSuccess && !isReloadingAfterSubmit.value) {
    error.value = "";
  }
  // Don't reload form if we have success message (prevents overwriting with stale data)
  if (hadSuccess && !isReloadingAfterSubmit.value) {
    return;
  }

  // IMPORTANT: If we're reloading after successful submit, don't load form values from BTCPay
  // because they might be stale (cached) and would overwrite the values we just saved
  // Only update app name, and let the caller restore the saved form values
  const skipFormLoad = isReloadingAfterSubmit.value && success.value;

  form.value.appName = app.name || "";

  // Only load form values if we're not reloading after successful save
  if (!skipFormLoad && app.config) {
    const config = app.config;
    form.value.title = config.title || "";
    form.value.description = config.description || "";
    // Ensure defaultView is always 'Light' for new apps or if not set
    const savedDefaultView = config.defaultView;
    if (
      savedDefaultView &&
      ["Light", "Static", "Cart", "Print"].includes(savedDefaultView)
    ) {
      form.value.defaultView = savedDefaultView;
    } else {
      // If no valid defaultView, use 'Light' as default
      form.value.defaultView = "Light";
    }
    form.value.currency = config.currency || store?.default_currency || "EUR";
    // Parse boolean values correctly - BTCPay might return strings or booleans
    form.value.showItems = parseBoolean(config.showItems, false);
    form.value.showCustomAmount = parseBoolean(config.showCustomAmount, false);
    form.value.showDiscount = parseBoolean(config.showDiscount, false);
    form.value.showSearch = parseBoolean(config.showSearch, true);
    form.value.showCategories = parseBoolean(config.showCategories, true);
    form.value.enableTips = parseBoolean(config.enableTips, false);
    form.value.tipsMessage =
      config.tipsMessage || "Do you want to leave a tip?";
    form.value.defaultTaxRate = config.defaultTaxRate
      ? parseFloat(String(config.defaultTaxRate))
      : 0;
    form.value.requestCustomerData = config.requestCustomerData || "";
    form.value.fixedAmountPayButtonText =
      config.fixedAmountPayButtonText || "Buy for {0}";
    form.value.customAmountPayButtonText =
      config.customAmountPayButtonText || "Pay";
    form.value.htmlLang = config.htmlLang || "";
    form.value.htmlMetaTags = config.htmlMetaTags || "";
    form.value.redirectUrl = config.redirectUrl || "";
    form.value.redirectAutomatically =
      config.redirectAutomatically !== undefined
        ? String(config.redirectAutomatically)
        : "";
    form.value.notificationUrl = config.notificationUrl || "";

    let templateArray: any[] = [];
    const productsSource = config.items || config.template;

    if (productsSource) {
      if (Array.isArray(productsSource)) {
        templateArray = productsSource;
      } else if (typeof productsSource === "string") {
        try {
          const parsed = JSON.parse(productsSource);
          templateArray = Array.isArray(parsed)
            ? parsed
            : Array.isArray(parsed.template)
              ? parsed.template
              : Array.isArray(parsed.items)
                ? parsed.items
                : [parsed];
        } catch (e) {
          console.warn("Failed to parse products", e);
          templateArray = [];
        }
      }
    }

    if (templateArray && templateArray.length > 0) {
      products.value = templateArray.map((p: any) => {
        let inventory: number | null = null;
        if (
          p.inventory !== null &&
          p.inventory !== undefined &&
          p.inventory !== ""
        ) {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) {
            inventory = invNum;
          }
        }

        return {
          id: p.id || "",
          title: p.title || "",
          priceType: p.priceType || "Fixed",
          price: p.price ? parseFloat(String(p.price)) : 0,
          taxRate:
            p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== ""
              ? parseFloat(String(p.taxRate))
              : null,
          image: p.image || "",
          description: p.description || "",
          categories: p.categories
            ? Array.isArray(p.categories)
              ? p.categories.join(", ")
              : String(p.categories)
            : null,
          inventory: inventory,
          buyButtonText: p.buyButtonText || null,
          disabled: p.disabled !== undefined ? p.disabled : false,
        };
      });
    } else {
      products.value = [];
    }
  } else {
    form.value.currency = store?.default_currency || "EUR";
    products.value = [];
  }
}

const isReloadingAfterSubmit = ref(false);

// Track current app ID to detect when switching between apps
const currentAppId = ref<string | null>(null);

watch(
  () => layoutRef.value?.app,
  (newApp) => {
    // If app ID changed, we're switching apps - clear success and reset reloading flag
    if (newApp && newApp.id !== currentAppId.value) {
      currentAppId.value = newApp.id;
      success.value = ""; // Clear success when switching apps
      isReloadingAfterSubmit.value = false; // Reset reloading flag
      loadApp();
      return;
    }

    // Normal reload logic
    if (isReloadingAfterSubmit.value) return;
    if (success.value) return;
    loadApp();
  },
  { immediate: true, deep: true },
);

// Clear error when success is set
watch(success, (newSuccess) => {
  if (newSuccess) {
    error.value = "";
  }
});

// Prevent error from showing if we have success message
watch(
  error,
  (newError) => {
    if (newError && success.value) {
      // Clear error immediately if success is set
      error.value = "";
    }
  },
  { immediate: true },
);

// Additional safeguard: clear error if success is set
watchEffect(() => {
  if (success.value && error.value) {
    error.value = "";
  }
});

async function handleSubmit() {
  const app = layoutRef.value?.app;
  if (!app) return;

  saving.value = true;
  error.value = "";
  success.value = "";

  try {
    const template = products.value
      .filter((p) => p.title)
      .map((p) => {
        let inventory: number | null = null;
        if (
          p.inventory !== null &&
          p.inventory !== undefined &&
          p.inventory !== ""
        ) {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) inventory = invNum;
        }

        return {
          id: p.id || generateProductId(p.title),
          title: p.title,
          disabled: p.disabled || false,
          description: p.description || null,
          categories: p.categories
            ? String(p.categories)
                .split(",")
                .map((c: string) => c.trim())
                .filter((c: string) => c)
            : null,
          image: p.image || null,
          priceType: p.priceType || "Fixed",
          price:
            p.priceType !== "Free" && p.priceType !== "Topup"
              ? String(p.price || 0)
              : null,
          buyButtonText: p.buyButtonText || null,
          inventory: inventory,
          taxRate:
            p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== ""
              ? String(p.taxRate)
              : null,
        };
      });

    const config: any = {
      appName: form.value.appName,
      title: form.value.title,
      defaultView: form.value.defaultView, // This should be 'Light', 'Static', 'Cart', or 'Print'
      currency: form.value.currency,
      showItems: form.value.showItems,
      showCustomAmount: form.value.showCustomAmount,
      showDiscount: form.value.showDiscount,
      showSearch: form.value.showSearch,
      showCategories: form.value.showCategories,
      enableTips: form.value.enableTips,
      tipsMessage: form.value.tipsMessage || null,
      defaultTaxRate: form.value.defaultTaxRate
        ? String(form.value.defaultTaxRate)
        : null,
      requestCustomerData: form.value.requestCustomerData || null,
      fixedAmountPayButtonText: form.value.fixedAmountPayButtonText,
      customAmountPayButtonText: form.value.customAmountPayButtonText,
      htmlLang: form.value.htmlLang || null,
      htmlMetaTags: form.value.htmlMetaTags || null,
      redirectUrl: form.value.redirectUrl || null,
      redirectAutomatically: form.value.redirectAutomatically
        ? form.value.redirectAutomatically === "true"
          ? true
          : form.value.redirectAutomatically === "false"
            ? false
            : null
        : null,
      notificationUrl: form.value.notificationUrl || null,
    };

    // Free tier: do not send advanced options so we don't overwrite them
    if (!canEditAdvancedOptions.value) {
      delete config.htmlLang;
      delete config.htmlMetaTags;
      delete config.redirectUrl;
      delete config.redirectAutomatically;
      delete config.notificationUrl;
    }

    if (
      form.value.defaultView === "Static" ||
      form.value.defaultView === "Cart"
    ) {
      config.description = form.value.description || null;
    }

    // Always save template/products if they exist, regardless of whether editor is visible
    // This ensures products are saved even if editor visibility changes
    if (template && template.length > 0) {
      config.template = template;
    } else if (shouldShowProductsEditor.value) {
      // If no products but editor should be visible, set empty array
      config.template = [];
    }

    await appsStore.updateApp(storeId.value, appId.value, {
      name: form.value.appName,
      config,
    });

    // Set success and clear error immediately; show flash toast
    success.value = "Settings saved successfully";
    error.value = "";
    flashStore.success(t("settings.settings_updated"));
    isReloadingAfterSubmit.value = true;

    try {
      // Reload app data after a short delay
      await new Promise((resolve) => setTimeout(resolve, 500));
      if (layoutRef.value) {
        try {
          await (layoutRef.value as any).loadApp();
        } catch (reloadError) {
          // Silently ignore errors during reload after successful save
          console.warn("Error reloading app after save:", reloadError);
        }
      }
      await new Promise((resolve) => setTimeout(resolve, 0));

      // Manually refresh form data from updated app
      // Only reload if we still have success (wasn't cleared by error)
      // IMPORTANT: Save all form values AND products before reload to prevent overwriting with stale data
      const savedFormValues = {
        defaultView: form.value.defaultView,
        showItems: form.value.showItems,
        showCustomAmount: form.value.showCustomAmount,
        showDiscount: form.value.showDiscount,
        showSearch: form.value.showSearch,
        showCategories: form.value.showCategories,
        enableTips: form.value.enableTips,
        tipsMessage: form.value.tipsMessage,
        defaultTaxRate: form.value.defaultTaxRate,
        requestCustomerData: form.value.requestCustomerData,
        fixedAmountPayButtonText: form.value.fixedAmountPayButtonText,
        customAmountPayButtonText: form.value.customAmountPayButtonText,
        currency: form.value.currency,
        title: form.value.title,
        description: form.value.description,
        htmlLang: form.value.htmlLang,
        htmlMetaTags: form.value.htmlMetaTags,
        redirectUrl: form.value.redirectUrl,
        redirectAutomatically: form.value.redirectAutomatically,
        notificationUrl: form.value.notificationUrl,
      };

      // Also save products array (deep copy to prevent reference issues)
      const savedProducts = JSON.parse(JSON.stringify(products.value));

      if (success.value) {
        // Reload app data (but don't load form values - loadApp will skip form loading)
        await loadApp(false);
        // Restore ALL form values we just saved
        // This is necessary because BTCPay API might return stale/cached data
        Object.assign(form.value, savedFormValues);
        // Restore products array
        products.value = savedProducts;
      }
    } finally {
      isReloadingAfterSubmit.value = false;
      // Ensure error is cleared and success is maintained after successful save
      if (success.value) {
        error.value = "";
        // Keep success message visible
        success.value = "Settings saved successfully";
      }
    }
  } catch (err: any) {
    // Only set error if we don't have success
    if (!success.value) {
      const msg = err.response?.data?.message || t("settings.failed_to_update");
      error.value = msg;
      flashStore.error(msg);
      success.value = "";
    }
  } finally {
    saving.value = false;
    // Always clear error if we have success message
    if (success.value) {
      error.value = "";
    }
  }
}

async function handleDelete() {
  const app = layoutRef.value?.app;
  if (!app) return;

  deleting.value = true;
  deleteError.value = "";

  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    if (isInertia) {
      inertiaRouter.visit(`/stores/${storeId.value}`);
    } else {
      vueRouter!.push({ name: "stores-show", params: { id: storeId.value } });
    }
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || "Failed to delete app";
  } finally {
    deleting.value = false;
  }
}
</script>

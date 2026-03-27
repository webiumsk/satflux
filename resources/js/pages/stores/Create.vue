<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-3xl font-bold text-white mb-8 text-center">
      {{ t("create_store.title") }}
    </h1>

    <!-- Store limit reached: show message + upgrade modal only -->
    <div
      v-if="storeLimitReached"
      class="bg-gray-800 shadow-2xl rounded-2xl border border-gray-700 p-8 text-center"
    >
      <p class="text-gray-300 mb-6">
        {{ t("stores.store_limit_reached", { max: limits?.stores?.max ?? 1 }) }}
      </p>
      <button
        type="button"
        @click="showUpgradeModal = true"
        class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        {{ t("stores.view_plan_options") }}
      </button>
      <div class="mt-6">
        <router-link
          to="/stores"
          class="text-sm text-indigo-400 hover:text-indigo-300"
        >
          {{ t("stores.back_to_stores") }}
        </router-link>
      </div>
    </div>

    <div
      v-else
      class="bg-gray-800 shadow-2xl rounded-2xl border border-gray-700"
    >
      <!-- Step Indicator -->
      <div class="bg-gray-900/50 border-b border-gray-700 px-8 py-6">
        <div class="flex items-center justify-between relative">
          <!-- Connecting Line -->
          <div
            class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-700 -z-0"
          ></div>
          <div
            class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-indigo-600 transition-all duration-500 ease-in-out -z-0"
            :style="{ width: ((currentStep - 1) / 2) * 100 + '%' }"
          ></div>

          <div
            class="flex flex-col items-center relative z-10 group cursor-pointer"
            @click="currentStep > 1 ? (currentStep = 1) : null"
          >
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all',
                currentStep >= 1
                  ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800'
                  : 'bg-gray-700 text-gray-400 border-2 border-gray-600',
              ]"
            >
              1
            </div>
            <span
              class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded"
              :class="currentStep >= 1 ? 'text-indigo-400' : 'text-gray-500'"
              >{{ t("create_store.step_basic_info") }}</span
            >
          </div>

          <div
            class="flex flex-col items-center relative z-10 group cursor-pointer"
            @click="currentStep > 2 ? (currentStep = 2) : null"
          >
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all',
                currentStep >= 2
                  ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800'
                  : 'bg-gray-700 text-gray-400 border-2 border-gray-600',
              ]"
            >
              2
            </div>
            <span
              class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded"
              :class="currentStep >= 2 ? 'text-indigo-400' : 'text-gray-500'"
              >{{ t("create_store.step_wallet_type") }}</span
            >
          </div>

          <div class="flex flex-col items-center relative z-10">
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg transition-all',
                currentStep >= 3
                  ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-gray-800'
                  : 'bg-gray-700 text-gray-400 border-2 border-gray-600',
              ]"
            >
              3
            </div>
            <span
              class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded"
              :class="currentStep >= 3 ? 'text-indigo-400' : 'text-gray-500'"
              >{{ t("create_store.step_confirm") }}</span
            >
          </div>
        </div>
      </div>

      <div class="p-8 sm:p-10">
        <!-- Step 1: Basic Info -->
        <div v-if="currentStep === 1" class="space-y-6">
          <div>
            <label
              for="name"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("create_store.store_name") }}</label
            >
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              :placeholder="t('create_store.store_name_placeholder')"
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            />
          </div>
          <div>
            <label
              for="default_currency"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("create_store.default_currency") }}</label
            >
            <input
              id="default_currency"
              v-model="form.default_currency"
              type="text"
              list="currency-selection-suggestion"
              required
              :placeholder="t('create_store.currency_placeholder')"
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
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
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="timezone"
                class="block text-sm font-medium text-gray-300 mb-1"
                >{{ t("create_store.timezone") }}</label
              >
              <Select
                id="timezone"
                v-model="form.timezone"
                :options="timezoneOptions"
                placeholder="Select timezone"
              />
            </div>
            <div>
              <label
                for="preferred_exchange"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                {{ t("create_store.preferred_price_source") }}
              </label>
              <Select
                id="preferred_exchange"
                v-model="form.preferred_exchange"
                :options="exchanges"
                placeholder="Select preferred exchange"
              />
            </div>
          </div>
          <p class="text-xs text-gray-500">
            {{ t("create_store.price_source_description") }}
          </p>

          <div class="flex justify-end pt-4">
            <button
              @click="currentStep = 2"
              :disabled="!form.name || !form.default_currency || !form.timezone"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              {{ t("create_store.next_step") }}
              <svg
                class="ml-2 -mr-1 w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M14 5l7 7m0 0l-7 7m7-7H3"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Step 2: Wallet Type -->
        <div v-if="currentStep === 2" class="space-y-6">
          <div>
            <p
              class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider"
            >
              {{ t("create_store.choose_wallet_backend") }}
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label
                class="relative flex flex-col p-6 border-2 rounded-2xl cursor-pointer transition-all hover:bg-gray-700/50"
                :class="
                  form.wallet_type === 'blink'
                    ? 'border-indigo-500 bg-indigo-900/10'
                    : 'border-gray-600 bg-gray-800'
                "
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-bold text-white text-lg">{{
                    t("create_store.blink_wallet")
                  }}</span>
                  <div
                    v-if="form.wallet_type === 'blink'"
                    class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-white"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="3"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </div>
                  <div
                    v-else
                    class="w-6 h-6 rounded-full border-2 border-gray-500"
                  ></div>
                </div>
                <p class="text-sm text-gray-400">
                  {{ t("create_store.blink_description") }}
                </p>
                <input
                  type="radio"
                  v-model="form.wallet_type"
                  value="blink"
                  class="hidden"
                />
              </label>

              <label
                class="relative flex flex-col p-6 border-2 rounded-2xl cursor-pointer transition-all hover:bg-gray-700/50"
                :class="
                  form.wallet_type === 'aqua_boltz'
                    ? 'border-indigo-500 bg-indigo-900/10'
                    : 'border-gray-600 bg-gray-800'
                "
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-bold text-white text-lg">{{
                    t("create_store.aqua_wallet")
                  }}</span>
                  <div
                    v-if="form.wallet_type === 'aqua_boltz'"
                    class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-white"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="3"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </div>
                  <div
                    v-else
                    class="w-6 h-6 rounded-full border-2 border-gray-500"
                  ></div>
                </div>
                <p class="text-sm text-gray-400">
                  {{ t("create_store.aqua_description") }}
                </p>
                <input
                  type="radio"
                  v-model="form.wallet_type"
                  value="aqua_boltz"
                  class="hidden"
                />
              </label>

              <label
                class="relative flex flex-col p-6 border-2 rounded-2xl cursor-pointer transition-all hover:bg-gray-700/50"
                :class="
                  form.wallet_type === 'cashu'
                    ? 'border-indigo-500 bg-indigo-900/10'
                    : 'border-gray-600 bg-gray-800'
                "
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-bold text-white text-lg">{{
                    t("create_store.wallet_type_cashu")
                  }}</span>
                  <div
                    v-if="form.wallet_type === 'cashu'"
                    class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-white"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="3"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </div>
                  <div
                    v-else
                    class="w-6 h-6 rounded-full border-2 border-gray-500"
                  ></div>
                </div>
                <p class="text-sm text-gray-400">
                  {{ t("create_store.cashu_description") }}
                </p>
                <input
                  type="radio"
                  v-model="form.wallet_type"
                  value="cashu"
                  class="hidden"
                />
              </label>
            </div>
            <div
              v-if="form.wallet_type === 'cashu'"
              class="mt-4 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 space-y-2"
            >
              <p class="text-sm font-medium text-amber-300">
                {{ t("stores.cashu_warning_title") }}
              </p>
              <ul class="text-sm text-amber-400 list-disc list-inside space-y-1.5">
                <li>{{ t("stores.cashu_warning_https") }}</li>
                <li>{{ t("stores.cashu_warning_mint_reachable") }}</li>
                <li>{{ t("stores.cashu_warning_ln_address") }}</li>
              </ul>
            </div>
          </div>

          <!-- Connection String Input -->
          <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
          >
            <div
              v-if="form.wallet_type"
              class="bg-gray-900/50 rounded-xl p-6 border border-gray-700"
            >
              <template v-if="form.wallet_type === 'cashu'">
                <div class="space-y-5">
                  <div>
                    <label
                      for="mint_url"
                      class="block text-sm font-medium text-gray-500 mb-2"
                    >
                      {{ t("stores.cashu_mint_url_label") }}
                    </label>
                    <input
                      id="mint_url"
                      v-model="form.mint_url"
                      type="text"
                      required
                      :placeholder="t('stores.cashu_mint_url_placeholder')"
                      class="block w-full rounded-lg p-2 border-gray-600 bg-gray-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm"
                    />
                    <p class="mt-2 text-sm text-gray-500 leading-relaxed whitespace-pre-line">
                      {{ t("stores.cashu_mint_url_hint") }}
                    </p>
                  </div>

                  <div>
                    <label for="lightning_address" class="block text-sm font-medium text-gray-500 mb-2">
                      {{ t("stores.cashu_lightning_address_label") }}
                    </label>
                    <input
                      id="lightning_address"
                      v-model="form.lightning_address"
                      type="text"
                      required
                      :placeholder="t('stores.cashu_lightning_address_placeholder')"
                      class="block w-full rounded-lg p-2 border-gray-600 bg-gray-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p class="mt-2 text-sm text-gray-500 leading-relaxed">
                      {{ t("stores.cashu_lightning_address_hint") }}
                    </p>
                  </div>

                  <p class="text-sm text-gray-500 leading-relaxed border-t border-gray-700 pt-4">
                    {{ t("stores.cashu_footer_help") }}
                  </p>
                </div>
              </template>

              <template v-else-if="form.wallet_type === 'blink'">
                <label
                  for="connection_string_blink"
                  class="block text-sm font-medium text-indigo-300 mb-2"
                >
                  {{ t("create_store.connection_string") }}
                </label>
                <textarea
                  id="connection_string_blink"
                  v-model="form.connection_string"
                  rows="4"
                  class="block w-full rounded-lg p-2 border-gray-600 bg-gray-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm placeholder-gray-600"
                  placeholder="type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx"
                ></textarea>
                <p
                  class="mt-3 p-3 rounded-lg border border-amber-500/30 bg-amber-500/10 text-sm text-amber-400"
                >
                  {{ t("stores.blink_keys_warning") }}
                  <a
                    href="https://dashboard.blink.sv/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline hover:text-amber-300 ml-1"
                    >{{ t("stores.blink_dashboard_link") }}</a
                  >
                </p>
                <p class="mt-3 text-sm text-gray-400 leading-relaxed">
                  {{ t("create_store.connection_string_format") }}<br />
                  {{ t("create_store.connection_string_help") }}
                </p>
                <p class="mt-2 text-sm text-gray-400">
                  <a
                    :href="docBlinkPath"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-indigo-400 hover:text-indigo-300 hover:underline"
                  >
                    {{ t("create_store.doc_link_blink") }}
                    <svg
                      class="inline w-3.5 h-3.5 ml-0.5"
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
                  </a>
                </p>
              </template>

              <template v-else-if="form.wallet_type === 'aqua_boltz'">
                <div
                  class="mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10"
                >
                  <p class="text-sm text-amber-400">
                    {{ t("stores.aqua_warning_btcpay") }}
                  </p>
                  <p class="text-sm text-amber-400 mt-2">
                    {{ t("stores.aqua_limits_warning") }}
                  </p>
                </div>
                <div class="flex gap-1 p-1 rounded-xl bg-gray-800/90 border border-gray-600 w-full sm:w-auto mb-6">
                  <button
                    type="button"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all"
                    :class="
                      aquaSetupTab === 'samrock'
                        ? 'bg-indigo-600 text-white shadow-md'
                        : 'text-gray-400 hover:text-white hover:bg-gray-700/80'
                    "
                    @click="aquaSetupTab = 'samrock'"
                  >
                    {{ t("create_store.tab_samrock") }}
                  </button>
                  <button
                    type="button"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-medium transition-all"
                    :class="
                      aquaSetupTab === 'descriptor'
                        ? 'bg-indigo-600 text-white shadow-md'
                        : 'text-gray-400 hover:text-white hover:bg-gray-700/80'
                    "
                    @click="aquaSetupTab = 'descriptor'"
                  >
                    {{ t("create_store.tab_descriptor") }}
                  </button>
                </div>

                <div v-show="aquaSetupTab === 'samrock'" class="space-y-4">
                  <p class="text-sm font-medium text-indigo-300">
                    {{ t("create_store.samrock_hint_title") }}
                  </p>
                  <p class="text-sm text-gray-300 leading-relaxed">
                    {{ t("create_store.samrock_hint_body") }}
                  </p>
                  <p class="text-xs text-gray-500">
                    {{ t("stores.samrock_require_plugin") }}
                  </p>
                </div>

                <div v-show="aquaSetupTab === 'descriptor'" class="space-y-3">
                  <label
                    for="connection_string_aqua"
                    class="block text-sm font-medium text-indigo-300 mb-2"
                  >
                    {{ t("create_store.descriptor") }}
                  </label>
                  <textarea
                    id="connection_string_aqua"
                    v-model="form.connection_string"
                    rows="4"
                    class="block w-full rounded-lg p-2 border-gray-600 bg-gray-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm placeholder-gray-600"
                    placeholder="ct(slip77(...),elsh(wpkh(...))))"
                  ></textarea>
                  <p class="mt-3 text-sm text-gray-400 leading-relaxed">
                    {{ t("create_store.descriptor_help") }}<br />
                    {{ t("create_store.descriptor_example") }}
                  </p>
                  <p class="mt-2 text-sm text-gray-400">
                    <a
                      :href="docAquaPath"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-indigo-400 hover:text-indigo-300 hover:underline"
                    >
                      {{ t("create_store.doc_link_aqua") }}
                      <svg
                        class="inline w-3.5 h-3.5 ml-0.5"
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
                    </a>
                  </p>
                </div>
              </template>
            </div>
          </transition>

          <div class="flex justify-between pt-4">
            <button
              @click="currentStep = 1"
              class="inline-flex justify-center py-3 px-6 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 transition-all"
            >
              <svg
                class="mr-2 -ml-1 w-5 h-5"
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
              {{ t("create_store.back") }}
            </button>
            <button
              @click="currentStep = 3"
              :disabled="!canProceedFromStep2"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              {{ t("create_store.next_step") }}
              <svg
                class="ml-2 -mr-1 w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M14 5l7 7m0 0l-7 7m7-7H3"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Step 3: Confirmation -->
        <div v-if="currentStep === 3" class="space-y-6">
          <div class="bg-gray-900/50 p-6 rounded-2xl border border-gray-700">
            <h3
              class="font-medium text-white mb-6 text-lg border-b border-gray-700 pb-2"
            >
              {{ t("create_store.review_store_settings") }}
            </h3>
            <dl class="space-y-4">
              <div class="flex justify-between items-center">
                <dt class="text-sm text-gray-400">
                  {{ t("create_store.store_name_label") }}
                </dt>
                <dd class="text-sm font-bold text-white">{{ form.name }}</dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-sm text-gray-400">
                  {{ t("create_store.default_currency_label") }}
                </dt>
                <dd
                  class="text-sm font-medium text-white bg-gray-800 px-2 py-1 rounded border border-gray-600"
                >
                  {{ form.default_currency }}
                </dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-sm text-gray-400">
                  {{ t("create_store.timezone_label") }}
                </dt>
                <dd class="text-sm font-medium text-white">
                  {{ form.timezone }}
                </dd>
              </div>
              <div
                class="flex justify-between items-center"
                v-if="form.preferred_exchange"
              >
                <dt class="text-sm text-gray-400">
                  {{ t("create_store.price_source_label") }}
                </dt>
                <dd class="text-sm font-medium text-white">
                  {{
                    exchanges.find((e) => e.value === form.preferred_exchange)
                      ?.label || form.preferred_exchange
                  }}
                </dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-sm text-gray-400">
                  {{ t("create_store.wallet_type_label") }}
                </dt>
                <dd class="text-sm font-bold text-indigo-300">
                  {{
                    form.wallet_type === "blink"
                      ? t("create_store.wallet_type_blink")
                      : form.wallet_type === "aqua_boltz"
                        ? t("create_store.wallet_type_aqua")
                        : t("create_store.wallet_type_cashu")
                  }}
                </dd>
              </div>
              <div
                class="flex justify-between items-center gap-4"
                v-if="
                  form.wallet_type === 'cashu'
                    ? form.mint_url && form.lightning_address
                    : form.wallet_type === 'aqua_boltz'
                      ? true
                      : !!form.connection_string?.trim()
                "
              >
                <dt class="text-sm text-gray-400 shrink-0">
                  {{
                    form.wallet_type === "cashu"
                      ? t("create_store.cashu_connection_summary")
                      : t("create_store.connection_label")
                  }}
                </dt>
                <dd class="text-sm font-medium text-white flex items-center justify-end text-right">
                  <template
                    v-if="form.wallet_type === 'aqua_boltz' && aquaSetupTab === 'samrock'"
                  >
                    <span class="text-indigo-300">{{
                      t("create_store.aqua_summary_samrock")
                    }}</span>
                  </template>
                  <template
                    v-else-if="form.wallet_type === 'aqua_boltz' && !form.connection_string?.trim()"
                  >
                    <span class="text-indigo-300">{{
                      t("create_store.aqua_configure_later")
                    }}</span>
                  </template>
                  <span v-else class="flex items-center text-green-400">
                    <svg
                      class="w-4 h-4 mr-1.5 shrink-0"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                    {{ t("create_store.configured") }}
                  </span>
                </dd>
              </div>
            </dl>
          </div>

          <div class="flex justify-between pt-4">
            <button
              @click="currentStep = 2"
              :disabled="loading"
              class="inline-flex justify-center py-3 px-6 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 transition-all"
            >
              <svg
                class="mr-2 -ml-1 w-5 h-5"
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
              {{ t("create_store.back") }}
            </button>
            <button
              @click="handleSubmit"
              :disabled="loading"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-green-600 hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-green-600/20"
            >
              <svg
                v-if="loading"
                class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
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
              {{
                loading
                  ? t("create_store.creating")
                  : t("create_store.create_store")
              }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Upgrade modal when store limit reached -->
    <UpgradeModal
      v-if="storeLimitReached"
      :show="showUpgradeModal"
      :message="
        t('stores.store_limit_reached', { max: limits?.stores?.max ?? 1 })
      "
      :limits="
        limits?.stores
          ? [
              {
                feature: 'stores',
                current: limits.stores.current,
                max: limits.stores.max,
              },
            ]
          : []
      "
      recommended-plan="pro"
      :upgrade-button-text="t('stores.upgrade_to_pro')"
      @close="showUpgradeModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useStoresStore } from "../../store/stores";
import { useFlashStore } from "../../store/flash";
import { useAccountLimits } from "../../composables/useAccountLimits";
import { currencies } from "../../data/currencies";
import { exchanges } from "../../data/exchanges";
import Select from "../../components/ui/Select.vue";
import UpgradeModal from "../../components/stores/UpgradeModal.vue";

const { t } = useI18n();

/** Example public mint; replace with your own mint in production. */
const DEFAULT_CASHU_MINT_URL = "https://mint.minibits.cash/Bitcoin";

const router = useRouter();
const storesStore = useStoresStore();
const { limits, load: loadLimits } = useAccountLimits();

const currentStep = ref(1);
const loading = ref(false);
const flashStore = useFlashStore();
const storeLimitReached = ref(false);
const showUpgradeModal = ref(false);

/** Aqua step 2: primary SamRock tab vs secondary manual descriptor tab */
const aquaSetupTab = ref<"samrock" | "descriptor">("samrock");

const form = ref({
  name: "",
  default_currency: "EUR",
  timezone: "UTC",
  preferred_exchange: "",
  wallet_type: "" as "blink" | "aqua_boltz" | "cashu" | "",
  connection_string: "",
  // Cashu fields (default mint prefilled as a starting point)
  mint_url: DEFAULT_CASHU_MINT_URL,
  lightning_address: "",
});

const docBlinkPath = "/documentation/blink-wallet";
const docAquaPath = "/documentation/aqua-wallet";

// Validate Blink connection string: type=blink;server=...;api-key=...;wallet-id=... (all keys present, type=blink, non-empty values)
function validateBlinkConnectionString(s: string): boolean {
  const trimmed = s.trim();
  if (!trimmed) return false;
  if (!trimmed.includes(";")) return false;
  const parts = trimmed
    .split(";")
    .map((p) => p.trim())
    .filter(Boolean);
  let typeVal = "";
  let serverVal = "";
  let apiKeyVal = "";
  let walletIdVal = "";
  for (const part of parts) {
    const eq = part.indexOf("=");
    if (eq === -1) continue;
    const key = part.slice(0, eq).trim().toLowerCase();
    const value = part.slice(eq + 1).trim();
    if (key === "type") typeVal = value;
    if (key === "server") serverVal = value;
    if (key === "api-key" || key === "apikey") apiKeyVal = value;
    if (key === "wallet-id" || key === "walletid") walletIdVal = value;
  }
  return typeVal === "blink" && !!serverVal && !!apiKeyVal && !!walletIdVal;
}

// Validate Aqua descriptor: must be format ct(slip77(...),elsh(wpkh(...)))
function validateDescriptor(s: string): boolean {
  const trimmed = s.trim();
  if (!trimmed) return false;
  const lower = trimmed.toLowerCase();
  return lower.startsWith("ct(slip77") && lower.includes(",elsh(wpkh(");
}

const canProceedFromStep2 = computed(() => {
  const wt = form.value.wallet_type;
  if (!wt) return false;

  if (wt === "blink") {
    const cs = form.value.connection_string?.trim() ?? "";
    return validateBlinkConnectionString(cs);
  }

  if (wt === "aqua_boltz") {
    if (aquaSetupTab.value === "samrock") {
      return true;
    }
    const cs = form.value.connection_string?.trim() ?? "";

    return validateDescriptor(cs);
  }

  if (wt === "cashu") {
    const mintUrl = (form.value.mint_url ?? "").trim();
    const lnAddress = (form.value.lightning_address ?? "").trim();
    if (!mintUrl || !mintUrl.startsWith("https://")) return false;
    if (!lnAddress.match(/^[^@]+@[^@]+$/)) return false;
    return true;
  }

  return false;
});

watch(
  () => form.value.wallet_type,
  (wt) => {
    if (wt === "cashu" && !(form.value.mint_url ?? "").trim()) {
      form.value.mint_url = DEFAULT_CASHU_MINT_URL;
    }
    if (wt === "aqua_boltz") {
      aquaSetupTab.value = "samrock";
    }
  }
);

// Common timezones - you can expand this list
const timezones = [
  "UTC",
  "America/New_York",
  "America/Chicago",
  "America/Denver",
  "America/Los_Angeles",
  "America/Phoenix",
  "America/Toronto",
  "America/Vancouver",
  "America/Sao_Paulo",
  "America/Argentina/Buenos_Aires",
  "America/Santiago",
  "America/Mexico_City",
  "Europe/London",
  "Europe/Paris",
  "Europe/Berlin",
  "Europe/Rome",
  "Europe/Madrid",
  "Europe/Amsterdam",
  "Europe/Brussels",
  "Europe/Vienna",
  "Europe/Prague",
  "Europe/Warsaw",
  "Europe/Stockholm",
  "Europe/Copenhagen",
  "Europe/Helsinki",
  "Europe/Athens",
  "Europe/Istanbul",
  "Europe/Moscow",
  "Europe/Kiev",
  "Asia/Dubai",
  "Asia/Tokyo",
  "Asia/Shanghai",
  "Asia/Hong_Kong",
  "Asia/Singapore",
  "Asia/Seoul",
  "Asia/Bangkok",
  "Asia/Jakarta",
  "Asia/Manila",
  "Asia/Kolkata",
  "Asia/Karachi",
  "Asia/Dhaka",
  "Asia/Tehran",
  "Asia/Jerusalem",
  "Australia/Sydney",
  "Australia/Melbourne",
  "Australia/Brisbane",
  "Australia/Perth",
  "Pacific/Auckland",
  "Pacific/Honolulu",
];

const timezoneOptions = timezones.map((tz) => ({ label: tz, value: tz }));

onMounted(async () => {
  await loadLimits();
  const s = limits.value?.stores;
  if (s && !s.unlimited && s.max != null && s.current >= s.max) {
    storeLimitReached.value = true;
    showUpgradeModal.value = true;
  }
});

async function handleSubmit() {
  loading.value = true;
  flashStore.clear();
  try {
    const payload: any = {
      name: form.value.name,
      default_currency: form.value.default_currency,
      timezone: form.value.timezone,
      preferred_exchange: form.value.preferred_exchange || undefined,
      wallet_type: form.value.wallet_type,
    };

    if (form.value.wallet_type === 'cashu') {
      payload.mint_url = form.value.mint_url;
      payload.lightning_address = form.value.lightning_address;
    } else {
      payload.connection_string = form.value.connection_string;
    }

    const store = await storesStore.createStore(payload);
    if (form.value.wallet_type === "aqua_boltz" && aquaSetupTab.value === "samrock") {
      router.push({
        name: "stores-wallet-connection",
        params: { id: store.id },
        query: { samrock: "1", setup: "1" },
      });
    } else {
      router.push(`/stores/${store.id}?setup=1`);
    }
  } catch (err: any) {
    const msg =
      err.response?.data?.message || t("create_store.failed_to_create");
    const errors = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()
      : [];
    flashStore.error(errors.length ? errors.join(", ") : msg);
  } finally {
    loading.value = false;
  }
}
</script>

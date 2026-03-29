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
            :style="{ width: ((Math.min(currentStep, 3) - 1) / 2) * 100 + '%' }"
          ></div>

          <div
            class="flex flex-col items-center relative z-10 group cursor-pointer"
            @click="currentStep > 1 && currentStep < 3 ? (currentStep = 1) : null"
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
            @click="currentStep === 3 ? (currentStep = 2) : currentStep > 2 ? (currentStep = 2) : null"
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
              class="mt-2 text-xs font-semibold uppercase tracking-wider transition-colors bg-gray-800 px-2 rounded text-center max-w-[8rem]"
              :class="currentStep >= 3 ? 'text-indigo-400' : 'text-gray-500'"
              >{{ t("create_store.step_provisioning") }}</span
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
              type="button"
              @click="submitStep1Create"
              :disabled="!form.name || !form.default_currency || !form.timezone || loading"
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
                <div class="flex items-center justify-between mb-2 gap-2">
                  <span class="font-bold text-white text-lg inline-flex items-center gap-2 flex-wrap">
                    {{ t("create_store.wallet_type_cashu") }}
                    <span
                      class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 border border-amber-500/40"
                    >{{ t("stores.cashu_beta_badge") }}</span>
                  </span>
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
              class="mt-4 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 space-y-4"
            >
              <p class="text-sm text-amber-200/95 leading-relaxed">
                {{ t("stores.cashu_beta_notice_short") }}
              </p>
              <p class="text-sm font-medium text-amber-300">
                {{ t("stores.cashu_warning_title") }}
              </p>
              <ul class="text-sm text-amber-400 list-disc list-inside space-y-1.5">
                <li>{{ t("stores.cashu_warning_https") }}</li>
                <li>{{ t("stores.cashu_warning_mint_reachable") }}</li>
                <li>{{ t("stores.cashu_warning_ln_address") }}</li>
              </ul>
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input
                  v-model="form.cashu_beta_accepted"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm text-amber-100 leading-relaxed">{{ t("stores.cashu_beta_consent_checkbox") }}</span>
              </label>
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
                    <p class="mt-2 text-sm text-gray-500 leading-relaxed">
                      <span>{{ t("stores.cashu_lightning_address_coinos_prefix") }}</span>
                      <a
                        href="https://coinos.io"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-400 hover:text-indigo-300 underline font-medium"
                      >coinos.io</a>
                    </p>
                  </div>
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

                <div
                  v-show="aquaSetupTab === 'samrock'"
                  class="bg-gray-900/50 border border-indigo-500/30 rounded-2xl p-6 space-y-4"
                >
                  <h3 class="text-sm font-bold text-indigo-400 uppercase tracking-wider">
                    {{ t("stores.samrock_title") }}
                  </h3>
                  <p class="text-sm text-gray-400 leading-relaxed">{{ t("stores.samrock_description") }}</p>
                  <p v-if="samrockErrorMessage && !samrockQrObjectUrl" class="text-red-400 text-sm">
                    {{ samrockErrorMessage }}
                  </p>

                  <div v-if="!samrockOtp && !samrockBusy" class="flex flex-wrap gap-3">
                    <button
                      type="button"
                      class="px-6 py-3 rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
                      :disabled="samrockBusy || !createdStoreId"
                      @click="startSamRockPairing"
                    >
                      {{ t("stores.samrock_generate_qr") }}
                    </button>
                    <button
                      v-if="samrockErrorMessage"
                      type="button"
                      class="px-6 py-3 rounded-xl text-sm font-medium border border-gray-600 text-gray-300 hover:bg-gray-800"
                      @click="samrockErrorMessage = ''; startSamRockPairing()"
                    >
                      {{ t("stores.samrock_try_again") }}
                    </button>
                  </div>

                  <div v-else-if="samrockBusy && !samrockQrObjectUrl" class="flex items-center gap-3 py-6 text-gray-400">
                    <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>{{ t("common.loading") }}</span>
                  </div>

                  <div v-else-if="samrockQrObjectUrl" class="space-y-4">
                    <p class="text-sm text-gray-300">{{ t("stores.samrock_waiting_scan") }}</p>
                    <div class="flex flex-col sm:flex-row gap-6 items-start">
                      <img
                        :src="samrockQrObjectUrl"
                        alt="SamRock QR"
                        class="w-48 h-48 rounded-xl border border-gray-600 bg-white p-2"
                      />
                      <div class="text-sm space-y-2">
                        <p v-if="samrockExpiresAt" class="text-gray-500">
                          {{ t("stores.samrock_expires") }}: {{ formatSamRockExpiry(samrockExpiresAt) }}
                        </p>
                        <p v-if="samrockPollStatus" class="font-mono text-indigo-300">{{ samrockPollStatus }}</p>
                        <p v-if="samrockErrorMessage" class="text-red-400">{{ samrockErrorMessage }}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="px-4 py-2 rounded-xl text-sm border border-gray-600 text-gray-300 hover:bg-gray-800"
                      @click="cancelSamRockPairing"
                    >
                      {{ t("stores.samrock_cancel") }}
                    </button>
                  </div>

                  <div
                    v-if="samrockWalletReady"
                    class="rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-sm text-green-300"
                  >
                    {{ t("stores.samrock_pairing_complete") }}
                  </div>
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

          <p v-if="createdStoreId" class="text-xs text-gray-500 pt-2">
            {{ t("create_store.store_created_hint") }}
          </p>

          <div class="flex justify-between pt-4">
            <button
              type="button"
              @click="goBackFromStep2"
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

            <template
              v-if="form.wallet_type === 'aqua_boltz' && aquaSetupTab === 'samrock'"
            >
              <button
                type="button"
                @click="finishSamrockAndGoToStore"
                :disabled="!samrockWalletReady || loading"
                class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-green-600 hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
              >
                {{ t("create_store.continue_to_store") }}
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                @click="submitWalletConfiguration"
                :disabled="!canProceedFromStep2 || loading"
                class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
              >
                <svg
                  v-if="loading"
                  class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ t("create_store.continue_setup") }}
              </button>
            </template>
          </div>
        </div>

        <!-- Step 3: Bot provisioning (Blink / Aqua descriptor) -->
        <div v-if="currentStep === 3" class="space-y-6">
          <div class="bg-gray-900/50 p-6 rounded-2xl border border-gray-700 text-center">
            <h3 class="text-lg font-semibold text-white mb-2">
              {{ t("create_store.bot_wait_title") }}
            </h3>
            <p class="text-sm text-gray-400 mb-6">
              {{ t("create_store.bot_wait_hint") }}
            </p>

            <div v-if="botWaitPhase === 'polling'" class="space-y-6">
              <div class="flex justify-center">
                <svg class="animate-spin h-12 w-12 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
              <p class="text-2xl font-mono text-indigo-300">{{ botWaitSecondsRemaining }}s</p>
              <p class="text-xs text-gray-500">{{ t("create_store.bot_wait_polling") }}</p>
            </div>

            <div v-else-if="botWaitPhase === 'success'" class="space-y-4">
              <p class="text-green-400 font-medium">{{ t("create_store.bot_result_success") }}</p>
              <button
                type="button"
                @click="goToStoreAfterBot"
                class="inline-flex justify-center py-3 px-8 border border-transparent rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
              >
                {{ t("create_store.continue_to_store") }}
              </button>
            </div>

            <div v-else class="space-y-4">
              <p class="text-amber-400">{{ botWaitErrorMessage }}</p>
              <p v-if="botWaitFailureDetail" class="text-sm text-gray-400">{{ botWaitFailureDetail }}</p>
              <button
                type="button"
                @click="goToStoreAfterBot"
                class="inline-flex justify-center py-3 px-8 border border-transparent rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
              >
                {{ t("create_store.continue_to_store") }}
              </button>
            </div>
          </div>

          <div class="flex justify-start">
            <button
              type="button"
              v-if="botWaitPhase === 'polling'"
              @click="cancelBotWait"
              class="text-sm text-gray-400 hover:text-white"
            >
              {{ t("common.cancel") }}
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
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useStoresStore } from "../../store/stores";
import { useFlashStore } from "../../store/flash";
import { useAccountLimits } from "../../composables/useAccountLimits";
import { currencies } from "../../data/currencies";
import { exchanges } from "../../data/exchanges";
import Select from "../../components/ui/Select.vue";
import UpgradeModal from "../../components/stores/UpgradeModal.vue";
import api from "../../services/api";
import { DEFAULT_CASHU_MINT_URL } from "../../constants/cashu";

const { t } = useI18n();

const router = useRouter();
const storesStore = useStoresStore();
const { limits, load: loadLimits } = useAccountLimits();

const currentStep = ref(1);
const loading = ref(false);
const flashStore = useFlashStore();
const storeLimitReached = ref(false);
const showUpgradeModal = ref(false);

/** Set after step 1 POST /stores (wallet optional). */
const createdStoreId = ref<string | null>(null);

/** Step 3: Blink / Aqua descriptor bot wait */
const BOT_WAIT_TOTAL_SECONDS = 120;
const BOT_POLL_MS = 15000;
const botWaitPhase = ref<"polling" | "success" | "error">("polling");
const botWaitSecondsRemaining = ref(BOT_WAIT_TOTAL_SECONDS);
const botWaitErrorMessage = ref("");
const botWaitFailureDetail = ref("");
let botWaitCountdownInterval: ReturnType<typeof setInterval> | null = null;
let botPollInterval: ReturnType<typeof setInterval> | null = null;

/** Aqua step 2: primary SamRock tab vs secondary manual descriptor tab */
const aquaSetupTab = ref<"samrock" | "descriptor">("samrock");

/** SamRock pairing (step 2, Aqua tab) */
const samrockOtp = ref("");
const samrockExpiresAt = ref<string | null>(null);
const samrockQrObjectUrl = ref<string | null>(null);
const samrockBusy = ref(false);
const samrockPollStatus = ref("");
const samrockErrorMessage = ref("");
const samrockWalletReady = ref(false);
let samrockPollInterval: ReturnType<typeof setInterval> | null = null;

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
  cashu_beta_accepted: false,
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
      return false;
    }
    const cs = form.value.connection_string?.trim() ?? "";

    return validateDescriptor(cs);
  }

  if (wt === "cashu") {
    const mintUrl = (form.value.mint_url ?? "").trim();
    const lnAddress = (form.value.lightning_address ?? "").trim();
    if (!mintUrl || !mintUrl.startsWith("https://")) return false;
    if (!lnAddress.match(/^[^@]+@[^@]+$/)) return false;
    if (!form.value.cashu_beta_accepted) return false;
    return true;
  }

  return false;
});

watch(
  () => form.value.wallet_type,
  (wt) => {
    if (wt !== "cashu") {
      form.value.cashu_beta_accepted = false;
    }
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

function revokeSamRockQr() {
  if (samrockQrObjectUrl.value) {
    URL.revokeObjectURL(samrockQrObjectUrl.value);
    samrockQrObjectUrl.value = null;
  }
}

function formatSamRockExpiry(iso: string) {
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function stopSamRockPolling() {
  if (samrockPollInterval != null) {
    window.clearInterval(samrockPollInterval);
    samrockPollInterval = null;
  }
}

async function cancelSamRockPairing() {
  stopSamRockPolling();
  revokeSamRockQr();
  const sid = createdStoreId.value;
  if (samrockOtp.value && sid) {
    try {
      await api.delete(
        `/stores/${sid}/samrock/otps/${encodeURIComponent(samrockOtp.value)}`
      );
    } catch {
      /* ignore */
    }
  }
  samrockOtp.value = "";
  samrockExpiresAt.value = null;
  samrockErrorMessage.value = "";
  samrockPollStatus.value = "";
  samrockBusy.value = false;
}

async function startSamRockPairing() {
  const sid = createdStoreId.value;
  if (!sid) return;

  try {
    await api.patch(`/stores/${sid}/wallet-type`, {
      wallet_type: "aqua_boltz",
    });
    await storesStore.fetchStore(sid);
  } catch (err: any) {
    samrockErrorMessage.value =
      err.response?.data?.message ?? t("stores.samrock_error");
    return;
  }

  samrockBusy.value = true;
  samrockErrorMessage.value = "";
  samrockWalletReady.value = false;
  revokeSamRockQr();
  samrockOtp.value = "";
  stopSamRockPolling();

  try {
    const res = await api.post(`/stores/${sid}/samrock/otps`, {
      btc: true,
      btcln: true,
      lbtc: false,
      expires_in_seconds: 300,
    });
    const d = res.data?.data ?? {};
    const otp = d.otp ?? "";
    if (!otp) {
      samrockErrorMessage.value = t("stores.samrock_error");
      samrockBusy.value = false;
      return;
    }
    samrockOtp.value = otp;
    samrockExpiresAt.value = d.expires_at ?? null;

    const qrRes = await api.get(
      `/stores/${sid}/samrock/otps/${encodeURIComponent(otp)}/qr`,
      {
        responseType: "blob",
        params: { format: "png" },
      }
    );
    samrockQrObjectUrl.value = URL.createObjectURL(qrRes.data);
    samrockBusy.value = false;
    samrockPollStatus.value = "pending";

    const pollOnce = async () => {
      if (!samrockOtp.value || !sid) return;
      try {
        const st = await api.get(
          `/stores/${sid}/samrock/otps/${encodeURIComponent(samrockOtp.value)}`
        );
        const status = st.data?.data?.status ?? "";
        samrockPollStatus.value = status;
        if (status === "success") {
          stopSamRockPolling();
          await api.post(`/stores/${sid}/samrock/complete`, {
            otp: samrockOtp.value,
          });
          revokeSamRockQr();
          samrockOtp.value = "";
          samrockWalletReady.value = true;
          await storesStore.fetchStore(sid);
        } else if (status === "error") {
          stopSamRockPolling();
          samrockErrorMessage.value =
            st.data?.data?.error_message ?? t("stores.samrock_error");
        }
      } catch {
        /* ignore */
      }
    };

    await pollOnce();
    samrockPollInterval = window.setInterval(pollOnce, 3000);
  } catch (err: any) {
    samrockErrorMessage.value =
      err.response?.data?.message ?? t("stores.samrock_error");
    samrockBusy.value = false;
  }
}

function stopBotWaitTimers() {
  if (botWaitCountdownInterval != null) {
    clearInterval(botWaitCountdownInterval);
    botWaitCountdownInterval = null;
  }
  if (botPollInterval != null) {
    clearInterval(botPollInterval);
    botPollInterval = null;
  }
}

async function pollBotWalletConnectionOnce() {
  const sid = createdStoreId.value;
  if (!sid) return;
  try {
    const res = await api.get(`/stores/${sid}/wallet-connection`);
    const data = res.data?.data;
    const status = data?.status ?? "";
    if (status === "connected") {
      stopBotWaitTimers();
      botWaitPhase.value = "success";
      await storesStore.fetchStore(sid);
    } else if (status === "needs_support") {
      stopBotWaitTimers();
      botWaitPhase.value = "error";
      botWaitErrorMessage.value = t("create_store.bot_result_support");
      botWaitFailureDetail.value = data?.bot_failure_message ?? "";
    }
  } catch {
    /* ignore */
  }
}

function startBotWaitSequence() {
  botWaitPhase.value = "polling";
  botWaitErrorMessage.value = "";
  botWaitFailureDetail.value = "";
  botWaitSecondsRemaining.value = BOT_WAIT_TOTAL_SECONDS;
  stopBotWaitTimers();

  void pollBotWalletConnectionOnce();
  botPollInterval = setInterval(() => {
    void pollBotWalletConnectionOnce();
  }, BOT_POLL_MS);

  botWaitCountdownInterval = setInterval(() => {
    botWaitSecondsRemaining.value -= 1;
    if (botWaitSecondsRemaining.value <= 0) {
      if (botWaitCountdownInterval != null) {
        clearInterval(botWaitCountdownInterval);
        botWaitCountdownInterval = null;
      }
      if (botPollInterval != null) {
        clearInterval(botPollInterval);
        botPollInterval = null;
      }
      if (botWaitPhase.value === "polling") {
        botWaitPhase.value = "error";
        botWaitErrorMessage.value = t("create_store.bot_result_timeout");
        botWaitFailureDetail.value = "";
      }
    }
  }, 1000);
}

function cancelBotWait() {
  stopBotWaitTimers();
  currentStep.value = 2;
  botWaitPhase.value = "polling";
}

async function submitStep1Create() {
  loading.value = true;
  flashStore.clear();
  try {
    const store = await storesStore.createStore({
      name: form.value.name,
      default_currency: form.value.default_currency,
      timezone: form.value.timezone,
      preferred_exchange: form.value.preferred_exchange || undefined,
    });
    createdStoreId.value = store.id;
    currentStep.value = 2;
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

function goBackFromStep2() {
  currentStep.value = 1;
}

async function submitWalletConfiguration() {
  const sid = createdStoreId.value;
  if (!sid) {
    flashStore.error(t("create_store.failed_to_create"));
    return;
  }

  loading.value = true;
  flashStore.clear();

  try {
    if (form.value.wallet_type === "cashu") {
      await api.put(`/stores/${sid}/cashu/settings`, {
        mint_url: form.value.mint_url,
        lightning_address: form.value.lightning_address,
        enabled: true,
      });
      await storesStore.fetchStore(sid);
      await router.push({ name: "stores-show", params: { id: sid }, query: { setup: "1" } });
      return;
    }

    const secret = (form.value.connection_string ?? "").trim();
    const connType =
      form.value.wallet_type === "blink" ? "blink" : "aqua_descriptor";

    await api.post(`/stores/${sid}/wallet-connection`, {
      type: connType,
      secret,
    });

    await storesStore.fetchStore(sid);
    currentStep.value = 3;
    startBotWaitSequence();
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

function goToStoreAfterBot() {
  const sid = createdStoreId.value;
  if (!sid) return;
  router.push({ name: "stores-show", params: { id: sid }, query: { setup: "1" } });
}

function finishSamrockAndGoToStore() {
  const sid = createdStoreId.value;
  if (!sid || !samrockWalletReady.value) return;
  router.push({ name: "stores-show", params: { id: sid }, query: { setup: "1" } });
}

onMounted(async () => {
  await loadLimits();
  const s = limits.value?.stores;
  if (s && !s.unlimited && s.max != null && s.current >= s.max) {
    storeLimitReached.value = true;
    showUpgradeModal.value = true;
  }
});

onUnmounted(() => {
  stopSamRockPolling();
  stopBotWaitTimers();
  revokeSamRockQr();
});
</script>

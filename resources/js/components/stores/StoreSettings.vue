<template>
  <div v-if="loading && !settings" class="text-center py-12">
    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 mt-4">{{ t('common.loading') }}</p>
  </div>

  <div v-else-if="settings" class="space-y-6">
    <!-- Main card with tabs -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50">
        <h1 class="text-2xl font-bold text-white">{{ t('stores.store_settings') }}</h1>
        <p class="text-sm text-gray-400 mt-1">{{ t('stores.manage_store_config') }}</p>
      </div>

      <!-- Tabs -->
      <nav class="flex border-b border-gray-700 overflow-x-auto" aria-label="Store settings tabs">
        <button
          v-for="tab in settingsTabs"
          :key="tab.id"
          type="button"
          @click="activeSettingsTab = tab.id"
          :class="[
            'px-5 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
            activeSettingsTab === tab.id
              ? 'border-indigo-500 text-indigo-400'
              : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-600',
          ]"
        >
          {{ t(tab.labelKey) }}
        </button>
      </nav>

      <!-- Tabs: Settings, Rates, Checkout (one form) -->
      <div v-show="['settings', 'payment', 'rates', 'checkout'].includes(activeSettingsTab)" class="px-6 py-8">
        <form @submit.prevent="handleSettingsSubmit" class="space-y-8">
          <!-- Tab: Settings -->
          <div v-show="activeSettingsTab === 'settings'" class="space-y-8">
          <!-- Store identification -->
          <div class="space-y-4">
            <h2 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">Store identification</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="md:col-span-2 space-y-4">
                <div v-if="settings?.id" class="grid grid-cols-1 gap-1">
                  <label class="block text-sm font-medium text-gray-400">Store Id</label>
                  <input
                    type="text"
                    :value="settings.id"
                    readonly
                    class="block w-full px-4 py-3 border border-gray-600 rounded-xl bg-gray-800/50 text-gray-400 font-mono text-sm cursor-not-allowed"
                  />
                </div>
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.store_name') }}</label>
                  <input
                    id="name"
                    v-model="settingsForm.name"
                    type="text"
                    required
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                  />
                </div>
                <div>
                  <label for="website" class="block text-sm font-medium text-gray-300 mb-1">Store website</label>
                  <input
                    id="website"
                    v-model="settingsForm.website"
                    type="url"
                    placeholder="https://example.com"
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                  />
                </div>
              </div>
              <div class="md:col-span-1">
                <p class="text-sm font-medium text-gray-300 mb-3">{{ t('stores.store_logo') }}</p>
                <div class="flex flex-col items-center gap-4">
                  <div v-if="storeLogoUrl" class="relative group">
                    <div class="w-32 h-32 rounded-xl bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-600">
                      <img :src="storeLogoUrl" alt="Store logo" class="w-full h-full object-contain" />
                    </div>
                    <button
                      type="button"
                      @click="handleDeleteLogo"
                      :disabled="deletingLogo"
                      class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg hover:bg-red-600 transition-colors"
                      :title="t('stores.delete_logo')"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                  <div v-else class="w-32 h-32 rounded-xl bg-gray-700/50 flex items-center justify-center border-2 border-dashed border-gray-600 text-gray-500">
                    <span class="text-sm">{{ t('stores.no_logo') }}</span>
                  </div>
                  <div class="w-full">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                      {{ storeLogoUrl ? t('stores.update_logo') : t('stores.upload_logo') }}
                    </label>
                    <input
                      ref="logoInputRef"
                      type="file"
                      accept="image/*"
                      @change="handleLogoUpload"
                      class="block w-full text-sm text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer bg-gray-700/50 rounded-lg border border-gray-600 focus:outline-none"
                    />
                    <p class="mt-2 text-xs text-gray-500">PNG, JPG, GIF. Max 2MB.</p>
                  </div>
                </div>
                <div v-if="logoError" class="mt-4 rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                  <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div class="text-sm text-red-400">{{ logoError }}</div>
                  </div>
                </div>
                <div v-if="logoSuccess" class="mt-4 rounded-xl bg-green-500/10 border border-green-500/20 p-4">
                  <div class="flex">
                    <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <div class="text-sm text-green-400">{{ logoSuccess }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Branding (Pro+) -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">Branding</h2>
              <button
                v-if="!canEditBranding"
                type="button"
                @click="showBrandingProNotice = !showBrandingProNotice"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
              >
                {{ t('stores.available_in_pro') }}
                <svg
                  class="w-3.5 h-3.5 transition-transform duration-200"
                  :class="{ 'rotate-180': showBrandingProNotice }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                v-if="!canEditBranding && showBrandingProNotice"
                class="mb-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-sm text-amber-200"
              >
                <p class="mb-2">{{ t('stores.pos_advanced_options_pro_only') }}</p>
                <a
                  :href="'/account'"
                  class="inline-flex items-center font-medium text-amber-300 hover:text-amber-200 underline underline-offset-2"
                >
                  {{ t('stores.upgrade_to_pro') }}
                </a>
              </div>
            </Transition>
            <p class="text-sm text-gray-400">Custom color, logo, and CSS apply to public and customer-facing pages. Brand color is used as an accent.</p>
            <div
              :class="[
                'space-y-4',
                !canEditBranding && 'pointer-events-none opacity-75 select-none',
              ]"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="brand_color" class="block text-sm font-medium text-gray-300 mb-1">Brand color</label>
                  <div class="flex items-center gap-2">
                    <input
                      id="brand_color"
                      v-model="settingsForm.brand_color"
                      type="color"
                      :disabled="!canEditBranding"
                      class="h-10 w-14 rounded border border-gray-600 bg-gray-800 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                    />
                    <input
                      v-model="settingsForm.brand_color"
                      type="text"
                      :disabled="!canEditBranding"
                      class="flex-1 px-4 py-3 border border-gray-600 rounded-xl bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    />
                  </div>
                </div>
              </div>
              <div>
                <label for="css_url" class="block text-sm font-medium text-gray-300 mb-1">Custom CSS URL</label>
                <input
                  id="css_url"
                  v-model="settingsForm.css_url"
                  type="url"
                  placeholder="https://example.com/theme.css"
                  :disabled="!canEditBranding"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                />
              </div>
              <div>
                <label for="payment_sound_url" class="block text-sm font-medium text-gray-300 mb-1">Payment sound URL</label>
                <input
                  id="payment_sound_url"
                  v-model="settingsForm.payment_sound_url"
                  type="url"
                  placeholder="https://example.com/sound.mp3"
                  :disabled="!canEditBranding"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                />
              </div>
            </div>
          </div>
          </div>

          <!-- Tab: Payment -->
          <div v-show="activeSettingsTab === 'payment'" class="space-y-6">
            <h2 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">{{ t('stores.settings_tab_payment') }}</h2>
            <p class="text-sm text-gray-400 max-w-2xl">{{ t('stores.settings_tab_payment_desc') }}</p>
            <!-- Default Currency & Timezone: available to all -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.default_currency') }}</label>
                <input
                  id="default_currency"
                  v-model="settingsForm.default_currency"
                  type="text"
                  list="currency-selection-suggestion"
                  required
                  :placeholder="t('stores.currency_placeholder')"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
              </div>
              <div>
                <label for="timezone" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.timezone') }}</label>
                <Select
                  id="timezone"
                  v-model="settingsForm.timezone"
                  :options="timezoneOptions"
                  placeholder="Select timezone"
                />
              </div>
            </div>
            <!-- Payment options: Pro only -->
            <div class="space-y-4 pt-4 border-t border-gray-700">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base font-semibold text-white">Payment options</h3>
                <button
                  v-if="!canEditPaymentOptions"
                  type="button"
                  @click="showPaymentProNotice = !showPaymentProNotice"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
                >
                  {{ t('stores.available_in_pro') }}
                  <svg
                    class="w-3.5 h-3.5 transition-transform duration-200"
                    :class="{ 'rotate-180': showPaymentProNotice }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                  v-if="!canEditPaymentOptions && showPaymentProNotice"
                  class="mb-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-sm text-amber-200"
                >
                  <p class="mb-2">{{ t('stores.pos_advanced_options_pro_only') }}</p>
                  <a
                    :href="'/account'"
                    class="inline-flex items-center font-medium text-amber-300 hover:text-indigo-200 underline underline-offset-2"
                  >
                    {{ t('stores.upgrade_to_pro') }}
                  </a>
                </div>
              </Transition>
              <div
                :class="[
                  'space-y-6',
                  !canEditPaymentOptions && 'pointer-events-none opacity-75 select-none',
                ]"
              >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="network_fee_mode" class="block text-sm font-medium text-gray-300 mb-1">Add network fee to invoice</label>
                <Select
                  id="network_fee_mode"
                  v-model="settingsForm.network_fee_mode"
                  :options="networkFeeModeOptions"
                  placeholder="Select"
                />
              </div>
              <div>
                <label for="invoice_expiration" class="block text-sm font-medium text-gray-300 mb-1">Invoice expires after (minutes)</label>
                <input
                  id="invoice_expiration"
                  v-model.number="settingsForm.invoice_expiration"
                  type="number"
                  min="1"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="payment_tolerance" class="block text-sm font-medium text-gray-300 mb-1">Consider invoice paid if amount is … % less (percent)</label>
                <input
                  id="payment_tolerance"
                  v-model.number="settingsForm.payment_tolerance"
                  type="number"
                  min="0"
                  step="0.01"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
              <div>
                <label for="refund_bolt11_expiration" class="block text-sm font-medium text-gray-300 mb-1">Minimum BOLT11 expiration for refunds (days)</label>
                <input
                  id="refund_bolt11_expiration"
                  v-model.number="settingsForm.refund_bolt11_expiration"
                  type="number"
                  min="1"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="display_expiration_timer" class="block text-sm font-medium text-gray-300 mb-1">Display expiration timer (minutes)</label>
                <input
                  id="display_expiration_timer"
                  v-model.number="settingsForm.display_expiration_timer"
                  type="number"
                  min="0"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
              <div>
                <label for="monitoring_expiration" class="block text-sm font-medium text-gray-300 mb-1">Payment invalid if not confirmed after expiration (minutes)</label>
                <input
                  id="monitoring_expiration"
                  v-model.number="settingsForm.monitoring_expiration"
                  type="number"
                  min="0"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
            </div>
            <div>
              <label for="speed_policy" class="block text-sm font-medium text-gray-300 mb-1">Consider invoice settled when payment has</label>
              <Select
                id="speed_policy"
                v-model="settingsForm.speed_policy"
                :options="speedPolicyOptions"
                placeholder="Select"
              />
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-4 items-start">
              <label class="flex items-center">
                <input v-model="settingsForm.anyone_can_create_invoice" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Allow anyone to create invoice</span>
              </label>
              <div>
                <label class="flex items-center">
                  <input v-model="settingsForm.show_recommended_fee" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                  <span class="ml-2 text-sm text-gray-300">Show recommended fee</span>
                </label>
                <p v-if="settingsForm.show_recommended_fee" class="mt-1 ml-7 text-xs text-gray-500">Fee is shown for BTC and LTC on-chain payments only.</p>
              </div>
            </div>
            <div v-if="settingsForm.show_recommended_fee" class="max-w-xs">
              <label for="recommended_fee_block_target" class="block text-sm font-medium text-gray-300 mb-1">Recommended fee confirmation target (blocks)</label>
              <input
                id="recommended_fee_block_target"
                v-model.number="settingsForm.recommended_fee_block_target"
                type="number"
                min="1"
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
              />
            </div>
            <div>
              <label for="lightning_description_template" class="block text-sm font-medium text-gray-300 mb-1">Lightning description template</label>
              <input
                id="lightning_description_template"
                v-model="settingsForm.lightning_description_template"
                type="text"
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
              />
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-4">
              <label class="flex items-center">
                <input v-model="settingsForm.archived" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Archived</span>
              </label>
            </div>
              </div>
            </div>
          </div>

          <!-- Tab: Rates -->
          <div v-show="activeSettingsTab === 'rates'" class="space-y-6">
            <h2 class="text-lg font-semibold text-white">{{ t('stores.settings_tab_rates') }}</h2>
            <p class="text-sm text-gray-400 max-w-2xl">{{ t('stores.settings_tab_rates_desc') }}</p>
            <!-- Preferred Price Source: free for all -->
            <div>
              <label for="preferred_exchange" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.preferred_price_source') }}</label>
              <Select
                id="preferred_exchange"
                v-model="settingsForm.preferred_exchange"
                :options="exchanges"
                placeholder="Select preferred exchange"
              />
              <p class="mt-2 text-xs text-gray-500">{{ t('stores.recommended_price_source') }}</p>
            </div>
            <!-- Additional rates: Pro only -->
            <div class="space-y-4 pt-4 border-t border-gray-700">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base font-semibold text-white">Additional rates</h3>
                <button
                  v-if="!canEditRatesOptions"
                  type="button"
                  @click="showRatesProNotice = !showRatesProNotice"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
                >
                  {{ t('stores.available_in_pro') }}
                  <svg
                    class="w-3.5 h-3.5 transition-transform duration-200"
                    :class="{ 'rotate-180': showRatesProNotice }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                  v-if="!canEditRatesOptions && showRatesProNotice"
                  class="mb-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-sm text-amber-200"
                >
                  <p class="mb-2">{{ t('stores.pos_advanced_options_pro_only') }}</p>
                  <a
                    :href="'/account'"
                    class="inline-flex items-center font-medium text-amber-300 hover:text-amber-200 underline underline-offset-2"
                  >
                    {{ t('stores.upgrade_to_pro') }}
                  </a>
                </div>
              </Transition>
              <div
                :class="[
                  'space-y-4',
                  !canEditRatesOptions && 'pointer-events-none opacity-75 select-none',
                ]"
              >
                <div>
                  <label for="additional_tracked_rates" class="block text-sm font-medium text-gray-300 mb-1">Additional rates to track</label>
                  <input
                    id="additional_tracked_rates"
                    :value="(settingsForm.additional_tracked_rates || []).join(', ')"
                    type="text"
                    placeholder="USD, EUR, JPY"
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                    @input="onAdditionalRatesInput"
                  />
                  <p class="mt-1 text-xs text-gray-500">Comma-separated list of currencies (e.g. USD, EUR, JPY). Rates are recorded and available in reports.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Checkout appearance (Pro only) -->
          <div v-show="activeSettingsTab === 'checkout'" class="space-y-6">
            <h2 class="text-lg font-semibold text-white">{{ t('stores.settings_tab_checkout') }}</h2>
            <p class="text-sm text-gray-400 max-w-2xl">{{ t('stores.settings_tab_checkout_desc') }}</p>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-base font-medium text-white">Checkout options</span>
              <button
                v-if="!canEditCheckoutOptions"
                type="button"
                @click="showCheckoutProNotice = !showCheckoutProNotice"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
              >
                {{ t('stores.available_in_pro') }}
                <svg
                  class="w-3.5 h-3.5 transition-transform duration-200"
                  :class="{ 'rotate-180': showCheckoutProNotice }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                v-if="!canEditCheckoutOptions && showCheckoutProNotice"
                class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-sm text-amber-200"
              >
                <p class="mb-2">{{ t('stores.pos_advanced_options_pro_only') }}</p>
                <a
                  :href="'/account'"
                  class="inline-flex items-center font-medium text-amber-300 hover:text-amber-200 underline underline-offset-2"
                >
                  {{ t('stores.upgrade_to_pro') }}
                </a>
              </div>
            </Transition>
            <div
              :class="[
                'space-y-6',
                !canEditCheckoutOptions && 'pointer-events-none opacity-75 select-none',
              ]"
            >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="default_lang" class="block text-sm font-medium text-gray-300 mb-1">Default language</label>
                <input
                  id="default_lang"
                  v-model="settingsForm.default_lang"
                  type="text"
                  placeholder="en"
                  maxlength="10"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
              <div>
                <label for="html_title" class="block text-sm font-medium text-gray-300 mb-1">HTML title</label>
                <input
                  id="html_title"
                  v-model="settingsForm.html_title"
                  type="text"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
              </div>
            </div>
            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-300">Receipt</p>
              <label class="flex items-center">
                <input v-model="settingsForm.receipt.enabled" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Enable receipt</span>
              </label>
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-4 pt-2 border-t border-gray-700">
              <label class="flex items-center">
                <input v-model="settingsForm.lightning_amount_in_satoshi" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Lightning amount in satoshi</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.lightning_private_route_hints" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Lightning private route hints</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.on_chain_with_ln_invoice_fallback" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">On-chain with LN invoice fallback</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.redirect_automatically" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Redirect automatically</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.pay_join_enabled" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">PayJoin enabled</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.auto_detect_language" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Auto-detect language</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.show_pay_in_wallet_button" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Show pay in wallet button</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.show_store_header" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Show store header</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.celebrate_payment" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Celebrate payment</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.play_sound_on_payment" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Play sound on payment</span>
              </label>
              <label class="flex items-center">
                <input v-model="settingsForm.lazy_payment_methods" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
                <span class="ml-2 text-sm text-gray-300">Lazy payment methods</span>
              </label>
            </div>
            <div class="max-w-xs">
              <label for="default_payment_method" class="block text-sm font-medium text-gray-300 mb-1">Default payment method</label>
              <input
                id="default_payment_method"
                v-model="settingsForm.default_payment_method"
                type="text"
                placeholder="BTC-CHAIN"
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
              />
            </div>
            </div>
          </div>

          <template v-if="['settings', 'payment', 'rates', 'checkout'].includes(activeSettingsTab)">
          <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
             <div class="flex">
                <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-sm text-red-400">{{ error }}</div>
             </div>
          </div>

          <div v-if="success" class="rounded-xl bg-green-500/10 border border-green-500/20 p-4">
             <div class="flex">
                <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <div class="text-sm text-green-400">{{ success }}</div>
             </div>
          </div>

          <div class="flex justify-end pt-4">
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-indigo-600/20"
            >
              <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>

              </svg>
              {{ saving ? t('auth.saving') : t('stores.save_changes') }}
            </button>
          </div>
          </template>
        </form>
      </div>
    </div>

    <!-- Danger Zone (shown under every tab) -->
    <div class="bg-red-900/10 shadow-xl rounded-2xl border border-red-900/30 overflow-hidden">
      <div class="px-6 py-5 border-b border-red-900/20 bg-red-900/20">
        <h2 class="text-lg font-bold text-red-400">Danger Zone</h2>
      </div>
      <div class="px-6 py-6">
        <h3 class="text-sm font-bold text-white mb-2">Delete Store</h3>
        <p class="text-sm text-gray-400 mb-6 max-w-xl">
          This will permanently delete the store <strong>{{ store?.name }}</strong> and all its data, including invoices, apps, and settings. This action cannot be undone.
        </p>
        <button
          type="button"
          @click="showDeleteStoreModal = true"
          class="inline-flex items-center px-4 py-2 border border-red-500/50 text-sm font-medium rounded-lg text-red-400 bg-red-500/10 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 focus:ring-offset-gray-900 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          Delete Store
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Store Confirmation Modal -->
  <div
    v-if="showDeleteStoreModal"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
     <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showDeleteStoreModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
           <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                 <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                 </div>
                 <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Delete Store</h3>
                    <div class="mt-2">
                       <p class="text-sm text-gray-400">
                          Are you sure you want to delete <strong>{{ store?.name }}</strong>? This action cannot be undone.
                       </p>
                       <p class="text-sm text-gray-400 mt-2">
                          Please type <span class="font-mono text-red-400 bg-red-900/20 px-1 rounded">DELETE</span> to confirm:
                       </p>
                       <input
                          v-model="deleteStoreConfirmText"
                          type="text"
                          class="mt-3 block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm bg-gray-700 text-white placeholder-gray-500"
                          placeholder="Type DELETE"
                           @keyup.enter="handleDeleteStore"
                       />
                       <p v-if="deleteStoreError" class="mt-2 text-sm text-red-500">{{ deleteStoreError }}</p>
                    </div>
                 </div>
              </div>
           </div>
           <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50">
              <button
                 type="button"
                 @click="handleDeleteStore"
                 :disabled="deletingStore || deleteStoreConfirmText !== 'DELETE'"
                 class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                 {{ deletingStore ? 'Deleting...' : 'Delete Store' }}
              </button>
              <button
                 type="button"
                 @click="showDeleteStoreModal = false; deleteStoreConfirmText = ''"
                 class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                 Cancel
              </button>
           </div>
        </div>
     </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import { useStoresStore } from '../../store/stores';
import api from '../../services/api';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';
import Select from '../ui/Select.vue';

const { t } = useI18n();

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['update-store']);

const router = useRouter();
const authStore = useAuthStore();
const storesStore = useStoresStore();

const planCode = computed(() => (authStore.user?.plan?.code ?? 'free') as string);
const userRole = computed(() => authStore.user?.role ?? '');
const hasPaidAccess = computed(() =>
  planCode.value === 'pro' || planCode.value === 'enterprise' || userRole.value === 'admin' || userRole.value === 'support'
);
const canEditBranding = computed(() => hasPaidAccess.value);
const showBrandingProNotice = ref(false);
const canEditPaymentOptions = computed(() => hasPaidAccess.value);
const showPaymentProNotice = ref(false);
const canEditRatesOptions = computed(() => hasPaidAccess.value);
const showRatesProNotice = ref(false);
const canEditCheckoutOptions = computed(() => hasPaidAccess.value);
const showCheckoutProNotice = ref(false);

const activeSettingsTab = ref<'settings' | 'payment' | 'rates' | 'checkout'>('settings');
const settingsTabs = [
  { id: 'settings' as const, labelKey: 'stores.settings_tab_general' },
  { id: 'payment' as const, labelKey: 'stores.settings_tab_payment' },
  { id: 'rates' as const, labelKey: 'stores.settings_tab_rates' },
  { id: 'checkout' as const, labelKey: 'stores.settings_tab_checkout' },
];

const loading = ref(false);
const saving = ref(false);
const settings = ref<any>(null);
const error = ref('');
const success = ref('');

const settingsForm = ref<Record<string, any>>({
  name: '',
  website: '',
  support_url: '',
  css_url: '',
  payment_sound_url: '',
  brand_color: '#F7931A',
  apply_brand_color_to_backend: false,
  default_currency: 'EUR',
  additional_tracked_rates: [] as string[],
  invoice_expiration: 15,
  refund_bolt11_expiration: 30,
  display_expiration_timer: 90,
  monitoring_expiration: 144,
  speed_policy: 'HighSpeed',
  lightning_description_template: '',
  payment_tolerance: 0,
  archived: false,
  anyone_can_create_invoice: false,
  receipt: { enabled: true, show_qr: null, show_payments: null },
  lightning_amount_in_satoshi: false,
  lightning_private_route_hints: false,
  on_chain_with_ln_invoice_fallback: false,
  redirect_automatically: false,
  show_recommended_fee: true,
  recommended_fee_block_target: 1,
  default_lang: 'en',
  html_title: '',
  network_fee_mode: 'Always',
  pay_join_enabled: false,
  auto_detect_language: false,
  show_pay_in_wallet_button: true,
  show_store_header: true,
  celebrate_payment: true,
  play_sound_on_payment: false,
  lazy_payment_methods: false,
  default_payment_method: '',
  payment_method_criteria: {},
  timezone: 'UTC',
  preferred_exchange: '',
});

// Logo management
const logoInputRef = ref<HTMLInputElement | null>(null);
const storeLogoUrl = ref<string | null>(null);
const uploadingLogo = ref(false);
const deletingLogo = ref(false);
const logoError = ref('');
const logoSuccess = ref('');

// Delete store modal
const showDeleteStoreModal = ref(false);
const deleteStoreConfirmText = ref('');
const deletingStore = ref(false);
const deleteStoreError = ref('');

// Timezones (same list as before)
const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Phoenix',
  'America/Toronto',
  'America/Vancouver',
  'America/Sao_Paulo',
  'America/Argentina/Buenos_Aires',
  'America/Santiago',
  'America/Mexico_City',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Vienna',
  'Europe/Prague',
  'Europe/Warsaw',
  'Europe/Stockholm',
  'Europe/Copenhagen',
  'Europe/Helsinki',
  'Europe/Athens',
  'Europe/Istanbul',
  'Europe/Moscow',
  'Europe/Kiev',
  'Asia/Dubai',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Asia/Hong_Kong',
  'Asia/Singapore',
  'Asia/Seoul',
  'Asia/Bangkok',
  'Asia/Jakarta',
  'Asia/Manila',
  'Asia/Kolkata',
  'Asia/Karachi',
  'Asia/Dhaka',
  'Asia/Tehran',
  'Asia/Jerusalem',
  'Australia/Sydney',
  'Australia/Melbourne',
  'Australia/Brisbane',
  'Australia/Perth',
  'Pacific/Auckland',
  'Pacific/Honolulu',
];

const timezoneOptions = timezones.map(tz => ({ label: tz, value: tz }));

const speedPolicyOptions = [
  { label: 'High speed (1 confirmation)', value: 'HighSpeed' },
  { label: 'Medium speed (2–3 confirmations)', value: 'MediumSpeed' },
  { label: 'Low speed (6+ confirmations)', value: 'LowSpeed' },
];

const networkFeeModeOptions = [
  { label: 'Always add network fee to invoice', value: 'Always' },
  { label: 'Only if customer makes more than one payment for the invoice', value: 'MultiplePaymentsOnly' },
  { label: 'Never', value: 'Never' },
];

function onAdditionalRatesInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value;
  settingsForm.value.additional_tracked_rates = raw.split(',').map((s: string) => s.trim()).filter(Boolean);
}

onMounted(async () => {
  await fetchSettings();
});

async function fetchSettings() {
  if (!props.store) return;

  loading.value = true;
  try {
    const response = await api.get(`/stores/${props.store.id}/settings`);
    const d = response.data.data;
    settings.value = d;
    storeLogoUrl.value = d.logo_url ?? null;

    const arr = (v: unknown) => (Array.isArray(v) ? v : []);
    settingsForm.value.name = d.name ?? '';
    settingsForm.value.website = d.website ?? '';
    settingsForm.value.support_url = d.support_url ?? '';
    settingsForm.value.css_url = d.css_url ?? '';
    settingsForm.value.payment_sound_url = d.payment_sound_url ?? '';
    settingsForm.value.brand_color = d.brand_color ?? '#F7931A';
    settingsForm.value.apply_brand_color_to_backend = !!d.apply_brand_color_to_backend;
    settingsForm.value.default_currency = d.default_currency ?? 'EUR';
    settingsForm.value.additional_tracked_rates = arr(d.additional_tracked_rates);
    settingsForm.value.invoice_expiration = d.invoice_expiration ?? 15;
    settingsForm.value.refund_bolt11_expiration = d.refund_bolt11_expiration ?? 30;
    settingsForm.value.display_expiration_timer = d.display_expiration_timer ?? 90;
    settingsForm.value.monitoring_expiration = d.monitoring_expiration ?? 144;
    settingsForm.value.speed_policy = d.speed_policy ?? 'HighSpeed';
    settingsForm.value.lightning_description_template = d.lightning_description_template ?? '';
    settingsForm.value.payment_tolerance = Number(d.payment_tolerance) || 0;
    settingsForm.value.archived = !!d.archived;
    settingsForm.value.anyone_can_create_invoice = !!d.anyone_can_create_invoice;
    settingsForm.value.receipt = {
      enabled: d.receipt?.enabled !== false,
      show_qr: d.receipt?.show_qr ?? d.receipt?.showQR ?? null,
      show_payments: d.receipt?.show_payments ?? d.receipt?.showPayments ?? null,
    };
    settingsForm.value.lightning_amount_in_satoshi = !!d.lightning_amount_in_satoshi;
    settingsForm.value.lightning_private_route_hints = !!d.lightning_private_route_hints;
    settingsForm.value.on_chain_with_ln_invoice_fallback = !!d.on_chain_with_ln_invoice_fallback;
    settingsForm.value.redirect_automatically = !!d.redirect_automatically;
    settingsForm.value.show_recommended_fee = d.show_recommended_fee !== false;
    settingsForm.value.recommended_fee_block_target = d.recommended_fee_block_target ?? 1;
    settingsForm.value.default_lang = d.default_lang ?? 'en';
    settingsForm.value.html_title = d.html_title ?? '';
    settingsForm.value.network_fee_mode = d.network_fee_mode ?? 'Always';
    settingsForm.value.pay_join_enabled = !!d.pay_join_enabled;
    settingsForm.value.auto_detect_language = !!d.auto_detect_language;
    settingsForm.value.show_pay_in_wallet_button = d.show_pay_in_wallet_button !== false;
    settingsForm.value.show_store_header = d.show_store_header !== false;
    settingsForm.value.celebrate_payment = d.celebrate_payment !== false;
    settingsForm.value.play_sound_on_payment = !!d.play_sound_on_payment;
    settingsForm.value.lazy_payment_methods = !!d.lazy_payment_methods;
    settingsForm.value.default_payment_method = d.default_payment_method ?? '';
    settingsForm.value.payment_method_criteria = d.payment_method_criteria && typeof d.payment_method_criteria === 'object' ? d.payment_method_criteria : {};
    settingsForm.value.timezone = d.timezone ?? 'UTC';
    settingsForm.value.preferred_exchange = d.preferred_exchange ?? '';
  } catch (err) {
    console.error('Failed to fetch settings:', err);
  } finally {
    loading.value = false;
  }
}

async function handleSettingsSubmit() {
  if (!props.store) return;
  
  error.value = '';
  success.value = '';
  saving.value = true;

  try {
    const payload = { ...settingsForm.value };
    if (!canEditBranding.value) {
      delete payload.brand_color;
      delete payload.css_url;
      delete payload.payment_sound_url;
    }
    if (!canEditPaymentOptions.value) {
      const paymentPaidFields = [
        'network_fee_mode', 'invoice_expiration', 'payment_tolerance', 'refund_bolt11_expiration',
        'display_expiration_timer', 'monitoring_expiration', 'speed_policy', 'anyone_can_create_invoice',
        'show_recommended_fee', 'recommended_fee_block_target', 'lightning_description_template', 'archived',
      ];
      paymentPaidFields.forEach((key) => delete payload[key]);
    }
    if (!canEditRatesOptions.value) {
      delete payload.additional_tracked_rates;
    }
    if (!canEditCheckoutOptions.value) {
      const checkoutFields = [
        'default_lang', 'html_title', 'receipt',
        'lightning_amount_in_satoshi', 'lightning_private_route_hints', 'on_chain_with_ln_invoice_fallback',
        'redirect_automatically', 'pay_join_enabled', 'auto_detect_language', 'show_pay_in_wallet_button',
        'show_store_header', 'celebrate_payment', 'play_sound_on_payment', 'lazy_payment_methods',
        'default_payment_method',
      ];
      checkoutFields.forEach((key) => delete payload[key]);
    }
    await api.put(`/stores/${props.store.id}/settings`, payload);
    success.value = 'Settings updated successfully';
    emit('update-store'); // Notify parent to refresh store data if needed
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to update settings.';
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(', ');
    }
  } finally {
    saving.value = false;
    // Clear success message
    if (success.value) {
      setTimeout(() => { success.value = ''; }, 3000);
    }
  }
}

async function handleLogoUpload(event: Event) {
  if (!props.store) return;
  
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploadingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(`/stores/${props.store.id}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (response.data?.data?.imageUrl) {
      storeLogoUrl.value = response.data.data.imageUrl;
    } else if (response.data?.imageUrl) {
      storeLogoUrl.value = response.data.imageUrl;
    }

    logoSuccess.value = 'Logo uploaded successfully';
    emit('update-store');

    if (logoInputRef.value) logoInputRef.value.value = '';
    setTimeout(() => { logoSuccess.value = ''; }, 3000);
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to upload logo';
  } finally {
    uploadingLogo.value = false;
  }
}

async function handleDeleteLogo() {
  if (!props.store) return;
  
  if (!confirm('Are you sure you want to delete the store logo?')) return;

  deletingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    await api.delete(`/stores/${props.store.id}/logo`);
    storeLogoUrl.value = null;
    logoSuccess.value = 'Logo deleted successfully';
    emit('update-store');
    setTimeout(() => { logoSuccess.value = ''; }, 3000);
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to delete logo';
  } finally {
    deletingLogo.value = false;
  }
}

async function handleDeleteStore() {
  if (!props.store) return;
  
  if (deleteStoreConfirmText.value !== 'DELETE') {
    deleteStoreError.value = 'Please type DELETE to confirm';
    return;
  }

  deletingStore.value = true;
  deleteStoreError.value = '';

  try {
    await storesStore.deleteStore(props.store.id);
    showDeleteStoreModal.value = false;
    router.push({ name: 'home' });
  } catch (err: any) {
    deleteStoreError.value = err.response?.data?.message || 'Failed to delete store';
    deletingStore.value = false;
  }
}
</script>

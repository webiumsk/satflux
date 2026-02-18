<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-white">
      {{ t("stores.settings_tab_checkout") }}
    </h2>
    <p class="text-sm text-gray-400 max-w-2xl">
      {{ t("stores.settings_tab_checkout_desc") }}
    </p>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-base font-medium text-white">{{
        t("stores.settings_checkout_options")
      }}</span>
      <button
        v-if="!canEditCheckoutOptions"
        type="button"
        @click="$emit('show-upgrade')"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
      >
        {{ t("stores.available_in_pro") }}
      </button>
    </div>
    <div
      :class="[
        'space-y-6 max-w-2xl',
        !canEditCheckoutOptions && 'pointer-events-none opacity-75 select-none',
      ]"
    >
      <div class="form-group mb-4">
        <label
          for="default_payment_method"
          class="block text-sm font-medium text-gray-300 mb-1"
          >{{ t("stores.settings_default_payment_method") }}</label
        >
        <Select
          id="default_payment_method"
          v-model="form.default_payment_method"
          :options="defaultPaymentMethodOptions"
          :placeholder="t('stores.settings_select')"
        />
      </div>
      <div class="form-group mb-4">
        <div class="text-sm font-medium text-gray-300 mb-1">
          {{ t("stores.settings_enable_payment_when_amount") }}
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <template
            v-for="(crit, idx) in form.payment_method_criteria"
            :key="idx"
          >
            <div class="w-28 min-w-0">
              <Select
                v-model="crit.payment_method"
                :options="defaultPaymentMethodOptions"
                :placeholder="t('stores.settings_method')"
              />
            </div>
            <div class="w-36 min-w-0">
              <Select
                v-model="crit.type"
                :options="paymentMethodCriteriaTypeOptions"
                :placeholder="t('stores.settings_type')"
              />
            </div>
            <input
              v-model="crit.value"
              type="text"
              placeholder="6.15 USD"
              class="w-28 px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </template>
        </div>
      </div>
      <h3
        class="text-base font-semibold text-white mb-3 pt-2 border-t border-gray-700"
      >
        {{ t("stores.settings_checkout_section") }}
      </h3>
      <div class="space-y-4">
        <div>
          <label
            for="display_expiration_timer"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_timer_before_expiration") }}</label
          >
          <div class="flex items-center gap-2 max-w-[14rem]">
            <input
              id="display_expiration_timer"
              v-model.number="form.display_expiration_timer"
              type="number"
              min="1"
              max="34560"
              inputmode="numeric"
              class="w-24 px-4 py-2 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <span class="text-sm text-gray-400">{{
              t("stores.settings_minutes")
            }}</span>
          </div>
        </div>
        <label class="flex items-center gap-3">
          <input
            v-model="form.celebrate_payment"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_celebrate_payment")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.play_sound_on_payment"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_sounds_on_checkout")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.show_store_header"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_show_store_header")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.show_pay_in_wallet_button"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_show_pay_in_wallet")
          }}</span>
        </label>
      </div>
      <h3
        class="text-base font-semibold text-white mb-3 pt-4 border-t border-gray-700"
      >
        {{ t("stores.settings_checkout_experience") }}
      </h3>
      <div class="space-y-4">
        <label class="flex items-center gap-3">
          <input
            v-model="form.on_chain_with_ln_invoice_fallback"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_unify_onchain_ln")
          }}</span>
          <a
            href="https://bitcoinqr.dev/"
            target="_blank"
            rel="noreferrer noopener"
            class="text-gray-500 hover:text-gray-400"
            :title="t('stores.settings_more_info')"
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
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </a>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.lightning_amount_in_satoshi"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_lightning_satoshis")
          }}</span>
        </label>
        <div class="flex items-start gap-3">
          <input
            v-model="form.auto_detect_language"
            type="checkbox"
            class="mt-1 h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <div>
            <label
              for="auto_detect_language"
              class="text-sm font-medium text-gray-300"
              >{{ t("stores.settings_auto_detect_language") }}</label
            >
            <p class="text-xs text-gray-500 mt-0.5">
              {{ t("stores.settings_auto_detect_language_desc") }}
            </p>
          </div>
        </div>
        <div>
          <label
            for="default_lang"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_default_language") }}</label
          >
          <Select
            id="default_lang"
            v-model="form.default_lang"
            :options="defaultLangOptions"
            :placeholder="t('stores.settings_select_language')"
          />
        </div>
        <div>
          <label
            for="html_title"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_html_title") }}</label
          >
          <input
            id="html_title"
            v-model="form.html_title"
            type="text"
            class="w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label
            for="support_url"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_support_url") }}</label
          >
          <input
            id="support_url"
            v-model="form.support_url"
            type="text"
            placeholder="https://example.com/support"
            class="w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <p class="mt-1.5 text-xs text-gray-500">
            {{ t("stores.settings_support_url_desc") }}
          </p>
        </div>
        <label class="flex items-center gap-3">
          <input
            v-model="form.lazy_payment_methods"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_lazy_payment_methods")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.redirect_automatically"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_redirect_automatically")
          }}</span>
        </label>
      </div>
      <h3
        class="text-base font-semibold text-white mb-3 pt-4 border-t border-gray-700"
      >
        {{ t("stores.settings_receipt_section") }}
      </h3>
      <div class="space-y-3">
        <label class="flex items-center gap-3">
          <input
            v-model="form.receipt.enabled"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_receipt_enabled")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.receipt.show_payments"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
            :disabled="!form.receipt.enabled"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_receipt_show_payments")
          }}</span>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.receipt.show_qr"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
            :disabled="!form.receipt.enabled"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_receipt_show_qr")
          }}</span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";
import Select from "../ui/Select.vue";

defineProps<{
  form: Record<string, any>;
  canEditCheckoutOptions: boolean;
  defaultPaymentMethodOptions: { label: string; value: string }[];
  paymentMethodCriteriaTypeOptions: { label: string; value: string }[];
  defaultLangOptions: { label: string; value: string }[];
}>();

defineEmits<{
  "show-upgrade": [];
}>();

const { t } = useI18n();
</script>

<template>
  <div class="space-y-8">
    <h2 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
      {{ t("stores.settings_tab_lightning") }}
    </h2>
    <p class="text-sm text-gray-400 max-w-2xl">
      {{ t("stores.settings_tab_lightning_desc") }}
    </p>

    <!-- Payment (Lightning) -->
    <section class="space-y-4">
      <h3 class="text-base font-semibold text-white">
        {{ t("stores.settings_lightning_payment_section") }}
      </h3>
      <div class="space-y-4">
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
        <label class="flex items-center gap-3">
          <input
            v-model="form.lightning_private_route_hints"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_lightning_hop_hints")
          }}</span>
        </label>
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
        <div>
          <label
            for="lightning_description_template"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_lightning_description_template") }}</label
          >
          <input
            id="lightning_description_template"
            v-model="form.lightning_description_template"
            type="text"
            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            :placeholder="t('stores.settings_lightning_description_placeholder')"
          />
          <p class="mt-1.5 text-xs text-gray-500">
            {{ t("stores.settings_lightning_description_placeholders") }}
          </p>
        </div>
      </div>
    </section>

    <!-- LNURL -->
    <section class="space-y-4 pt-4 border-t border-gray-700">
      <h3 class="text-base font-semibold text-white">
        {{ t("stores.settings_lightning_lnurl_section") }}
      </h3>
      <div class="space-y-4">
        <label class="flex items-center gap-3">
          <input
            v-model="form.lnurl_enabled"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_lnurl_enabled")
          }}</span>
        </label>
        <label class="flex items-start gap-3">
          <input
            v-model="form.lnurl_classic_mode"
            type="checkbox"
            class="mt-1 h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
            :disabled="!form.lnurl_enabled"
          />
          <div>
            <span class="text-sm text-gray-300">{{
              t("stores.settings_lnurl_classic_mode")
            }}</span>
            <p class="text-xs text-gray-500 mt-0.5">
              {{ t("stores.settings_lnurl_classic_mode_desc") }}
            </p>
          </div>
        </label>
        <label class="flex items-center gap-3">
          <input
            v-model="form.lnurl_allow_payee_comment"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
            :disabled="!form.lnurl_enabled"
          />
          <span class="text-sm text-gray-300">{{
            t("stores.settings_lnurl_allow_payee_comment")
          }}</span>
        </label>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

defineProps<{
  form: Record<string, any>;
}>();

const { t } = useI18n();
</script>

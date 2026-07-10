<template>
  <div
    v-if="isCashuFlow"
    class="bg-gray-900/50 border border-gray-700 rounded-2xl p-8"
  >
    <div v-if="switchToCashuIntent && walletType !== 'cashu'" class="mb-6">
      <button
        type="button"
        class="text-sm font-medium text-indigo-400 hover:text-indigo-300"
        @click="switchToCashuIntent = false"
      >
        ← {{ t("stores.wallet_connection_back_from_cashu") }}
      </button>
    </div>

    <!-- Cashu store: read-only summary until user confirms password -->
    <template
      v-if="walletType === 'cashu' && cashuSectionMode === 'readonly'"
    >
      <h3
        class="text-sm font-bold text-indigo-400 mb-6 uppercase tracking-wider"
      >
        {{ t("stores.cashu_settings_title") }}
      </h3>
      <div
        class="mb-6 p-4 rounded-xl border border-green-500/20 bg-green-500/10 text-sm"
      >
        <span class="font-medium text-green-400">{{
          t("stores.connected")
        }}</span>
        <span class="text-gray-300 ml-2">{{
          t("stores.cashu_connected_via_plugin")
        }}</span>
      </div>
      <div class="space-y-5 text-sm mb-8">
        <div>
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
            >{{ t("stores.cashu_mint_url_label") }}</span
          >
          <p class="font-mono text-gray-200 break-all">
            {{ maskCashuMintUrl(cashuForm.mint_url) }}
          </p>
        </div>
        <div>
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
            >{{ t("stores.cashu_lightning_address_label") }}</span
          >
          <p class="text-gray-200">
            {{ maskCashuLightningAddress(cashuForm.lightning_address) }}
          </p>
        </div>
        <div>
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
            >{{ t("stores.cashu_trusted_mint_urls_label") }}</span
          >
          <p class="text-gray-200">
            {{
              (cashuForm.trusted_mint_urls || "").trim()
                ? t("stores.cashu_summary_configured")
                : "-"
            }}
          </p>
        </div>
        <div>
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
            >{{ t("stores.cashu_max_melt_fee_sats_label") }}</span
          >
          <p class="text-gray-200">
            {{ cashuMaxMeltSatsDisplay === "" ? "-" : cashuMaxMeltSatsDisplay }}
          </p>
        </div>
        <div>
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
            >{{ t("stores.cashu_max_melt_fee_percent_label") }}</span
          >
          <p class="text-gray-200">
            {{
              cashuMaxMeltPercentDisplay === ""
                ? "-"
                : cashuMaxMeltPercentDisplay
            }}
          </p>
        </div>
      </div>
      <p v-if="passwordError" class="mb-4 text-sm text-red-400">
        {{ passwordError }}
      </p>
      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="inline-flex items-center px-6 py-3 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20 transition-all disabled:opacity-50"
          :disabled="revealing"
          @click="startCashuConnectionEdit"
        >
          {{ t("stores.change_connection") }}
        </button>
        <button
          type="button"
          class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
          @click="$emit('cancel')"
        >
          {{ t("common.cancel") }}
        </button>
      </div>
    </template>

    <!-- Cashu: confirm password / LNURL / Nostr before editing -->
    <template
      v-else-if="walletType === 'cashu' && cashuSectionMode === 'password'"
    >
      <h3
        class="text-sm font-bold text-indigo-400 mb-4 uppercase tracking-wider"
      >
        {{ t("stores.cashu_settings_title") }}
      </h3>
      <div
        class="bg-gray-900/50 border border-gray-700 rounded-xl p-6 max-w-md"
      >
        <p class="text-sm text-gray-400 mb-4">
          {{ t("stores.confirm_password_to_change") }}
        </p>
        <form @submit.prevent="handleCashuConfirmPassword" class="space-y-4">
          <div>
            <label
              for="cashu-wc-password"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("account.current_password") }}</label
            >
            <input
              id="cashu-wc-password"
              v-model="passwordInput"
              type="password"
              autocomplete="current-password"
              :placeholder="t('stores.password_placeholder')"
              class="block w-full rounded-xl border border-gray-600 bg-gray-800 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
            />
            <p v-if="passwordError" class="mt-2 text-sm text-red-400">
              {{ passwordError }}
            </p>
          </div>
          <div class="flex gap-3">
            <button
              type="button"
              @click="
                cashuSectionMode = 'readonly';
                passwordError = '';
                passwordInput = '';
              "
              class="px-5 py-2.5 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 transition-all"
            >
              {{ t("common.cancel") }}
            </button>
            <button
              type="submit"
              :disabled="revealing || !passwordInput.trim()"
              class="px-5 py-2.5 border border-transparent rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <span v-if="revealing">{{ t("common.loading") }}</span>
              <span v-else>{{ t("common.confirm") }}</span>
            </button>
          </div>
        </form>
        <div
          v-if="hasLightningLogin || hasNostrLogin"
          class="mt-4 pt-4 border-t border-gray-700 space-y-2"
        >
          <p class="text-sm text-gray-400 mb-2">
            {{ t("account.or_confirm_with_lightning") }}
          </p>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="hasLightningLogin"
              type="button"
              :disabled="lnurlRevealLoading || lnurlRevealPolling"
              @click="handleCashuEditWithLightning"
              class="px-4 py-2 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 hover:bg-indigo-500/10 disabled:opacity-50"
            >
              <span v-if="lnurlRevealLoading || lnurlRevealPolling">{{
                t("common.loading")
              }}</span>
              <span v-else>{{
                t("account.confirm_with_lightning_wallet")
              }}</span>
            </button>
            <button
              v-if="hasNostrLogin"
              type="button"
              @click="showNostrRevealModal = true"
              class="px-4 py-2 border border-amber-500/50 rounded-xl text-sm font-medium text-amber-400 hover:bg-amber-500/10"
            >
              🟠 {{ t("auth.nostr_confirm_reveal") }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Cashu: editing (first-time setup, after password, or switching from Lightning) -->
    <template v-else>
      <h3
        class="text-sm font-bold text-indigo-400 mb-6 uppercase tracking-wider inline-flex items-center gap-2 flex-wrap"
      >
        {{ t("stores.cashu_settings_title") }}
        <span
          class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 border border-amber-500/40"
          >{{ t("stores.cashu_beta_badge") }}</span
        >
      </h3>

      <div
        v-if="
          walletType === 'cashu' &&
          (cashuForm.mint_url || '').trim() &&
          (cashuForm.lightning_address || '').trim()
        "
        class="mb-4 p-4 rounded-xl border border-green-500/20 bg-green-500/10 text-sm"
      >
        <span class="font-medium text-green-400">{{
          t("stores.connected")
        }}</span>
        <span class="text-gray-300 ml-2">{{
          t("stores.cashu_connected_via_plugin")
        }}</span>
      </div>

      <div
        class="mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 space-y-4"
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
            v-model="cashuBetaAccepted"
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
          />
          <span class="text-sm text-amber-100 leading-relaxed">{{
            t("stores.cashu_beta_consent_checkbox")
          }}</span>
        </label>
      </div>

      <div v-if="cashuLoading" class="flex items-center justify-center py-10">
        <svg
          class="animate-spin h-8 w-8 text-indigo-500"
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

      <form v-else-if="cashuBetaAccepted" @submit.prevent="handleSaveCashu" class="space-y-6">
        <div>
          <label
            for="cashu-mint-url"
            class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
          >
            {{ t("stores.cashu_mint_url_label") }}
          </label>
          <input
            id="cashu-mint-url"
            v-model="cashuForm.mint_url"
            type="text"
            class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3 font-mono"
            :placeholder="t('stores.cashu_mint_url_placeholder')"
          />
          <p
            class="mt-2 text-sm text-gray-500 leading-relaxed whitespace-pre-line"
          >
            {{ t("stores.cashu_mint_url_hint") }}
          </p>
          <p v-if="cashuErrors.mint_url" class="mt-2 text-sm text-red-400">
            {{ cashuErrors.mint_url }}
          </p>
        </div>

        <div>
          <label
            for="cashu-lightning-address"
            class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
          >
            {{ t("stores.cashu_lightning_address_label") }}
          </label>
          <input
            id="cashu-lightning-address"
            v-model="cashuForm.lightning_address"
            type="text"
            class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
            :placeholder="t('stores.cashu_lightning_address_placeholder')"
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
              >coinos.io</a
            >
          </p>
          <p
            v-if="cashuErrors.lightning_address"
            class="mt-2 text-sm text-red-400"
          >
            {{ cashuErrors.lightning_address }}
          </p>
        </div>

        <div>
          <label
            for="cashu-trusted-mints"
            class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
          >
            {{ t("stores.cashu_trusted_mint_urls_label") }}
          </label>
          <textarea
            id="cashu-trusted-mints"
            v-model="cashuForm.trusted_mint_urls"
            rows="4"
            class="block w-full rounded-xl border border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3 font-mono"
            :placeholder="t('stores.cashu_trusted_mint_urls_placeholder')"
          />
          <p
            class="mt-2 text-sm text-gray-500 leading-relaxed whitespace-pre-line"
          >
            {{ t("stores.cashu_trusted_mint_urls_hint") }}
          </p>
          <p
            v-if="cashuErrors.trusted_mint_urls"
            class="mt-2 text-sm text-red-400"
          >
            {{ cashuErrors.trusted_mint_urls }}
          </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
          <div>
            <label
              for="cashu-max-fee-sats"
              class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
            >
              {{ t("stores.cashu_max_melt_fee_sats_label") }}
            </label>
            <input
              id="cashu-max-fee-sats"
              v-model="cashuForm.max_melt_fee_reserve_sats"
              type="text"
              inputmode="numeric"
              autocomplete="off"
              class="block w-full rounded-xl border border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3 tabular-nums"
              :placeholder="t('stores.cashu_optional_limit_placeholder')"
            />
            <p class="mt-2 text-sm text-gray-500">
              {{ t("stores.cashu_max_melt_fee_sats_hint") }}
            </p>
            <p
              v-if="cashuErrors.max_melt_fee_reserve_sats"
              class="mt-2 text-sm text-red-400"
            >
              {{ cashuErrors.max_melt_fee_reserve_sats }}
            </p>
          </div>
          <div>
            <label
              for="cashu-max-fee-pct"
              class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
            >
              {{ t("stores.cashu_max_melt_fee_percent_label") }}
            </label>
            <input
              id="cashu-max-fee-pct"
              v-model="cashuForm.max_melt_fee_reserve_percent_of_minted"
              type="text"
              inputmode="decimal"
              autocomplete="off"
              class="block w-full rounded-xl border border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3 tabular-nums"
              :placeholder="t('stores.cashu_optional_limit_placeholder')"
            />
            <p class="mt-2 text-sm text-gray-500">
              {{ t("stores.cashu_max_melt_fee_percent_hint") }}
            </p>
            <p
              v-if="cashuErrors.max_melt_fee_reserve_percent_of_minted"
              class="mt-2 text-sm text-red-400"
            >
              {{ cashuErrors.max_melt_fee_reserve_percent_of_minted }}
            </p>
          </div>
        </div>

        <div
          v-if="cashuErrorMessage"
          class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300"
        >
          {{ cashuErrorMessage }}
        </div>

        <div
          class="flex flex-col-reverse sm:flex-row justify-between items-center gap-3 pt-4 border-t border-gray-700"
        >
          <button
            type="button"
            @click="$emit('cancel')"
            class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all w-full sm:w-auto"
            :disabled="cashuSubmitting"
          >
            {{ t("common.cancel") }}
          </button>
          <button
            type="submit"
            :disabled="cashuSubmitting || !canSaveCashu"
            class="px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all w-full sm:w-auto"
          >
            <span v-if="cashuSubmitting">{{ t("common.loading") }}</span>
            <span v-else>{{ t("stores.cashu_save_settings") }}</span>
          </button>
        </div>
      </form>
    </template>
  </div>

  <div v-else class="space-y-8">
    <!-- Read-only: Current Connection + Change button (when connection exists and not editing) -->
    <template v-if="existingConnection && viewMode === 'readonly'">
      <div class="bg-gray-900/50 border border-gray-700 rounded-2xl p-8">
        <h3
          class="text-sm font-bold text-indigo-400 mb-6 uppercase tracking-wider"
        >
          {{ t("stores.current_connection") }}
        </h3>
        <div
          v-if="existingConnection.configuration_source === 'samrock'"
          class="mb-6 p-4 rounded-xl border border-green-500/30 bg-green-500/10"
        >
          <p class="text-sm text-green-300">
            {{ t("stores.samrock_connected_note") }}
          </p>
        </div>
        <div
          v-else-if="existingConnection.type === 'aqua_descriptor'"
          class="mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10"
        >
          <p class="text-sm text-amber-400">
            {{ t("stores.aqua_warning_btcpay") }}
          </p>
          <p class="text-sm text-amber-400 mt-2">
            {{ t("stores.aqua_limits_warning") }}
          </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
          <div>
            <span
              class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
              >{{ t("stores.type") }}</span
            >
            <WalletTypeIcon
              :type="existingConnection.type"
              :brand="
                existingConnection.type === 'aqua_descriptor'
                  ? readonlyAquaWalletBrand
                  : undefined
              "
              size="lg"
              :show-label="true"
              class="font-medium text-white"
            />
          </div>
          <div>
            <span
              class="block text-gray-500 text-xs uppercase tracking-wider mb-1"
              >{{ t("stores.status") }}</span
            >
            <span
              class="font-medium"
              :class="getStatusColorClass(existingConnection.status)"
              >{{ formatStatus(existingConnection.status) }}</span
            >
          </div>
        </div>
        <div class="mt-6 pt-6 border-t border-gray-700">
          <span
            class="block text-gray-500 text-xs uppercase tracking-wider mb-2"
            >{{ t("stores.masked_secret") }}</span
          >
          <div
            class="font-mono text-gray-300 text-sm break-all bg-gray-800/80 px-4 py-3 rounded-xl border border-gray-700/50"
          >
            {{ existingConnection.masked_secret || "N/A" }}
          </div>
        </div>
        <!-- Last change: date + user ID (integrated into main card) -->
        <div
          v-if="
            existingConnection.secret_updated_at ||
            existingConnection.submitted_by_user_id
          "
          class="mt-6 pt-6 border-t border-gray-700 flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm text-gray-400"
        >
          <span v-if="existingConnection.secret_updated_at">
            <span class="text-gray-500 uppercase tracking-wider"
              >{{ t("stores.last_connection_change") }}:</span
            >
            <span class="ml-2 text-gray-300">{{
              formatLastChangeDate(existingConnection.secret_updated_at)
            }}</span>
          </span>
          <span v-if="existingConnection.submitted_by_user_id">
            <span class="text-gray-500 uppercase tracking-wider"
              >{{ t("stores.by_user_id") }}:</span
            >
            <span class="ml-2 font-mono text-indigo-300">{{
              existingConnection.submitted_by_user_id
            }}</span>
          </span>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-4">
          <button
            type="button"
            @click="startWalletConnectionEdit"
            :disabled="revealing"
            class="inline-flex items-center px-6 py-3 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20 transition-all disabled:opacity-50"
          >
            {{ t("stores.change_connection") }}
          </button>
          <button
            type="button"
            @click="$emit('cancel')"
            class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
          >
            {{ t("common.cancel") }}
          </button>
        </div>
      <p v-if="passwordError" class="mt-4 text-sm text-red-400">
        {{ passwordError }}
      </p>
    </template>

    <!-- Password step: before revealing secret -->
    <template v-else-if="existingConnection && viewMode === 'password'">
      <div
        class="bg-gray-900/50 border border-gray-700 rounded-xl p-6 max-w-md"
      >
        <p class="text-sm text-gray-400 mb-4">
          {{ t("stores.confirm_password_to_change") }}
        </p>
        <form @submit.prevent="handleConfirmPassword" class="space-y-4">
          <div>
            <label
              for="wc-password"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("account.current_password") }}</label
            >
            <input
              id="wc-password"
              v-model="passwordInput"
              type="password"
              autocomplete="current-password"
              :placeholder="t('stores.password_placeholder')"
              class="block w-full rounded-xl border border-gray-600 bg-gray-800 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
            />
            <p v-if="passwordError" class="mt-2 text-sm text-red-400">
              {{ passwordError }}
            </p>
          </div>
          <div class="flex gap-3">
            <button
              type="button"
              @click="
                viewMode = 'readonly';
                passwordError = '';
                passwordInput = '';
              "
              class="px-5 py-2.5 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 transition-all"
            >
              {{ t("common.cancel") }}
            </button>
            <button
              type="submit"
              :disabled="revealing || !passwordInput.trim()"
              class="px-5 py-2.5 border border-transparent rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <span v-if="revealing">{{ t("common.loading") }}</span>
              <span v-else>{{ t("common.confirm") }}</span>
            </button>
          </div>
        </form>
        <div
          v-if="hasLightningLogin || hasNostrLogin"
          class="mt-4 pt-4 border-t border-gray-700 space-y-2"
        >
          <p class="text-sm text-gray-400 mb-2">
            {{ t("account.or_confirm_with_lightning") }}
          </p>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="hasLightningLogin"
              type="button"
              :disabled="lnurlRevealLoading || lnurlRevealPolling"
              @click="handleConfirmWithLightning"
              class="px-4 py-2 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 hover:bg-indigo-500/10 disabled:opacity-50"
            >
              <span v-if="lnurlRevealLoading || lnurlRevealPolling">{{
                t("common.loading")
              }}</span>
              <span v-else>{{
                t("account.confirm_with_lightning_wallet")
              }}</span>
            </button>
            <button
              v-if="hasNostrLogin"
              type="button"
              @click="showNostrRevealModal = true"
              class="px-4 py-2 border border-amber-500/50 rounded-xl text-sm font-medium text-amber-400 hover:bg-amber-500/10"
            >
              🟠 {{ t("auth.nostr_confirm_reveal") }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Unified wallet setup (paste first, SamRock second - same as create store) -->
    <template v-else-if="showWalletSetupForm">
      <div class="space-y-6">
      <div
        v-if="showWalletSetupTabs"
        class="flex gap-1 p-1 rounded-xl bg-gray-800/90 border border-gray-600 w-full sm:w-fit"
      >
        <button
          type="button"
          class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all"
          :class="
            walletSetupTab === 'paste'
              ? 'bg-indigo-600 text-white shadow-md'
              : 'text-gray-400 hover:text-white hover:bg-gray-700/80'
          "
          @click="walletSetupTab = 'paste'"
        >
          {{ t("stores.wallet_smart_paste_label") }}
        </button>
        <button
          type="button"
          class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-medium transition-all"
          :class="
            walletSetupTab === 'samrock'
              ? 'bg-indigo-600 text-white shadow-md'
              : 'text-gray-400 hover:text-white hover:bg-gray-700/80'
          "
          @click="walletSetupTab = 'samrock'"
        >
          <span class="inline-flex items-center gap-2 flex-wrap">
            {{ t("create_store.tab_samrock") }}
            <span
              class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
            >{{ t("stores.wallet_recommended_badge") }}</span>
          </span>
        </button>
      </div>

      <div
        v-show="!showWalletSetupTabs || walletSetupTab === 'paste'"
        class="bg-gray-900/50 rounded-xl p-6 border border-gray-700 space-y-6"
      >
        <div
          v-if="
            viewMode === 'editing' &&
            existingConnection &&
            existingConnection.status === 'connected' &&
            walletType === 'aqua_boltz'
          "
          class="p-4 rounded-xl border border-green-500/25 bg-green-500/10 text-sm text-green-100 space-y-1"
        >
          <p class="font-medium text-green-400">
            {{ t("stores.aqua_wallet_connected_title") }}
          </p>
          <p class="text-gray-300 leading-relaxed">
            {{ t("stores.aqua_wallet_connected_samrock_hint") }}
          </p>
        </div>

        <div
          v-if="showAquaDescriptorWarnings"
          class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 space-y-2"
        >
          <p class="text-sm text-amber-400">
            {{ t("stores.aqua_warning_btcpay") }}
          </p>
          <p class="text-sm text-amber-400">
            {{ t("stores.aqua_limits_warning") }}
          </p>
        </div>

        <WalletConnectionSmartPaste
          v-model="form.secret"
          v-model:connection-type="form.type"
          input-id="wallet-connection-smart-paste"
          @detect-cashu="onDetectCashuFromPaste"
        />

        <p v-if="errors.type" class="text-sm text-red-400">{{ errors.type }}</p>
        <p v-if="errors.secret" class="text-sm text-red-400">{{ errors.secret }}</p>

        <div
          v-if="testResult"
          class="rounded-xl p-4 border"
          :class="
            testResult.success
              ? 'bg-green-500/10 border-green-500/20'
              : 'bg-red-500/10 border-red-500/20'
          "
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg
                v-if="testResult.success"
                class="h-5 w-5 text-green-400"
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
              <svg
                v-else
                class="h-5 w-5 text-red-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div class="ml-3">
              <p
                class="text-sm font-medium"
                :class="testResult.success ? 'text-green-400' : 'text-red-400'"
              >
                {{ testResult.message }}
              </p>
              <p
                v-if="testResult.requires_manual_config"
                class="mt-1 text-sm text-gray-400"
              >
                {{ t("stores.manual_config_required") }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div
        v-show="showWalletSetupTabs && walletSetupTab === 'samrock'"
        class="bg-gray-900/50 border border-indigo-500/30 rounded-2xl p-8 space-y-4"
      >
        <div
          v-if="
            viewMode === 'editing' &&
            existingConnection &&
            existingConnection.status === 'connected' &&
            walletType === 'aqua_boltz'
          "
          class="p-4 rounded-xl border border-green-500/25 bg-green-500/10 text-sm text-green-100 space-y-1"
        >
          <p class="font-medium text-green-400">
            {{ t("stores.aqua_wallet_connected_title") }}
          </p>
          <p class="text-gray-300 leading-relaxed">
            {{ t("stores.aqua_wallet_connected_samrock_hint") }}
          </p>
        </div>
        <h3 class="text-sm font-bold text-indigo-400 uppercase tracking-wider">
          {{ t("stores.samrock_title") }}
        </h3>
        <p class="text-sm text-gray-400 leading-relaxed">
          {{ t("stores.samrock_description") }}
        </p>
        <p
          v-if="samrockErrorMessage && !samrockQrObjectUrl"
          class="text-red-400 text-sm"
        >
          {{ samrockErrorMessage }}
        </p>

        <div v-if="!samrockOtp && !samrockBusy" class="flex flex-wrap gap-3">
          <button
            type="button"
            class="px-6 py-3 rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
            :disabled="samrockBusy"
            @click="startSamRockPairing"
          >
            {{ t("stores.samrock_generate_qr") }}
          </button>
          <button
            v-if="samrockErrorMessage"
            type="button"
            class="px-6 py-3 rounded-xl text-sm font-medium border border-gray-600 text-gray-300 hover:bg-gray-800"
            @click="
              samrockErrorMessage = '';
              startSamRockPairing();
            "
          >
            {{ t("stores.samrock_try_again") }}
          </button>
        </div>

        <div
          v-else-if="samrockBusy && !samrockQrObjectUrl"
          class="flex items-center gap-3 py-6 text-gray-400"
        >
          <svg
            class="animate-spin h-6 w-6 text-indigo-500"
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
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            ></path>
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
                {{ t("stores.samrock_expires") }}:
                {{ formatSamRockExpiry(samrockExpiresAt) }}
              </p>
              <p v-if="samrockPollStatus" class="font-mono text-indigo-300">
                {{ samrockPollStatus }}
              </p>
              <p v-if="samrockErrorMessage" class="text-red-400">
                {{ samrockErrorMessage }}
              </p>
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
      </div>

      <WalletConnectionTypeGuide
        :highlight-kind="
          walletPasteDetection.kind === 'unknown' ? null : walletPasteDetection.kind
        "
        :highlight-brand="walletPasteDetection.brand"
      />

      <div
        class="flex flex-col-reverse sm:flex-row justify-between items-center gap-4 pt-2"
      >
        <div class="flex w-full sm:w-auto gap-4">
          <button
            type="button"
            @click="handleTestConnection"
            :disabled="testing || !form.secret.trim()"
            class="w-full sm:w-auto px-6 py-3 border border-gray-600 rounded-xl shadow-sm text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          >
            {{ testing ? t("stores.testing") : t("stores.test_connection") }}
          </button>
        </div>
        <div class="flex w-full sm:w-auto gap-4">
          <button
            v-if="existingConnection && viewMode === 'editing'"
            type="button"
            @click="handleCancelEdit"
            class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
          >
            {{ t("common.cancel") }}
          </button>
          <button
            v-else
            type="button"
            @click="$emit('cancel')"
            class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
          >
            {{ t("common.cancel") }}
          </button>
          <button
            type="button"
            :disabled="submitting"
            class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            @click="handleSubmit"
          >
            {{ submitting ? t("common.loading") : t("stores.save_connection") }}
          </button>
        </div>
      </div>
      </div>
    </template>

    <div
      v-else
      class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-6 text-sm text-gray-200 space-y-2"
    >
      <p class="font-medium text-amber-200">
        {{ t("stores.wallet_connection_unsupported_title") }}
      </p>
      <p class="text-gray-400">
        {{
          t("stores.wallet_connection_unsupported_body", {
            type: walletTypeLabel,
          })
        }}
      </p>
    </div>
  </div>

  <LnurlQrModal
    :open="showLnurlRevealModal"
    :title="t('account.confirm_with_lightning_wallet')"
    :lnurl="lnurlRevealUrl"
    :error="lnurlRevealError"
    :polling="lnurlRevealPolling"
    :expires-in-seconds="300"
    @close="closeLnurlRevealModal"
    @regenerate="requestNewRevealChallenge"
  />
  <NostrAuthModal
    :open="showNostrRevealModal"
    mode="reveal"
    :store-id="storeId"
    :confirm-purpose="nostrRevealConfirmPurpose"
    @close="showNostrRevealModal = false"
    @success="onNostrRevealSuccess"
  />
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onUnmounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import api, { walletApi } from "../../services/api";
import { DEFAULT_CASHU_MINT_URL } from "../../constants/cashu";
import WalletTypeIcon from "../WalletTypeIcon.vue";
import WalletConnectionSmartPaste from "./WalletConnectionSmartPaste.vue";
import WalletConnectionTypeGuide from "./WalletConnectionTypeGuide.vue";
import { isValidAquaBoltzDescriptor } from "../../utils/aquaBoltzDescriptor";
import {
  detectWalletConnectionInput,
  isValidCashuLightningAddress,
} from "../../utils/detectWalletConnectionInput";
import {
  isCashuWalletNwcUri,
  normalizeNwcUri,
  validateBlinkConnectionString,
  validateNwcUri,
} from "../../utils/walletNwcHelpers";
import {
  detectWalletBrandFromDescriptor,
  type AquaBoltzWalletBrand,
} from "../../utils/aquaBoltzWalletBrand";
import LnurlQrModal from "../auth/LnurlQrModal.vue";
import NostrAuthModal from "../auth/NostrAuthModal.vue";

interface Props {
  storeId: string;
  existingConnection?: any;
  walletType?: "blink" | "aqua_boltz" | "cashu" | "nwc" | string | null | undefined;
  /** When wallet_type is aqua_boltz: Aqua vs Bull (from API) */
  walletBrand?: AquaBoltzWalletBrand | null | undefined;
  /** After create-store redirect: auto-open SamRock QR flow */
  autoSamrock?: boolean;
}

const props = defineProps<Props>();

const switchToCashuIntent = ref(false);
/** Native Cashu store: readonly summary → password → editing. */
const cashuSectionMode = ref<"readonly" | "password" | "editing">("editing");
const cashuInitializedFromFetch = ref(false);
/** LNURL challenge completion targets Cashu confirm-edit instead of wallet reveal. */
const lnurlRevealForCashuEdit = ref(false);
/** Smart paste vs SamRock tab (paste first - same as create store). */
const walletSetupTab = ref<"paste" | "samrock">("paste");

const isUnsetWalletType = computed(() => {
  const w = props.walletType;
  return w === null || w === undefined || w === "";
});

const isCashuFlow = computed(() => {
  if (props.walletType === "cashu") return true;
  if (switchToCashuIntent.value) return true;
  return false;
});

const showWalletSetupForm = computed(() => {
  if (viewMode.value !== "create" && viewMode.value !== "editing") return false;
  if (isCashuFlow.value) return false;
  const wt = props.walletType;
  if (wt === "aqua_boltz" || wt === "blink" || wt === "nwc") return true;
  if (isUnsetWalletType.value) return true;
  return false;
});

/** SamRock can (re)provision Aqua/Bull for any non-Cashu store (backend allows the Blink/NWC → Aqua switch). */
const showWalletSetupTabs = computed(() => !isCashuFlow.value);

const showAquaDescriptorWarnings = computed(() => {
  if (props.walletType === "nwc" || props.walletType === "blink") return false;
  if (props.walletType === "aqua_boltz") return true;
  return form.type === "aqua_descriptor";
});

function maskCashuMintUrl(url: string): string {
  const u = (url || "").trim();
  if (!u) return "-";
  try {
    const parsed = new URL(u);
    const path = parsed.pathname && parsed.pathname !== "/" ? " ···" : "";
    return `${parsed.hostname}${path}`;
  } catch {
    return "···";
  }
}

function maskCashuLightningAddress(addr: string): string {
  const a = (addr || "").trim();
  if (!a) return "-";
  const i = a.indexOf("@");
  if (i <= 0) return "···";
  const local = a.slice(0, i);
  const domain = a.slice(i + 1);
  const showLocal = local.length <= 2 ? "•••" : `${local.slice(0, 2)}···`;
  return `${showLocal}@${domain}`;
}

const emit = defineEmits<{
  submitted: [];
  cancel: [];
}>();

const { t } = useI18n();

const walletTypeLabel = computed(() => {
  const w = props.walletType;
  if (w === null || w === undefined || w === "")
    return t("stores.wallet_type_not_set");
  if (w === "aqua_boltz") {
    const brand = props.walletBrand ?? readonlyAquaWalletBrand.value;
    return brand === "bull"
      ? t("create_store.wallet_type_bull")
      : t("create_store.wallet_type_aqua");
  }
  if (w === "blink") return t("create_store.wallet_type_blink");
  if (w === "cashu") return t("create_store.wallet_type_cashu");
  if (w === "nwc") return t("create_store.wallet_type_nwc");
  return String(w);
});

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

type ViewMode = "readonly" | "password" | "editing" | "create";

type WalletConnectionFormType = "blink" | "aqua_descriptor" | "nwc";

function defaultWalletConnectionType(
  walletType: Props["walletType"],
  existing?: Props["existingConnection"],
): WalletConnectionFormType {
  if (existing?.type) {
    return existing.type as WalletConnectionFormType;
  }
  if (walletType === "nwc") return "nwc";
  if (walletType === "blink") return "blink";
  return "aqua_descriptor";
}

/** Pending = user can still use SamRock QR or finish manual descriptor; do not lock to readonly. */
function initialWalletViewMode(conn: Props["existingConnection"]): ViewMode {
  if (!conn) {
    return "create";
  }
  if (conn.status === "pending") {
    return "create";
  }

  return "readonly";
}

const viewMode = ref<ViewMode>(initialWalletViewMode(props.existingConnection));

const passwordInput = ref("");
const passwordError = ref("");
const revealing = ref(false);

const hasLightningLogin = computed(() => !!authStore.user?.has_lightning_login);
const hasNostrLogin = computed(() => !!authStore.user?.has_nostr_login);
/** Legacy email/password accounts; recovery-phrase users have no password. */
const canUsePasswordLogin = computed(
  () => authStore.user?.can_use_password_login ?? true,
);
const showNostrRevealModal = ref(false);
const showLnurlRevealModal = ref(false);
const lnurlRevealUrl = ref("");
const lnurlRevealK1 = ref("");
const lnurlRevealLoading = ref(false);
const lnurlRevealError = ref("");
const lnurlRevealPolling = ref(false);
let lnurlRevealPollingInterval: number | null = null;

watch(
  () => props.walletType,
  () => {
    if (props.walletType !== "cashu") {
      cashuInitializedFromFetch.value = false;
    }
  },
);

const autoSamrockConsumed = ref(false);

const samrockOtp = ref("");
const samrockExpiresAt = ref<string | null>(null);
const samrockQrObjectUrl = ref<string | null>(null);
const samrockBusy = ref(false);
const samrockPollStatus = ref("");
const samrockErrorMessage = ref("");
let samrockPollInterval: number | null = null;

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
  if (samrockOtp.value) {
    try {
      await walletApi.samrock.deleteOtp(props.storeId, samrockOtp.value);
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
  if (route.query.samrock) {
    const q = { ...route.query };
    delete q.samrock;
    router.replace({ path: route.path, query: q });
  }

  samrockBusy.value = true;
  samrockErrorMessage.value = "";
  revokeSamRockQr();
  samrockOtp.value = "";
  stopSamRockPolling();

  try {
    const d = await walletApi.samrock.createOtp(props.storeId, {
      btc: true,
      btcln: true,
      lbtc: false,
      expires_in_seconds: 300,
    });
    const otp = d.otp ?? "";
    if (!otp) {
      samrockErrorMessage.value = t("stores.samrock_error");
      samrockBusy.value = false;
      return;
    }
    samrockOtp.value = otp;
    samrockExpiresAt.value = d.expires_at ?? null;

    const qrBlob = await walletApi.samrock.otpQr(props.storeId, otp, { format: "png" });
    samrockQrObjectUrl.value = URL.createObjectURL(qrBlob);
    samrockBusy.value = false;
    samrockPollStatus.value = "pending";

    const pollOnce = async () => {
      if (!samrockOtp.value) return;
      try {
        const st = await walletApi.samrock.otpStatus(props.storeId, samrockOtp.value);
        const status = st.status ?? "";
        samrockPollStatus.value = status;
        if (status === "success") {
          stopSamRockPolling();
          await walletApi.samrock.complete(props.storeId, { otp: samrockOtp.value });
          revokeSamRockQr();
          samrockOtp.value = "";
          emit("submitted");
        } else if (status === "error") {
          stopSamRockPolling();
          samrockErrorMessage.value =
            st.error_message ?? t("stores.samrock_error");
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

const submitting = ref(false);
const testing = ref(false);
const errors = reactive<Record<string, string>>({});
const testResult = ref<{
  success: boolean;
  message: string;
  requires_manual_config?: boolean;
} | null>(null);

const form = reactive({
  type: defaultWalletConnectionType(
    props.walletType,
    props.existingConnection,
  ),
  secret: "",
});

const aquaWalletBrand = ref<AquaBoltzWalletBrand>("aqua");

const detectedAquaWalletBrand = computed((): AquaBoltzWalletBrand | null =>
  detectWalletBrandFromDescriptor(form.secret),
);

const walletPasteDetection = computed(() =>
  detectWalletConnectionInput(form.secret ?? ""),
);

/** Stable brand for read-only view (no mutable chip state). */
const readonlyAquaWalletBrand = computed((): AquaBoltzWalletBrand => {
  if (props.walletBrand === "bull" || props.walletBrand === "aqua") {
    return props.walletBrand;
  }
  if (
    props.existingConnection?.brand === "bull" ||
    props.existingConnection?.brand === "aqua"
  ) {
    return props.existingConnection.brand;
  }
  if (props.existingConnection?.configuration_source === "samrock") {
    return "aqua";
  }
  return detectedAquaWalletBrand.value ?? "aqua";
});

watch(detectedAquaWalletBrand, (detected) => {
  if (detected && viewMode.value !== "readonly") {
    aquaWalletBrand.value = detected;
  }
});

function startSwitchToCashu() {
  switchToCashuIntent.value = true;
  cashuBetaAccepted.value = false;
}

const nostrRevealConfirmPurpose = computed(() =>
  props.walletType === "cashu" && cashuSectionMode.value === "password"
    ? "cashu_edit"
    : "wallet_reveal",
);

// Cashu settings UI (wallet_type=cashu) - configured directly via BTCPay plugin, no wallet_connections secrets.
const cashuLoading = ref(false);
const cashuSubmitting = ref(false);
const cashuErrorMessage = ref("");
const cashuErrors = reactive<Record<string, string>>({});
const cashuForm = reactive({
  mint_url: DEFAULT_CASHU_MINT_URL,
  lightning_address: "",
  trusted_mint_urls: "",
  max_melt_fee_reserve_sats: "",
  max_melt_fee_reserve_percent_of_minted: "",
});
const cashuOriginal = reactive({
  mint_url: DEFAULT_CASHU_MINT_URL,
  lightning_address: "",
  trusted_mint_urls: "",
  max_melt_fee_reserve_sats: "",
  max_melt_fee_reserve_percent_of_minted: "",
});

const cashuBetaAccepted = ref(false);

/** Apply Cashu mint/LN fields from smart-paste detection (same rules as Create.vue). */
function syncCashuFieldsFromPasteDetection() {
  const det = walletPasteDetection.value;
  if (det.kind !== "cashu" && det.kind !== "cashu_wallet_nwc") {
    return;
  }
  if (det.cashuMintUrl) {
    cashuForm.mint_url = det.cashuMintUrl;
  } else if (!(cashuForm.mint_url ?? "").trim()) {
    cashuForm.mint_url = DEFAULT_CASHU_MINT_URL;
  }
  if (det.cashuLightningAddress) {
    cashuForm.lightning_address = det.cashuLightningAddress;
  }
}

watch(walletPasteDetection, (det) => {
  if (det.kind === "cashu") {
    syncCashuFieldsFromPasteDetection();
  }
});

const cashuMaxMeltSatsDisplay = computed(() =>
  String(cashuForm.max_melt_fee_reserve_sats ?? "").trim(),
);
const cashuMaxMeltPercentDisplay = computed(() =>
  String(cashuForm.max_melt_fee_reserve_percent_of_minted ?? "").trim(),
);

function cashuOptionalNumberPayload(
  val: string | number | null | undefined,
): number | null {
  if (val === null || val === undefined) return null;
  const s = String(val).trim();
  if (s === "") return null;
  const n = Number(s);
  return Number.isFinite(n) ? n : null;
}

const canSaveCashu = computed(() => {
  const mintUrl = (cashuForm.mint_url ?? "").trim();
  const lnAddress = (cashuForm.lightning_address ?? "").trim();
  if (!mintUrl || !mintUrl.startsWith("https://")) return false;
  if (!isValidCashuLightningAddress(lnAddress)) return false;
  if (!cashuBetaAccepted.value) return false;
  return true;
});

watch(switchToCashuIntent, (on) => {
  if (on) cashuBetaAccepted.value = false;
});

watch(cashuSectionMode, (m, prev) => {
  if (m === "editing" && (prev === "password" || prev === "readonly")) {
    cashuBetaAccepted.value = false;
  }
});

function resetCashuErrors() {
  Object.keys(cashuErrors).forEach((k) => delete cashuErrors[k]);
  cashuErrorMessage.value = "";
}

async function fetchCashuSettings() {
  cashuLoading.value = true;
  resetCashuErrors();

  try {
    const d = await walletApi.cashu.getSettings(props.storeId);

    const mintFromApi = (d.mint_url ?? "").trim();
    cashuForm.mint_url = mintFromApi || DEFAULT_CASHU_MINT_URL;
    cashuForm.lightning_address = d.lightning_address ?? "";
    cashuForm.trusted_mint_urls =
      typeof d.trusted_mint_urls === "string" ? d.trusted_mint_urls : "";
    cashuForm.max_melt_fee_reserve_sats =
      d.max_melt_fee_reserve_sats != null && d.max_melt_fee_reserve_sats !== ""
        ? String(d.max_melt_fee_reserve_sats)
        : "";
    cashuForm.max_melt_fee_reserve_percent_of_minted =
      d.max_melt_fee_reserve_percent_of_minted != null &&
      d.max_melt_fee_reserve_percent_of_minted !== ""
        ? String(d.max_melt_fee_reserve_percent_of_minted)
        : "";

    cashuOriginal.mint_url = cashuForm.mint_url;
    cashuOriginal.lightning_address = cashuForm.lightning_address;
    cashuOriginal.trusted_mint_urls = cashuForm.trusted_mint_urls;
    cashuOriginal.max_melt_fee_reserve_sats =
      cashuForm.max_melt_fee_reserve_sats;
    cashuOriginal.max_melt_fee_reserve_percent_of_minted =
      cashuForm.max_melt_fee_reserve_percent_of_minted;

    if (
      props.walletType === "cashu" &&
      !switchToCashuIntent.value &&
      !cashuInitializedFromFetch.value
    ) {
      cashuInitializedFromFetch.value = true;
      const has =
        (cashuForm.mint_url || "").trim() &&
        (cashuForm.lightning_address || "").trim();
      cashuSectionMode.value = has ? "readonly" : "editing";
    }
  } catch (err: any) {
    cashuErrorMessage.value =
      err.response?.data?.message || "Failed to load Cashu settings";
  } finally {
    cashuLoading.value = false;
    syncCashuFieldsFromPasteDetection();
  }
}

async function handleSaveCashu() {
  if (!canSaveCashu.value) return;

  cashuSubmitting.value = true;
  resetCashuErrors();

  try {
    const payload = {
      mint_url: cashuForm.mint_url,
      lightning_address: cashuForm.lightning_address,
      enabled: true,
      unit: "sat",
      trusted_mint_urls:
        (cashuForm.trusted_mint_urls ?? "").trim() === ""
          ? null
          : String(cashuForm.trusted_mint_urls).trim(),
      max_melt_fee_reserve_sats: cashuOptionalNumberPayload(
        cashuForm.max_melt_fee_reserve_sats,
      ),
      max_melt_fee_reserve_percent_of_minted: cashuOptionalNumberPayload(
        cashuForm.max_melt_fee_reserve_percent_of_minted,
      ),
    };

    const d = await walletApi.cashu.updateSettings(props.storeId, payload);

    cashuForm.mint_url = d.mint_url ?? cashuForm.mint_url;
    cashuForm.lightning_address =
      d.lightning_address ?? cashuForm.lightning_address;
    cashuForm.trusted_mint_urls =
      typeof d.trusted_mint_urls === "string" ? d.trusted_mint_urls : "";
    cashuForm.max_melt_fee_reserve_sats =
      d.max_melt_fee_reserve_sats != null && d.max_melt_fee_reserve_sats !== ""
        ? String(d.max_melt_fee_reserve_sats)
        : "";
    cashuForm.max_melt_fee_reserve_percent_of_minted =
      d.max_melt_fee_reserve_percent_of_minted != null &&
      d.max_melt_fee_reserve_percent_of_minted !== ""
        ? String(d.max_melt_fee_reserve_percent_of_minted)
        : "";

    cashuOriginal.mint_url = cashuForm.mint_url;
    cashuOriginal.lightning_address = cashuForm.lightning_address;
    cashuOriginal.trusted_mint_urls = cashuForm.trusted_mint_urls;
    cashuOriginal.max_melt_fee_reserve_sats =
      cashuForm.max_melt_fee_reserve_sats;
    cashuOriginal.max_melt_fee_reserve_percent_of_minted =
      cashuForm.max_melt_fee_reserve_percent_of_minted;

    switchToCashuIntent.value = false;
    cashuInitializedFromFetch.value = true;
    cashuSectionMode.value = "readonly";
    emit("submitted");
  } catch (err: any) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      const validationErrors = err.response.data.errors || {};
      Object.keys(validationErrors).forEach((key) => {
        const v = validationErrors[key];
        const msg = Array.isArray(v) ? v[0] : v;
        if (key === "cashu") {
          cashuErrorMessage.value = msg;
        } else {
          cashuErrors[key] = msg;
        }
      });
    } else {
      cashuErrorMessage.value =
        err.response?.data?.message || "Failed to save Cashu settings";
    }
  } finally {
    cashuSubmitting.value = false;
  }
}

watch(
  () => [props.walletType, switchToCashuIntent.value] as const,
  ([wt, cashuIntent]) => {
    const cashuActive = wt === "cashu" || cashuIntent;
    if (cashuActive) {
      fetchCashuSettings();
    }
    if (wt === "aqua_boltz") {
      form.type = "aqua_descriptor";
    }
  },
  { immediate: true },
);

watch(
  () => props.storeId,
  () => {
    switchToCashuIntent.value = false;
    cashuSectionMode.value = "editing";
    cashuInitializedFromFetch.value = false;
    walletSetupTab.value = "paste";
    lnurlRevealForCashuEdit.value = false;
    cashuBetaAccepted.value = false;
  },
);

watch(
  () => props.existingConnection,
  (conn: any) => {
    if (!conn) {
      viewMode.value = "create";

      return;
    }
    if (viewMode.value !== "editing") {
      form.type = (conn.type || "blink") as
        | "blink"
        | "aqua_descriptor"
        | "nwc";
    }
    if (conn.status === "pending") {
      viewMode.value = "create";
    } else if (viewMode.value === "create") {
      viewMode.value = "readonly";
    }
  },
  { immediate: true },
);

watch(
  () => [props.autoSamrock, showWalletSetupForm.value, showWalletSetupTabs.value] as const,
  async () => {
    if (!props.autoSamrock || autoSamrockConsumed.value) return;
    if (!showWalletSetupForm.value || !showWalletSetupTabs.value) return;
    autoSamrockConsumed.value = true;
    walletSetupTab.value = "samrock";
    await nextTick();
    startSamRockPairing();
  },
  { flush: "post" },
);

function formatStatus(status: string): string {
  const statusMap: Record<string, string> = {
    pending: "Pending",
    needs_support: "Needs Support",
    connected: "Connected",
  };
  return statusMap[status] || status;
}

function getStatusColorClass(status: string): string {
  switch (status) {
    case "connected":
      return "text-green-400";
    case "needs_support":
      return "text-blue-400";
    case "pending":
      return "text-yellow-400";
    default:
      return "text-gray-400";
  }
}

function formatLastChangeDate(dateString: string): string {
  if (!dateString) return "";
  return new Date(dateString).toLocaleString(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

function closeLnurlRevealModal() {
  if (lnurlRevealPollingInterval != null) {
    window.clearInterval(lnurlRevealPollingInterval);
    lnurlRevealPollingInterval = null;
  }
  lnurlRevealPolling.value = false;
  showLnurlRevealModal.value = false;
  lnurlRevealK1.value = "";
  lnurlRevealUrl.value = "";
  lnurlRevealError.value = "";
  lnurlRevealForCashuEdit.value = false;
}

async function fetchRevealChallengeAndOpen(): Promise<boolean> {
  try {
    const res = await api.post("/lnurl-auth/reveal-confirm-challenge");
    const raw = res.data ?? {};
    const data =
      typeof raw === "object" && raw !== null && "data" in raw
        ? (raw as { data: { k1?: string; lnurl?: string } }).data
        : raw;
    const k1 = data?.k1 ?? (data as { K1?: string })?.K1;
    const lnurl =
      data?.lnurl ?? (data as { lnurlAuthUrl?: string })?.lnurlAuthUrl;
    if (!k1 || !lnurl) {
      lnurlRevealError.value = t("auth.error_occurred");
      return false;
    }
    lnurlRevealK1.value = k1;
    lnurlRevealUrl.value = lnurl;
    showLnurlRevealModal.value = true;
    lnurlRevealPolling.value = true;
    const startTime = Date.now();
    const doPoll = async () => {
      if (Date.now() - startTime > 300000) {
        lnurlRevealError.value = t("account.challenge_expired");
        closeLnurlRevealModal();
        return;
      }
      try {
        const statusRes = await api.get(
          `/lnurl-auth/challenge-status/${k1}?_=${Date.now()}`,
        );
        const sRaw = statusRes.data ?? {};
        const sData =
          typeof sRaw === "object" && sRaw !== null && "data" in sRaw
            ? (sRaw as { data: { status?: string } }).data
            : sRaw;
        const status = (sData as { status?: string })?.status;
        if (status === "reveal_confirmed") {
          if (lnurlRevealPollingInterval != null)
            window.clearInterval(lnurlRevealPollingInterval);
          lnurlRevealPollingInterval = null;
          lnurlRevealPolling.value = false;
          showLnurlRevealModal.value = false;
          revealing.value = true;
          const forCashuEdit = lnurlRevealForCashuEdit.value;
          lnurlRevealForCashuEdit.value = false;
          try {
            if (forCashuEdit) {
              await walletApi.cashu.confirmEdit(props.storeId, {
                confirm_via_lnurl: true,
              });
              cashuSectionMode.value = "editing";
            } else {
              const reveal = await walletApi.connection.reveal(props.storeId, {
                confirm_via_lnurl: true,
              });
              form.type = (reveal.type ||
                props.existingConnection?.type ||
                "blink") as "blink" | "aqua_descriptor";
              form.secret = reveal.secret || "";
              sanitizeSecretForDeclaredType();
              syncWalletFormAquaTabAfterReveal();
              viewMode.value = "editing";
            }
          } catch (err: any) {
            lnurlRevealError.value =
              err.response?.data?.errors?.password?.[0] ||
              err.response?.data?.message ||
              t("stores.invalid_password");
            showLnurlRevealModal.value = true;
          } finally {
            revealing.value = false;
          }
        } else if (status === "expired" || status === "error") {
          lnurlRevealError.value =
            status === "expired"
              ? t("account.challenge_expired")
              : (sData as { message?: string })?.message ||
                t("auth.error_occurred");
        }
      } catch {
        // keep polling
      }
    };
    doPoll();
    lnurlRevealPollingInterval = window.setInterval(doPoll, 1000);
    return true;
  } catch (err: any) {
    lnurlRevealError.value =
      err.response?.data?.error || t("auth.error_occurred");
    return false;
  }
}

async function handleConfirmWithLightning() {
  lnurlRevealForCashuEdit.value = false;
  lnurlRevealLoading.value = true;
  lnurlRevealError.value = "";
  try {
    await fetchRevealChallengeAndOpen();
  } finally {
    lnurlRevealLoading.value = false;
  }
}

async function handleCashuEditWithLightning() {
  lnurlRevealForCashuEdit.value = true;
  lnurlRevealLoading.value = true;
  lnurlRevealError.value = "";
  try {
    await fetchRevealChallengeAndOpen();
  } finally {
    lnurlRevealLoading.value = false;
  }
}

async function requestNewRevealChallenge() {
  if (lnurlRevealPollingInterval != null) {
    window.clearInterval(lnurlRevealPollingInterval);
    lnurlRevealPollingInterval = null;
  }
  lnurlRevealPolling.value = false;
  lnurlRevealError.value = "";
  await fetchRevealChallengeAndOpen();
}

function onNostrRevealSuccess(rawPayload?: unknown) {
  // NostrAuthModal emits `unknown`; narrow to the reveal payload shape.
  const payload = (rawPayload ?? undefined) as
    | { secret?: string; type?: string; ok?: boolean }
    | undefined;
  showNostrRevealModal.value = false;
  if (props.walletType === "cashu" && cashuSectionMode.value === "password") {
    cashuSectionMode.value = "editing";
    return;
  }
  if (payload?.secret) {
    form.secret = payload.secret;
    form.type = (payload.type || props.existingConnection?.type || "blink") as
      | "blink"
      | "aqua_descriptor";
    sanitizeSecretForDeclaredType();
    syncWalletFormAquaTabAfterReveal();
    viewMode.value = "editing";
  }
}

onUnmounted(() => {
  if (lnurlRevealPollingInterval != null)
    window.clearInterval(lnurlRevealPollingInterval);
  stopSamRockPolling();
  revokeSamRockQr();
});

function startWalletConnectionEdit() {
  passwordError.value = "";
  passwordInput.value = "";
  if (!canUsePasswordLogin.value) {
    void handleConfirmPassword();
    return;
  }
  viewMode.value = "password";
}

function startCashuConnectionEdit() {
  passwordError.value = "";
  passwordInput.value = "";
  if (!canUsePasswordLogin.value) {
    void handleCashuConfirmPassword();
    return;
  }
  cashuSectionMode.value = "password";
}

async function handleConfirmPassword() {
  if (canUsePasswordLogin.value && !passwordInput.value.trim()) return;
  passwordError.value = "";
  revealing.value = true;
  try {
    const payload = canUsePasswordLogin.value
      ? { password: passwordInput.value }
      : {};
    const reveal = await walletApi.connection.reveal(props.storeId, payload);
    form.type = (reveal.type ||
      props.existingConnection?.type ||
      defaultWalletConnectionType(props.walletType, props.existingConnection)) as WalletConnectionFormType;
    form.secret = reveal.secret || "";
    sanitizeSecretForDeclaredType();
    syncWalletFormAquaTabAfterReveal();
    passwordInput.value = "";
    viewMode.value = "editing";
  } catch (err: any) {
    const msg =
      err.response?.data?.errors?.password?.[0] ||
      err.response?.data?.message ||
      t("stores.invalid_password");
    passwordError.value = msg;
  } finally {
    revealing.value = false;
  }
}

async function handleCashuConfirmPassword() {
  if (canUsePasswordLogin.value && !passwordInput.value.trim()) return;
  passwordError.value = "";
  revealing.value = true;
  try {
    const payload = canUsePasswordLogin.value
      ? { password: passwordInput.value }
      : {};
    await walletApi.cashu.confirmEdit(props.storeId, payload);
    passwordInput.value = "";
    cashuSectionMode.value = "editing";
  } catch (err: any) {
    const msg =
      err.response?.data?.errors?.password?.[0] ||
      err.response?.data?.message ||
      t("stores.invalid_password");
    passwordError.value = msg;
  } finally {
    revealing.value = false;
  }
}

function handleCancelEdit() {
  switchToCashuIntent.value = false;
  walletSetupTab.value = "paste";
  form.secret = "";
  if (props.existingConnection) {
    form.type = defaultWalletConnectionType(
      props.walletType,
      props.existingConnection,
    );
  }
  viewMode.value = "readonly";
  testResult.value = null;
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function validateDescriptor(descriptor: string): boolean {
  if (!isValidAquaBoltzDescriptor(descriptor)) {
    return false;
  }
  const detected = detectWalletBrandFromDescriptor(descriptor);
  if (detected && detected !== aquaWalletBrand.value) {
    return false;
  }
  return true;
}

/** Drop Blink-shaped secrets when the form expects a descriptor (and vice versa). */
function sanitizeSecretForDeclaredType() {
  const raw = form.secret.trim();
  if (!raw) return;
  if (form.type === "aqua_descriptor") {
    if (
      validateBlinkConnectionString(raw) ||
      raw.toLowerCase().startsWith("type=blink")
    ) {
      form.secret = "";
    }
  } else if (form.type === "blink" && validateDescriptor(raw)) {
    form.secret = "";
  }
}

function syncWalletFormAquaTabAfterReveal() {
  if (form.type !== "aqua_descriptor") {
    walletSetupTab.value = "paste";
    return;
  }
  const hasDescriptor = validateDescriptor(form.secret);
  walletSetupTab.value =
    hasDescriptor || !showWalletSetupTabs.value ? "paste" : "samrock";
}

watch(
  () => form.type,
  (newType, oldType) => {
    if (oldType === undefined || !oldType || newType === oldType) return;
    if (newType === "aqua_descriptor" && oldType === "blink") {
      const s = form.secret.trim();
      if (
        s &&
        (s.toLowerCase().startsWith("type=blink") ||
          validateBlinkConnectionString(s))
      ) {
        form.secret = "";
      }
    } else if (newType === "blink" && oldType === "aqua_descriptor") {
      const s = form.secret.trim();
      if (s && validateDescriptor(s)) {
        form.secret = "";
      }
    }
    if (newType === "aqua_descriptor" && showWalletSetupTabs.value) {
      walletSetupTab.value = "paste";
    }
  },
);

async function handleTestConnection() {
  if (!form.secret.trim()) {
    testResult.value = {
      success: false,
      message: "Please enter a connection string or descriptor first.",
    };
    return;
  }
  if (form.type === "blink" && !validateBlinkConnectionString(form.secret)) {
    testResult.value = {
      success: false,
      message: "Invalid Blink connection string format.",
    };
    return;
  }
  if (form.type === "aqua_descriptor" && !validateDescriptor(form.secret)) {
    testResult.value = {
      success: false,
      message: t("create_store.invalid_descriptor_format"),
    };
    return;
  }
  if (form.type === "nwc" && isCashuWalletNwcUri(form.secret)) {
    testResult.value = {
      success: false,
      message: t("stores.wallet_cashu_nwc_rejected"),
    };
    return;
  }
  if (form.type === "nwc" && !validateNwcUri(form.secret)) {
    testResult.value = {
      success: false,
      message: t("stores.nwc_invalid_connection"),
    };
    return;
  }
  testing.value = true;
  testResult.value = null;
  try {
    const result = await walletApi.connection.test(props.storeId, {
      connection_string: formatConnectionStringForApi(form.secret, form.type),
      crypto_code: "BTC",
    });
    testResult.value = {
      success: result.success ?? false,
      message: result.message || "Connection test completed",
      requires_manual_config: result.requires_manual_config ?? false,
    };
  } catch (err: any) {
    testResult.value = {
      success: false,
      message:
        err.response?.data?.message ||
        "Failed to test connection. Please try again.",
    };
  } finally {
    testing.value = false;
  }
}

function formatConnectionStringForApi(value: string, type: string): string {
  if (type !== "nwc") {
    return value.trim();
  }
  const trimmed = value.trim();
  if (trimmed.toLowerCase().startsWith("type=nwc;")) {
    return trimmed;
  }
  const normalized = trimmed.replace(
    "nostr+walletconnect://",
    "nostr+walletconnect:",
  );
  return `type=nwc;key=${normalized}`;
}

function onDetectCashuFromPaste(_payload: {
  mintUrl: string;
  lightningAddress: string | null;
}) {
  syncCashuFieldsFromPasteDetection();
  if (walletPasteDetection.value.kind === "cashu_wallet_nwc") {
    return;
  }
  startSwitchToCashu();
}

async function handleSubmit() {
  submitting.value = true;
  Object.keys(errors).forEach((k) => delete errors[k]);
  testResult.value = null;

  if (form.type === "blink" && !validateBlinkConnectionString(form.secret)) {
    errors.secret = "Invalid Blink connection string format.";
    submitting.value = false;
    return;
  }
  if (form.type === "aqua_descriptor" && !validateDescriptor(form.secret)) {
    errors.secret = t("create_store.invalid_descriptor_format");
    submitting.value = false;
    return;
  }
  if (form.type === "nwc" && isCashuWalletNwcUri(form.secret)) {
    errors.secret = t("stores.wallet_cashu_nwc_rejected");
    submitting.value = false;
    return;
  }
  if (form.type === "nwc" && !validateNwcUri(form.secret)) {
    errors.secret = t("stores.nwc_invalid_connection");
    submitting.value = false;
    return;
  }

  try {
    const secretPayload =
      form.type === "nwc" ? normalizeNwcUri(form.secret) : form.secret.trim();

    await walletApi.connection.create(props.storeId, {
      type: form.type,
      secret: secretPayload,
    });
    emit("submitted");
  } catch (err: any) {
    if (err.response?.status === 422) {
      const validationErrors = err.response.data.errors || {};
      Object.keys(validationErrors).forEach((key) => {
        errors[key] = Array.isArray(validationErrors[key])
          ? validationErrors[key][0]
          : validationErrors[key];
      });
    } else {
      errors.general =
        err.response?.data?.message || "Failed to save wallet connection";
    }
  } finally {
    submitting.value = false;
  }
}
</script>

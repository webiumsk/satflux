<template>
    <div
        v-if="walletType === 'cashu'"
        class="bg-gray-900/50 border border-gray-700 rounded-2xl p-8"
    >
        <h3 class="text-sm font-bold text-indigo-400 mb-6 uppercase tracking-wider">
            {{ t('stores.cashu_settings_title') }}
        </h3>

        <div class="mb-4 p-4 rounded-xl border border-green-500/20 bg-green-500/10 text-sm">
            <span class="font-medium text-green-400">
                {{ t('stores.connected') }}
            </span>
            <span class="text-gray-300 ml-2">{{ t('stores.cashu_connected_via_plugin') }}</span>
        </div>

        <div class="mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 space-y-2">
            <p class="text-sm font-medium text-amber-300">
                {{ t('stores.cashu_warning_title') }}
            </p>
            <ul class="text-sm text-amber-400 list-disc list-inside space-y-1.5">
                <li>{{ t('stores.cashu_warning_https') }}</li>
                <li>{{ t('stores.cashu_warning_mint_reachable') }}</li>
                <li>{{ t('stores.cashu_warning_ln_address') }}</li>
            </ul>
        </div>

        <div v-if="cashuLoading" class="flex items-center justify-center py-10">
            <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <form
            v-else
            @submit.prevent="handleSaveCashu"
            class="space-y-6"
        >
            <div>
                <label for="cashu-mint-url" class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider">
                    {{ t('stores.cashu_mint_url_label') }}
                </label>
                <input
                    id="cashu-mint-url"
                    v-model="cashuForm.mint_url"
                    type="text"
                    class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3 font-mono"
                    :placeholder="t('stores.cashu_mint_url_placeholder')"
                />
                <p class="mt-2 text-sm text-gray-500 leading-relaxed whitespace-pre-line">{{ t('stores.cashu_mint_url_hint') }}</p>
                <p v-if="cashuErrors.mint_url" class="mt-2 text-sm text-red-400">{{ cashuErrors.mint_url }}</p>
            </div>

            <div>
                <label for="cashu-lightning-address" class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider">
                    {{ t('stores.cashu_lightning_address_label') }}
                </label>
                <input
                    id="cashu-lightning-address"
                    v-model="cashuForm.lightning_address"
                    type="text"
                    class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
                    :placeholder="t('stores.cashu_lightning_address_placeholder')"
                />
                <p class="mt-2 text-sm text-gray-500 leading-relaxed">{{ t('stores.cashu_lightning_address_hint') }}</p>
                <p v-if="cashuErrors.lightning_address" class="mt-2 text-sm text-red-400">{{ cashuErrors.lightning_address }}</p>
            </div>

            <p class="text-sm text-gray-500 leading-relaxed border-t border-gray-700 pt-4">
                {{ t('stores.cashu_footer_help') }}
            </p>

            <div v-if="cashuErrorMessage" class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
                {{ cashuErrorMessage }}
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-between items-center gap-3 pt-4 border-t border-gray-700">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all w-full sm:w-auto"
                    :disabled="cashuSubmitting"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="cashuSubmitting || !canSaveCashu"
                    class="px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all w-full sm:w-auto"
                >
                    <span v-if="cashuSubmitting">{{ t('common.loading') }}</span>
                    <span v-else>{{ t('stores.cashu_save_settings') }}</span>
                </button>
            </div>
        </form>
    </div>

    <div v-else class="space-y-8">
        <!-- SamRock QR (Aqua + Boltz stores, no connection yet or pending manual) -->
        <div
            v-if="samrockPanelVisible"
            class="bg-gray-900/50 border border-indigo-500/30 rounded-2xl p-8"
        >
            <h3 class="text-sm font-bold text-indigo-400 mb-3 uppercase tracking-wider">
                {{ t('stores.samrock_title') }}
            </h3>
            <p class="text-sm text-gray-400 mb-2 leading-relaxed">{{ t('stores.samrock_description') }}</p>
            <p class="text-xs text-gray-500 mb-6">{{ t('stores.samrock_require_plugin') }}</p>

            <p v-if="samrockErrorMessage && !samrockQrObjectUrl" class="text-red-400 text-sm mb-4">{{ samrockErrorMessage }}</p>

            <div v-if="!samrockOtp && !samrockBusy" class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="px-6 py-3 rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="samrockBusy"
                    @click="startSamRockPairing"
                >
                    {{ t('stores.samrock_generate_qr') }}
                </button>
                <button
                    v-if="samrockErrorMessage"
                    type="button"
                    class="px-6 py-3 rounded-xl text-sm font-medium border border-gray-600 text-gray-300 hover:bg-gray-800"
                    @click="samrockErrorMessage = ''; startSamRockPairing()"
                >
                    {{ t('stores.samrock_try_again') }}
                </button>
            </div>

            <div v-else-if="samrockBusy && !samrockQrObjectUrl" class="flex items-center gap-3 py-6 text-gray-400">
                <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>{{ t('common.loading') }}</span>
            </div>

            <div v-else-if="samrockQrObjectUrl" class="space-y-4">
                <p class="text-sm text-gray-300">{{ t('stores.samrock_waiting_scan') }}</p>
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    <img
                        :src="samrockQrObjectUrl"
                        alt="SamRock QR"
                        class="w-48 h-48 rounded-xl border border-gray-600 bg-white p-2"
                    />
                    <div class="text-sm space-y-2">
                        <p v-if="samrockExpiresAt" class="text-gray-500">
                            {{ t('stores.samrock_expires') }}: {{ formatSamRockExpiry(samrockExpiresAt) }}
                        </p>
                        <p v-if="samrockPollStatus" class="font-mono text-indigo-300">{{ samrockPollStatus }}</p>
                        <p v-if="samrockErrorMessage" class="text-red-400">{{ samrockErrorMessage }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-sm border border-gray-600 text-gray-300 hover:bg-gray-800"
                        @click="cancelSamRockPairing"
                    >
                        {{ t('stores.samrock_cancel') }}
                    </button>
                </div>
            </div>

            <p class="mt-8 pt-6 border-t border-gray-700 text-sm text-gray-500">{{ t('stores.samrock_or_manual') }}</p>
        </div>

        <!-- Read-only: Current Connection + Change button (when connection exists and not editing) -->
        <template v-if="existingConnection && viewMode === 'readonly'">
            <div class="bg-gray-900/50 border border-gray-700 rounded-2xl p-8">
                <h3 class="text-sm font-bold text-indigo-400 mb-6 uppercase tracking-wider">
                    {{ t('stores.current_connection') }}
                </h3>
                <div v-if="existingConnection.configuration_source === 'samrock'" class="mb-6 p-4 rounded-xl border border-green-500/30 bg-green-500/10">
                    <p class="text-sm text-green-300">{{ t('stores.samrock_connected_note') }}</p>
                </div>
                <div v-else-if="existingConnection.type === 'aqua_descriptor'" class="mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10">
                    <p class="text-sm text-amber-400">{{ t('stores.aqua_warning_btcpay') }}</p>
                    <p class="text-sm text-amber-400 mt-2">{{ t('stores.aqua_limits_warning') }}</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="block text-gray-500 text-xs uppercase tracking-wider mb-1">{{ t('stores.type') }}</span>
                        <WalletTypeIcon
                          :type="existingConnection.type"
                          size="lg"
                          :show-label="true"
                          class="font-medium text-white"
                        />
                    </div>
                    <div>
                        <span class="block text-gray-500 text-xs uppercase tracking-wider mb-1">{{ t('stores.status') }}</span>
                        <span class="font-medium" :class="getStatusColorClass(existingConnection.status)">{{ formatStatus(existingConnection.status) }}</span>
                    </div>
                </div>
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <span class="block text-gray-500 text-xs uppercase tracking-wider mb-2">{{ t('stores.masked_secret') }}</span>
                    <div class="font-mono text-gray-300 text-sm break-all bg-gray-800/80 px-4 py-3 rounded-xl border border-gray-700/50">
                        {{ existingConnection.masked_secret || 'N/A' }}
                    </div>
                </div>
                <!-- Last change: date + user ID (integrated into main card) -->
                <div
                    v-if="existingConnection.secret_updated_at || existingConnection.submitted_by_user_id"
                    class="mt-6 pt-6 border-t border-gray-700 flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm text-gray-400"
                >
                    <span v-if="existingConnection.secret_updated_at">
                        <span class="text-gray-500 uppercase tracking-wider">{{ t('stores.last_connection_change') }}:</span>
                        <span class="ml-2 text-gray-300">{{ formatLastChangeDate(existingConnection.secret_updated_at) }}</span>
                    </span>
                    <span v-if="existingConnection.submitted_by_user_id">
                        <span class="text-gray-500 uppercase tracking-wider">{{ t('stores.by_user_id') }}:</span>
                        <span class="ml-2 font-mono text-indigo-300">{{ existingConnection.submitted_by_user_id }}</span>
                    </span>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <button
                        type="button"
                        @click="viewMode = 'password'"
                        class="inline-flex items-center px-6 py-3 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20 transition-all"
                    >
                        {{ t('stores.change_connection') }}
                    </button>
                    <button
                        type="button"
                        @click="$emit('cancel')"
                        class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Password step: before revealing secret -->
        <template v-else-if="existingConnection && viewMode === 'password'">
            <div class="bg-gray-900/50 border border-gray-700 rounded-xl p-6 max-w-md">
                <p class="text-sm text-gray-400 mb-4">{{ t('stores.confirm_password_to_change') }}</p>
                <form @submit.prevent="handleConfirmPassword" class="space-y-4">
                    <div>
                        <label for="wc-password" class="block text-sm font-medium text-gray-300 mb-1">{{ t('account.current_password') }}</label>
                        <input
                            id="wc-password"
                            v-model="passwordInput"
                            type="password"
                            autocomplete="current-password"
                            :placeholder="t('stores.password_placeholder')"
                            class="block w-full rounded-xl border border-gray-600 bg-gray-800 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
                        />
                        <p v-if="passwordError" class="mt-2 text-sm text-red-400">{{ passwordError }}</p>
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="viewMode = 'readonly'; passwordError = ''; passwordInput = ''"
                            class="px-5 py-2.5 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 transition-all"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="revealing || !passwordInput.trim()"
                            class="px-5 py-2.5 border border-transparent rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <span v-if="revealing">{{ t('common.loading') }}</span>
                            <span v-else>{{ t('common.confirm') }}</span>
                        </button>
                    </div>
                </form>
                <div v-if="hasLightningLogin || hasNostrLogin" class="mt-4 pt-4 border-t border-gray-700 space-y-2">
                    <p class="text-sm text-gray-400 mb-2">{{ t('account.or_confirm_with_lightning') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="hasLightningLogin"
                            type="button"
                            :disabled="lnurlRevealLoading || lnurlRevealPolling"
                            @click="handleConfirmWithLightning"
                            class="px-4 py-2 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 hover:bg-indigo-500/10 disabled:opacity-50"
                        >
                            <span v-if="lnurlRevealLoading || lnurlRevealPolling">{{ t('common.loading') }}</span>
                            <span v-else>{{ t('account.confirm_with_lightning_wallet') }}</span>
                        </button>
                        <button
                            v-if="hasNostrLogin"
                            type="button"
                            @click="showNostrRevealModal = true"
                            class="px-4 py-2 border border-amber-500/50 rounded-xl text-sm font-medium text-amber-400 hover:bg-amber-500/10"
                        >
                            🟠 {{ t('auth.nostr_confirm_reveal') }}
                        </button>
                    </div>
                </div>
            </div>
            <!-- LNURL reveal confirm modal -->
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
                :store-id="Number(storeId)"
                @close="showNostrRevealModal = false"
                @success="onNostrRevealSuccess"
            />
        </template>

        <!-- Edit form: type + secret + Test + Save/Cancel (create flow or after password) -->
        <form v-else @submit.prevent="handleSubmit" class="space-y-8">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider">
                    {{ t('create_store.wallet_type_label') }}
                </label>
                <div class="space-y-4">
                    <label
                        class="flex items-start p-5 border rounded-xl cursor-pointer transition-all duration-200 group"
                        :class="form.type === 'blink'
                            ? 'border-indigo-500 bg-indigo-900/10 shadow-lg shadow-indigo-900/20'
                            : 'border-gray-700 bg-gray-800 hover:bg-gray-700/50 hover:border-gray-600'"
                    >
                        <div class="flex items-center h-5 mt-1">
                            <input
                                type="radio"
                                v-model="form.type"
                                value="blink"
                                class="h-4 w-4 text-indigo-600 border-gray-600 focus:ring-indigo-500 bg-gray-700"
                                required
                            />
                        </div>
                        <div class="ml-4">
                            <div class="font-bold text-white text-lg">{{ t('create_store.wallet_type_blink') }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ t('stores.blink_connection_description') }}</div>
                        </div>
                    </label>

                    <label
                        class="flex items-start p-5 border rounded-xl cursor-pointer transition-all duration-200 group"
                        :class="form.type === 'aqua_descriptor'
                            ? 'border-indigo-500 bg-indigo-900/10 shadow-lg shadow-indigo-900/20'
                            : 'border-gray-700 bg-gray-800 hover:bg-gray-700/50 hover:border-gray-600'"
                    >
                        <div class="flex items-center h-5 mt-1">
                            <input
                                type="radio"
                                v-model="form.type"
                                value="aqua_descriptor"
                                class="h-4 w-4 text-indigo-600 border-gray-600 focus:ring-indigo-500 bg-gray-700"
                                required
                            />
                        </div>
                        <div class="ml-4">
                            <div class="font-bold text-white text-lg">{{ t('create_store.wallet_type_aqua') }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ t('stores.aqua_connection_description') }}</div>
                        </div>
                    </label>
                </div>
                <div v-if="form.type === 'aqua_descriptor'" class="mt-4 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10">
                    <p class="text-sm text-amber-400">{{ t('stores.aqua_warning_btcpay') }}</p>
                    <p class="text-sm text-amber-400 mt-2">{{ t('stores.aqua_limits_warning') }}</p>
                </div>
                <p v-if="errors.type" class="mt-2 text-sm text-red-400">{{ errors.type }}</p>
            </div>

            <div>
                <label for="secret" class="block text-sm font-medium text-indigo-300 mb-2 uppercase tracking-wider">
                    {{ form.type === 'blink' ? t('create_store.connection_string') : t('create_store.descriptor') }}
                </label>
                <textarea
                    id="secret"
                    v-model="form.secret"
                    rows="5"
                    class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono p-4"
                    :placeholder="form.type === 'blink'
                        ? 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx'
                        : 'ct(slip77(...),elsh(wpkh(...))))'"
                    required
                ></textarea>
                <div v-if="form.type === 'blink'" class="mt-3 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10">
                    <p class="text-sm text-amber-400 font-medium">
                        {{ t('stores.blink_keys_warning') }}
                        <a href="https://dashboard.blink.sv/" target="_blank" rel="noopener noreferrer" class="underline hover:text-amber-300 ml-1">{{ t('stores.blink_dashboard_link') }}</a>
                    </p>
                </div>
                <div class="mt-3 text-sm text-gray-400 bg-gray-900/30 p-4 rounded-xl border border-gray-700/50">
                    <p class="font-medium text-gray-300 mb-2">{{ t('stores.format_help') }}</p>
                    <div v-if="form.type === 'blink'" class="space-y-1">
                        <p>{{ t('create_store.connection_string_format') }}</p>
                        <p>{{ t('create_store.connection_string_help') }}</p>
                    </div>
                    <div v-else class="space-y-1">
                        <p>{{ t('create_store.descriptor_help') }}</p>
                        <p>{{ t('create_store.descriptor_example') }}</p>
                    </div>
                </div>
                <p v-if="errors.secret" class="mt-2 text-sm text-red-400">{{ errors.secret }}</p>
            </div>

            <div v-if="testResult" class="rounded-xl p-4 border" :class="testResult.success ? 'bg-green-500/10 border-green-500/20' : 'bg-red-500/10 border-red-500/20'">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg v-if="testResult.success" class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <svg v-else class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium" :class="testResult.success ? 'text-green-400' : 'text-red-400'">
                            {{ testResult.message }}
                        </p>
                        <p v-if="testResult.requires_manual_config" class="mt-1 text-sm text-gray-400">
                            {{ t('stores.manual_config_required') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-between items-center gap-4 pt-4 border-t border-gray-700">
                <div class="flex w-full sm:w-auto gap-4">
                    <button
                        type="button"
                        @click="handleTestConnection"
                        :disabled="testing || !form.secret.trim()"
                        class="w-full sm:w-auto px-6 py-3 border border-gray-600 rounded-xl shadow-sm text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        <svg v-if="testing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-400 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ testing ? t('stores.testing') : t('stores.test_connection') }}
                    </button>
                </div>
                <div class="flex w-full sm:w-auto gap-4">
                    <button
                        v-if="existingConnection && viewMode === 'editing'"
                        type="button"
                        @click="handleCancelEdit"
                        class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        @click="$emit('cancel')"
                        class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ submitting ? t('common.loading') : t('stores.save_connection') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';
import WalletTypeIcon from '../WalletTypeIcon.vue';
import LnurlQrModal from '../auth/LnurlQrModal.vue';
import NostrAuthModal from '../auth/NostrAuthModal.vue';

interface Props {
    storeId: string;
    existingConnection?: any;
    walletType?: 'blink' | 'aqua_boltz' | 'cashu' | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    submitted: [];
    cancel: [];
}>();

const { t } = useI18n();
const authStore = useAuthStore();

type ViewMode = 'readonly' | 'password' | 'editing' | 'create';

const viewMode = ref<ViewMode>(props.existingConnection ? 'readonly' : 'create');

const passwordInput = ref('');
const passwordError = ref('');
const revealing = ref(false);

const hasLightningLogin = computed(() => !!authStore.user?.has_lightning_login);
const hasNostrLogin = computed(() => !!authStore.user?.has_nostr_login);
const showNostrRevealModal = ref(false);
const showLnurlRevealModal = ref(false);
const lnurlRevealUrl = ref('');
const lnurlRevealK1 = ref('');
const lnurlRevealLoading = ref(false);
const lnurlRevealError = ref('');
const lnurlRevealPolling = ref(false);
let lnurlRevealPollingInterval: number | null = null;

const samrockPanelVisible = computed(() => {
    if (props.walletType !== 'aqua_boltz') return false;
    if (viewMode.value !== 'create') return false;
    if (!props.existingConnection) return true;
    if (props.existingConnection.configuration_source === 'samrock') return false;
    return props.existingConnection.status === 'pending';
});

const samrockOtp = ref('');
const samrockExpiresAt = ref<string | null>(null);
const samrockQrObjectUrl = ref<string | null>(null);
const samrockBusy = ref(false);
const samrockPollStatus = ref('');
const samrockErrorMessage = ref('');
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
            await api.delete(`/stores/${props.storeId}/samrock/otps/${encodeURIComponent(samrockOtp.value)}`);
        } catch {
            /* ignore */
        }
    }
    samrockOtp.value = '';
    samrockExpiresAt.value = null;
    samrockErrorMessage.value = '';
    samrockPollStatus.value = '';
    samrockBusy.value = false;
}

async function startSamRockPairing() {
    samrockBusy.value = true;
    samrockErrorMessage.value = '';
    revokeSamRockQr();
    samrockOtp.value = '';
    stopSamRockPolling();

    try {
        const res = await api.post(`/stores/${props.storeId}/samrock/otps`, {
            btc: true,
            btcln: true,
            lbtc: false,
            expires_in_seconds: 300,
        });
        const d = res.data?.data ?? {};
        const otp = d.otp ?? '';
        if (!otp) {
            samrockErrorMessage.value = t('stores.samrock_error');
            samrockBusy.value = false;
            return;
        }
        samrockOtp.value = otp;
        samrockExpiresAt.value = d.expires_at ?? null;

        const qrRes = await api.get(`/stores/${props.storeId}/samrock/otps/${encodeURIComponent(otp)}/qr`, {
            responseType: 'blob',
            params: { format: 'png' },
        });
        samrockQrObjectUrl.value = URL.createObjectURL(qrRes.data);
        samrockBusy.value = false;
        samrockPollStatus.value = 'pending';

        const pollOnce = async () => {
            if (!samrockOtp.value) return;
            try {
                const st = await api.get(`/stores/${props.storeId}/samrock/otps/${encodeURIComponent(samrockOtp.value)}`);
                const status = st.data?.data?.status ?? '';
                samrockPollStatus.value = status;
                if (status === 'success') {
                    stopSamRockPolling();
                    await api.post(`/stores/${props.storeId}/samrock/complete`, { otp: samrockOtp.value });
                    revokeSamRockQr();
                    samrockOtp.value = '';
                    emit('submitted');
                } else if (status === 'error') {
                    stopSamRockPolling();
                    samrockErrorMessage.value = st.data?.data?.error_message ?? t('stores.samrock_error');
                }
            } catch {
                /* ignore */
            }
        };

        await pollOnce();
        samrockPollInterval = window.setInterval(pollOnce, 3000);
    } catch (err: any) {
        samrockErrorMessage.value = err.response?.data?.message ?? t('stores.samrock_error');
        samrockBusy.value = false;
    }
}

const submitting = ref(false);
const testing = ref(false);
const errors = reactive<Record<string, string>>({});
const testResult = ref<{ success: boolean; message: string; requires_manual_config?: boolean } | null>(null);

const form = reactive({
    type: (props.existingConnection?.type || 'blink') as 'blink' | 'aqua_descriptor',
    secret: '',
});

// Cashu settings UI (wallet_type=cashu) - configured directly via BTCPay plugin, no wallet_connections secrets.
const cashuLoading = ref(false);
const cashuSubmitting = ref(false);
const cashuErrorMessage = ref('');
const cashuErrors = reactive<Record<string, string>>({});
const cashuForm = reactive({
    mint_url: '',
    lightning_address: '',
    enabled: true,
});
const cashuOriginal = reactive({
    mint_url: '',
    lightning_address: '',
    enabled: true,
});

const canSaveCashu = computed(() => {
    const mintUrl = (cashuForm.mint_url ?? '').trim();
    const lnAddress = (cashuForm.lightning_address ?? '').trim();
    if (!mintUrl || !mintUrl.startsWith('https://')) return false;
    if (!lnAddress.match(/^[^@]+@[^@]+$/)) return false;
    return true;
});

function resetCashuErrors() {
    Object.keys(cashuErrors).forEach((k) => delete cashuErrors[k]);
    cashuErrorMessage.value = '';
}

async function fetchCashuSettings() {
    cashuLoading.value = true;
    resetCashuErrors();

    try {
        const response = await api.get(`/stores/${props.storeId}/cashu/settings`);
        const d = response.data?.data ?? {};

        cashuForm.mint_url = d.mint_url ?? '';
        cashuForm.lightning_address = d.lightning_address ?? '';
        cashuForm.enabled = d.enabled ?? true;

        cashuOriginal.mint_url = cashuForm.mint_url;
        cashuOriginal.lightning_address = cashuForm.lightning_address;
        cashuOriginal.enabled = cashuForm.enabled;
    } catch (err: any) {
        cashuErrorMessage.value =
            err.response?.data?.message || 'Failed to load Cashu settings';
    } finally {
        cashuLoading.value = false;
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
        };

        const response = await api.put(`/stores/${props.storeId}/cashu/settings`, payload);
        const d = response.data?.data ?? {};

        cashuForm.mint_url = d.mint_url ?? cashuForm.mint_url;
        cashuForm.lightning_address = d.lightning_address ?? cashuForm.lightning_address;
        cashuForm.enabled = d.enabled ?? cashuForm.enabled;

        cashuOriginal.mint_url = cashuForm.mint_url;
        cashuOriginal.lightning_address = cashuForm.lightning_address;
        cashuOriginal.enabled = cashuForm.enabled;

        emit('submitted');
    } catch (err: any) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            const validationErrors = err.response.data.errors || {};
            Object.keys(validationErrors).forEach((key) => {
                const v = validationErrors[key];
                cashuErrors[key] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            cashuErrorMessage.value =
                err.response?.data?.message || 'Failed to save Cashu settings';
        }
    } finally {
        cashuSubmitting.value = false;
    }
}

watch(
    () => props.walletType,
    (wt: Props['walletType']) => {
        if (wt === 'cashu') {
            fetchCashuSettings();
        }
    },
    { immediate: true }
);

watch(() => props.existingConnection, (conn: any) => {
    if (conn) {
        form.type = (conn.type || 'blink') as 'blink' | 'aqua_descriptor';
        if (viewMode.value === 'create') viewMode.value = 'readonly';
    }
}, { immediate: true });

function formatStatus(status: string): string {
    const statusMap: Record<string, string> = {
        pending: 'Pending',
        needs_support: 'Needs Support',
        connected: 'Connected',
    };
    return statusMap[status] || status;
}

function getStatusColorClass(status: string): string {
    switch (status) {
        case 'connected': return 'text-green-400';
        case 'needs_support': return 'text-blue-400';
        case 'pending': return 'text-yellow-400';
        default: return 'text-gray-400';
    }
}

function formatLastChangeDate(dateString: string): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function closeLnurlRevealModal() {
    if (lnurlRevealPollingInterval != null) {
        window.clearInterval(lnurlRevealPollingInterval);
        lnurlRevealPollingInterval = null;
    }
    lnurlRevealPolling.value = false;
    showLnurlRevealModal.value = false;
    lnurlRevealK1.value = '';
    lnurlRevealUrl.value = '';
    lnurlRevealError.value = '';
}

async function fetchRevealChallengeAndOpen(): Promise<boolean> {
    try {
        const res = await api.post('/lnurl-auth/reveal-confirm-challenge');
        const raw = res.data ?? {};
        const data = typeof raw === 'object' && raw !== null && 'data' in raw ? (raw as { data: { k1?: string; lnurl?: string } }).data : raw;
        const k1 = data?.k1 ?? (data as { K1?: string })?.K1;
        const lnurl = data?.lnurl ?? (data as { lnurlAuthUrl?: string })?.lnurlAuthUrl;
        if (!k1 || !lnurl) {
            lnurlRevealError.value = t('auth.error_occurred');
            return false;
        }
        lnurlRevealK1.value = k1;
        lnurlRevealUrl.value = lnurl;
        showLnurlRevealModal.value = true;
        lnurlRevealPolling.value = true;
        const startTime = Date.now();
        const doPoll = async () => {
            if (Date.now() - startTime > 300000) {
                lnurlRevealError.value = t('account.challenge_expired');
                closeLnurlRevealModal();
                return;
            }
            try {
                const statusRes = await api.get(`/lnurl-auth/challenge-status/${k1}?_=${Date.now()}`);
                const sRaw = statusRes.data ?? {};
                const sData = typeof sRaw === 'object' && sRaw !== null && 'data' in sRaw ? (sRaw as { data: { status?: string } }).data : sRaw;
                const status = (sData as { status?: string })?.status;
                if (status === 'reveal_confirmed') {
                    if (lnurlRevealPollingInterval != null) window.clearInterval(lnurlRevealPollingInterval);
                    lnurlRevealPollingInterval = null;
                    lnurlRevealPolling.value = false;
                    showLnurlRevealModal.value = false;
                    revealing.value = true;
                    try {
                        const response = await api.post(`/stores/${props.storeId}/wallet-connection/reveal`, { confirm_via_lnurl: true });
                        form.type = (response.data.data?.type || props.existingConnection?.type || 'blink') as 'blink' | 'aqua_descriptor';
                        form.secret = response.data.data?.secret || '';
                        viewMode.value = 'editing';
                    } catch (err: any) {
                        lnurlRevealError.value = err.response?.data?.errors?.password?.[0] || err.response?.data?.message || t('stores.invalid_password');
                        showLnurlRevealModal.value = true;
                    } finally {
                        revealing.value = false;
                    }
                } else if (status === 'expired' || status === 'error') {
                    lnurlRevealError.value = status === 'expired' ? t('account.challenge_expired') : (sData as { message?: string })?.message || t('auth.error_occurred');
                }
            } catch {
                // keep polling
            }
        };
        doPoll();
        lnurlRevealPollingInterval = window.setInterval(doPoll, 1000);
        return true;
    } catch (err: any) {
        lnurlRevealError.value = err.response?.data?.error || t('auth.error_occurred');
        return false;
    }
}

async function handleConfirmWithLightning() {
    lnurlRevealLoading.value = true;
    lnurlRevealError.value = '';
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
    lnurlRevealError.value = '';
    await fetchRevealChallengeAndOpen();
}

function onNostrRevealSuccess(payload?: { secret?: string; type?: string }) {
    showNostrRevealModal.value = false;
    if (payload?.secret) {
        form.secret = payload.secret;
        form.type = (payload.type || props.existingConnection?.type || 'blink') as 'blink' | 'aqua_descriptor';
        viewMode.value = 'editing';
    }
}

onUnmounted(() => {
    if (lnurlRevealPollingInterval != null) window.clearInterval(lnurlRevealPollingInterval);
    stopSamRockPolling();
    revokeSamRockQr();
});

async function handleConfirmPassword() {
    if (!passwordInput.value.trim()) return;
    passwordError.value = '';
    revealing.value = true;
    try {
        const response = await api.post(`/stores/${props.storeId}/wallet-connection/reveal`, {
            password: passwordInput.value,
        });
        form.type = (response.data.data?.type || props.existingConnection?.type || 'blink') as 'blink' | 'aqua_descriptor';
        form.secret = response.data.data?.secret || '';
        passwordInput.value = '';
        viewMode.value = 'editing';
    } catch (err: any) {
        const msg = err.response?.data?.errors?.password?.[0] || err.response?.data?.message || t('stores.invalid_password');
        passwordError.value = msg;
    } finally {
        revealing.value = false;
    }
}

function handleCancelEdit() {
    form.secret = '';
    viewMode.value = 'readonly';
    testResult.value = null;
    Object.keys(errors).forEach(k => delete errors[k]);
}

function validateBlinkConnectionString(connectionString: string): boolean {
    const trimmed = connectionString.trim();
    if (!trimmed) return false;
    if (!trimmed.includes(';')) return false;
    const parts = trimmed.split(';').map(p => p.trim()).filter(Boolean);
    let typeVal = '';
    let serverVal = '';
    let apiKeyVal = '';
    let walletIdVal = '';
    for (const part of parts) {
        const eq = part.indexOf('=');
        if (eq === -1) continue;
        const key = part.slice(0, eq).trim().toLowerCase();
        const value = part.slice(eq + 1).trim();
        if (key === 'type') typeVal = value;
        if (key === 'server') serverVal = value;
        if (key === 'api-key' || key === 'apikey') apiKeyVal = value;
        if (key === 'wallet-id' || key === 'walletid') walletIdVal = value;
    }
    return typeVal === 'blink' && !!serverVal && !!apiKeyVal && !!walletIdVal;
}

function validateDescriptor(descriptor: string): boolean {
    const trimmed = descriptor.trim();
    if (!trimmed) return false;
    const lower = trimmed.toLowerCase();
    return lower.startsWith('ct(slip77') && lower.includes(',elsh(wpkh(');
}

async function handleTestConnection() {
    if (!form.secret.trim()) {
        testResult.value = { success: false, message: 'Please enter a connection string or descriptor first.' };
        return;
    }
    if (form.type === 'blink' && !validateBlinkConnectionString(form.secret)) {
        testResult.value = { success: false, message: 'Invalid Blink connection string format.' };
        return;
    }
    if (form.type === 'aqua_descriptor' && !validateDescriptor(form.secret)) {
        testResult.value = { success: false, message: 'Invalid descriptor. Required format: ct(slip77(...),elsh(wpkh(...)))' };
        return;
    }
    testing.value = true;
    testResult.value = null;
    try {
        const response = await api.post(`/stores/${props.storeId}/wallet-connection/test`, {
            connection_string: form.secret,
            crypto_code: 'BTC',
        });
        testResult.value = {
            success: response.data.success ?? false,
            message: response.data.message || 'Connection test completed',
            requires_manual_config: response.data.requires_manual_config ?? false,
        };
    } catch (err: any) {
        testResult.value = {
            success: false,
            message: err.response?.data?.message || 'Failed to test connection. Please try again.',
        };
    } finally {
        testing.value = false;
    }
}

async function handleSubmit() {
    submitting.value = true;
    Object.keys(errors).forEach(k => delete errors[k]);
    testResult.value = null;

    if (form.type === 'blink' && !validateBlinkConnectionString(form.secret)) {
        errors.secret = 'Invalid Blink connection string format.';
        submitting.value = false;
        return;
    }
    if (form.type === 'aqua_descriptor' && !validateDescriptor(form.secret)) {
        errors.secret = 'Invalid descriptor. Required format: ct(slip77(...),elsh(wpkh(...)))';
        submitting.value = false;
        return;
    }

    try {
        await api.post(`/stores/${props.storeId}/wallet-connection`, {
            type: form.type,
            secret: form.secret,
        });
        emit('submitted');
    } catch (err: any) {
        if (err.response?.status === 422) {
            const validationErrors = err.response.data.errors || {};
            Object.keys(validationErrors).forEach(key => {
                errors[key] = Array.isArray(validationErrors[key]) ? validationErrors[key][0] : validationErrors[key];
            });
        } else {
            errors.general = err.response?.data?.message || 'Failed to save wallet connection';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

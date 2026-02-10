<template>
    <div class="space-y-8">
        <!-- Read-only: Current Connection + Change button (when connection exists and not editing) -->
        <template v-if="existingConnection && viewMode === 'readonly'">
            <div class="bg-gray-900/50 border border-gray-700 rounded-xl p-6">
                <h3 class="text-sm font-bold text-indigo-400 mb-4 uppercase tracking-wider">
                    {{ t('stores.current_connection') }}
                </h3>
                <div v-if="existingConnection.type === 'aqua_descriptor'" class="mb-4 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10">
                    <p class="text-sm text-amber-400">{{ t('stores.aqua_warning_btcpay') }}</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="block text-gray-500 text-xs uppercase">{{ t('stores.type') }}</span>
                        <span class="font-medium text-white">{{ existingConnection.type === 'blink' ? t('create_store.wallet_type_blink') : t('create_store.wallet_type_aqua') }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 text-xs uppercase">{{ t('stores.status') }}</span>
                        <span class="font-medium" :class="getStatusColorClass(existingConnection.status)">{{ formatStatus(existingConnection.status) }}</span>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="block text-gray-500 text-xs uppercase">{{ t('stores.masked_secret') }}</span>
                        <span class="font-mono text-gray-300 break-all bg-gray-800 px-2 py-1 rounded border border-gray-700/50 inline-block mt-1">{{ existingConnection.masked_secret || 'N/A' }}</span>
                    </div>
                </div>
                <div class="mt-6">
                    <button
                        type="button"
                        @click="viewMode = 'password'"
                        class="inline-flex items-center px-5 py-2.5 border border-indigo-500 rounded-xl text-sm font-medium text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20 transition-all"
                    >
                        {{ t('stores.change_connection') }}
                    </button>
                </div>
            </div>
            <div class="flex justify-start pt-4 border-t border-gray-700">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
                >
                    {{ t('common.cancel') }}
                </button>
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
            </div>
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
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';

interface Props {
    storeId: string;
    existingConnection?: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    submitted: [];
    cancel: [];
}>();

const { t } = useI18n();

type ViewMode = 'readonly' | 'password' | 'editing' | 'create';

const viewMode = ref<ViewMode>(props.existingConnection ? 'readonly' : 'create');

const passwordInput = ref('');
const passwordError = ref('');
const revealing = ref(false);

const submitting = ref(false);
const testing = ref(false);
const errors = reactive<Record<string, string>>({});
const testResult = ref<{ success: boolean; message: string; requires_manual_config?: boolean } | null>(null);

const form = reactive({
    type: (props.existingConnection?.type || 'blink') as 'blink' | 'aqua_descriptor',
    secret: '',
});

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

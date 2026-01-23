<template>
    <form @submit.prevent="handleSubmit" class="space-y-8">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider">
                Wallet Type
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
                        <div class="font-bold text-white text-lg">Blink</div>
                        <div class="text-sm text-gray-400 mt-1">Connect your Blink wallet using a read+receive API key. Best for speed and reliability.</div>
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
                        <div class="font-bold text-white text-lg">Aqua / Bitcoin Core</div>
                        <div class="text-sm text-gray-400 mt-1">Connect a watch-only wallet using an output descriptor. Non-custodial Setup.</div>
                    </div>
                </label>
            </div>
            <p v-if="errors.type" class="mt-2 text-sm text-red-400">{{ errors.type }}</p>
        </div>

        <div>
            <label for="secret" class="block text-sm font-medium text-indigo-300 mb-2 uppercase tracking-wider">
                {{ form.type === 'blink' ? 'Connection String' : 'Descriptor' }}
            </label>
            <div class="relative rounded-xl shadow-sm">
                <textarea
                    id="secret"
                    v-model="form.secret"
                    rows="5"
                    class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono p-4"
                    :class="duplicateWarning?.exists && form.type === 'aqua_descriptor' ? 'border-yellow-500' : ''"
                    :placeholder="form.type === 'blink' 
                        ? 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx'
                        : 'wpkh([fingerprint/hdpath]xpub...)'"
                    required
                ></textarea>
                <div v-if="checkingDuplicate" class="absolute top-2 right-2">
                    <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            
            <div class="mt-3 text-sm text-gray-400 bg-gray-900/30 p-4 rounded-xl border border-gray-700/50">
                <p class="font-medium text-gray-300 mb-2">Format Help:</p>
                <div v-if="form.type === 'blink'" class="space-y-1">
                     <p>Format: <code class="bg-gray-800 border border-gray-600 px-1.5 py-0.5 rounded text-indigo-300 font-mono text-xs">type=blink;server=...;api-key=...;wallet-id=...</code></p>
                    <p>Paste your Blink connection string containing server URL, API key, and wallet ID.</p>
                </div>
                 <div v-else class="space-y-1">
                    <p>Paste your Bitcoin Core output descriptor (watch-only, no private keys).</p>
                    <p>Examples: <code class="bg-gray-800 border border-gray-600 px-1.5 py-0.5 rounded text-indigo-300 font-mono text-xs">ct(slip77(...),elsh(wpkh(...)))</code>, <code class="bg-gray-800 border border-gray-600 px-1.5 py-0.5 rounded text-indigo-300 font-mono text-xs">tr(...)</code></p>
                </div>
            </div>
            <p v-if="errors.secret" class="mt-2 text-sm text-red-400">{{ errors.secret }}</p>
            
            <!-- Duplicate descriptor warning for Aqua -->
            <div v-if="duplicateWarning?.exists && form.type === 'aqua_descriptor'" class="mt-3 rounded-xl p-4 border border-yellow-500/20 bg-yellow-500/10">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-yellow-400">
                            This descriptor is already in use
                        </p>
                        <p class="mt-1 text-sm text-yellow-300">
                            {{ duplicateWarning.message }}
                        </p>
                        <p class="mt-2 text-xs text-yellow-400/80">
                            BTCPay allows each descriptor to be used only once. Please use a different wallet/descriptor.
                        </p>
                    </div>
                </div>
            </div>
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
                        Manual configuration by support team may be required.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="existingConnection" class="bg-blue-900/10 border border-blue-500/20 rounded-xl p-5">
            <h3 class="text-sm font-bold text-blue-400 mb-3 uppercase tracking-wider">Current Connection</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                     <span class="block text-gray-500 text-xs uppercase">Type</span>
                     <span class="font-medium text-white">{{ existingConnection.type === 'blink' ? 'Blink' : 'Aqua' }}</span>
                </div>
                 <div>
                     <span class="block text-gray-500 text-xs uppercase">Status</span>
                     <span class="font-medium" :class="getStatusColorClass(existingConnection.status)">{{ formatStatus(existingConnection.status) }}</span>
                </div>
                 <div class="sm:col-span-3">
                     <span class="block text-gray-500 text-xs uppercase">Masked Secret</span>
                     <span class="font-mono text-gray-300 break-all bg-gray-900/50 px-2 py-1 rounded border border-gray-700/50 inline-block mt-1">{{ existingConnection.masked_secret || 'N/A' }}</span>
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
                    <svg v-if="testing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ testing ? 'Testing...' : 'Test Connection' }}
                </button>
             </div>
             
            <div class="flex w-full sm:w-auto gap-4">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-gray-400 hover:text-white bg-transparent hover:bg-gray-800 transition-all"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all transform hover:scale-105"
                >
                    <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ submitting ? 'Saving...' : 'Save Connection' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
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

const submitting = ref(false);
const testing = ref(false);
const checkingDuplicate = ref(false);
const errors = reactive<Record<string, string>>({});
const testResult = ref<{ success: boolean; message: string; requires_manual_config?: boolean } | null>(null);
const duplicateWarning = ref<{ exists: boolean; message: string | null; existing_store_name: string | null } | null>(null);

const form = reactive({
    type: props.existingConnection?.type || 'blink',
    secret: '',
});

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

// Client-side validation for Blink connection string format
function validateBlinkConnectionString(connectionString: string): boolean {
    // Check for connection string format: type=blink;server=...;api-key=...;wallet-id=...
    if (connectionString.includes(';')) {
        const parts = connectionString.split(';');
        let hasType = false;
        let hasServer = false;
        let hasApiKey = false;
        let hasWalletId = false;

        for (const part of parts) {
            const [key] = part.split('=');
            const keyLower = key.trim().toLowerCase();
            if (keyLower === 'type') hasType = true;
            if (keyLower === 'server') hasServer = true;
            if (keyLower === 'api-key' || keyLower === 'apikey') hasApiKey = true;
            if (keyLower === 'wallet-id' || keyLower === 'walletid') hasWalletId = true;
        }

        return hasType && hasServer && hasApiKey && hasWalletId;
    }

    // Legacy format: URL is also acceptable
    try {
        new URL(connectionString);
        return true;
    } catch {
        return false;
    }
}

// Client-side validation for descriptor format (basic check)
function validateDescriptor(descriptor: string): boolean {
    const trimmed = descriptor.trim();
    if (!trimmed) return false;

    // Must NOT contain private keys
    if (/prv/i.test(trimmed)) {
        return false;
    }

    // Must NOT contain private key prefixes
    if (/(xprv|yprv|zprv)/i.test(trimmed)) {
        return false;
    }

    // Check if descriptor contains at least one valid descriptor function
    // Supports both simple formats (wpkh(), tr(), etc.) and complex nested formats (ct(slip77(...),elsh(wpkh(...))))
    const validFunctions = [
        'wpkh', 'wsh', 'tr', 'pkh', 'sh', 'addr', 'raw',  // Basic functions
        'ct', 'elsh', 'slip77',  // Complex nested functions (for Boltz/Aqua)
    ];
    
    const lower = trimmed.toLowerCase();
    let hasValidFunction = false;
    
    for (const func of validFunctions) {
        // Check if function appears in the descriptor (not just at start)
        // Pattern: function name followed by opening parenthesis
        if (new RegExp(`\\b${func}\\s*\\(`).test(lower)) {
            hasValidFunction = true;
            break;
        }
    }

    if (!hasValidFunction) {
        return false;
    }

    // Basic structure validation: should have balanced parentheses
    const openParens = (trimmed.match(/\(/g) || []).length;
    const closeParens = (trimmed.match(/\)/g) || []).length;
    if (openParens !== closeParens || openParens === 0) {
        return false;
    }

    // Should contain at least one xpub/ypub/zpub (extended public key)
    if (!/(xpub|ypub|zpub|tpub|upub|vpub)/i.test(trimmed)) {
        return false;
    }

    return true;
}

// Check for duplicate descriptor (for Aqua/Boltz)
async function checkDuplicateDescriptor() {
    if (form.type !== 'aqua_descriptor' || !form.secret.trim()) {
        duplicateWarning.value = null;
        return;
    }

    // Only check if descriptor is valid format
    if (!validateDescriptor(form.secret)) {
        duplicateWarning.value = null;
        return;
    }

    checkingDuplicate.value = true;
    duplicateWarning.value = null;

    try {
        const response = await api.post(`/stores/${props.storeId}/wallet-connection/check-duplicate`, {
            descriptor: form.secret.trim(),
            type: 'aqua_descriptor',
        });

        duplicateWarning.value = {
            exists: response.data.duplicate || false,
            message: response.data.message || null,
            existing_store_name: response.data.existing_store_name || null,
        };
    } catch (err: any) {
        // If check fails, don't show warning (let backend handle it)
        duplicateWarning.value = null;
    } finally {
        checkingDuplicate.value = false;
    }
}

// Watch for changes in descriptor to check for duplicates
let duplicateCheckTimeout: ReturnType<typeof setTimeout> | null = null;
watch(() => form.secret, () => {
    if (duplicateCheckTimeout) {
        clearTimeout(duplicateCheckTimeout);
    }
    
    if (form.type === 'aqua_descriptor') {
        // Debounce the check
        duplicateCheckTimeout = setTimeout(() => {
            checkDuplicateDescriptor();
        }, 500);
    } else {
        duplicateWarning.value = null;
    }
});

// Watch for type changes
watch(() => form.type, () => {
    duplicateWarning.value = null;
    if (duplicateCheckTimeout) {
        clearTimeout(duplicateCheckTimeout);
    }
    if (form.type === 'aqua_descriptor' && form.secret.trim()) {
        duplicateCheckTimeout = setTimeout(() => {
            checkDuplicateDescriptor();
        }, 500);
    }
});

async function handleTestConnection() {
    if (!form.secret.trim()) {
        testResult.value = {
            success: false,
            message: 'Please enter a connection string or descriptor first.',
        };
        return;
    }

    // Client-side validation
    if (form.type === 'blink' && !validateBlinkConnectionString(form.secret)) {
        testResult.value = {
            success: false,
            message: 'Invalid Blink connection string format. Expected: type=blink;server=https://...;api-key=...;wallet-id=...',
        };
        return;
    }

    if (form.type === 'aqua_descriptor' && !validateDescriptor(form.secret)) {
        testResult.value = {
            success: false,
            message: 'Invalid descriptor format. Must be a valid Bitcoin Core output descriptor (e.g., wpkh(), tr(), wsh(), or complex formats like ct(slip77(...),elsh(wpkh(...)))) and must not contain private keys.',
        };
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
    errors.value = {};
    testResult.value = null;

    // Client-side validation
    if (form.type === 'blink' && !validateBlinkConnectionString(form.secret)) {
        errors.secret = 'Invalid Blink connection string format. Expected: type=blink;server=https://...;api-key=...;wallet-id=...';
        submitting.value = false;
        return;
    }

    if (form.type === 'aqua_descriptor' && !validateDescriptor(form.secret)) {
        errors.secret = 'Invalid descriptor format. Must be a valid Bitcoin Core output descriptor (e.g., wpkh(), tr(), wsh(), or complex formats like ct(slip77(...),elsh(wpkh(...)))) and must not contain private keys.';
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
                errors[key] = Array.isArray(validationErrors[key]) 
                    ? validationErrors[key][0] 
                    : validationErrors[key];
            });
        } else {
            errors.general = err.response?.data?.message || 'Failed to save wallet connection';
        }
    } finally {
        submitting.value = false;
    }
}
</script>


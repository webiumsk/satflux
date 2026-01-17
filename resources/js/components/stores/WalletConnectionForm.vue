<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Wallet Type
            </label>
            <div class="space-y-3">
                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer"
                    :class="form.type === 'blink' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'">
                    <input
                        type="radio"
                        v-model="form.type"
                        value="blink"
                        class="mt-1 mr-3"
                        required
                    />
                    <div>
                        <div class="font-medium text-gray-900">Blink</div>
                        <div class="text-sm text-gray-500">Read+Receive only token</div>
                    </div>
                </label>
                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer"
                    :class="form.type === 'aqua_descriptor' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'">
                    <input
                        type="radio"
                        v-model="form.type"
                        value="aqua_descriptor"
                        class="mt-1 mr-3"
                        required
                    />
                    <div>
                        <div class="font-medium text-gray-900">Aqua</div>
                        <div class="text-sm text-gray-500">Watch-only core descriptor</div>
                    </div>
                </label>
            </div>
            <p v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</p>
        </div>

        <div>
            <label for="secret" class="block text-sm font-medium text-gray-700 mb-2">
                {{ form.type === 'blink' ? 'Connection String' : 'Descriptor' }}
            </label>
            <textarea
                id="secret"
                v-model="form.secret"
                rows="4"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-sm"
                :placeholder="form.type === 'blink' 
                    ? 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx'
                    : 'wpkh([fingerprint/hdpath]xpub...)'"
                required
            ></textarea>
            <p class="mt-1 text-sm text-gray-500">
                <span v-if="form.type === 'blink'">
                    Format: <code class="bg-gray-100 px-1 py-0.5 rounded">type=blink;server=https://...;api-key=...;wallet-id=...</code><br>
                    Paste your Blink connection string with server URL, API key, and wallet ID.
                </span>
                <span v-else>
                    Paste your Bitcoin Core output descriptor (watch-only, no private keys).<br>
                    Example formats: <code class="bg-gray-100 px-1 py-0.5 rounded">wpkh(...)</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">tr(...)</code>
                </span>
            </p>
            <p v-if="errors.secret" class="mt-1 text-sm text-red-600">{{ errors.secret }}</p>
        </div>

        <div v-if="testResult" class="rounded-md p-4" :class="testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <p class="text-sm font-medium" :class="testResult.success ? 'text-green-800' : 'text-red-800'">
                {{ testResult.message }}
            </p>
            <p v-if="testResult.requires_manual_config" class="mt-1 text-sm text-gray-600">
                Manual configuration by support team may be required.
            </p>
        </div>

        <div v-if="existingConnection" class="bg-blue-50 border border-blue-200 rounded-md p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">Current Connection</h3>
            <p class="text-sm text-blue-700">
                <strong>Type:</strong> {{ existingConnection.type === 'blink' ? 'Blink' : 'Aqua' }}<br>
                <strong>Status:</strong> {{ formatStatus(existingConnection.status) }}<br>
                <strong>Masked:</strong> {{ existingConnection.masked_secret || 'N/A' }}
            </p>
        </div>

        <div class="flex justify-between items-center">
            <button
                type="button"
                @click="handleTestConnection"
                :disabled="testing || !form.secret.trim()"
                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ testing ? 'Testing...' : 'Test Connection' }}
            </button>
            <div class="flex space-x-3">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                    {{ submitting ? 'Saving...' : 'Save Connection' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
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
const errors = reactive<Record<string, string>>({});
const testResult = ref<{ success: boolean; message: string; requires_manual_config?: boolean } | null>(null);

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

    // Check for common descriptor prefixes
    const validPrefixes = ['wpkh', 'wsh', 'tr', 'pkh', 'sh', 'addr', 'raw'];
    const lower = trimmed.toLowerCase();
    
    for (const prefix of validPrefixes) {
        if (lower.startsWith(prefix + '(')) {
            return true;
        }
    }

    return false;
}

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
            message: 'Invalid descriptor format. Must start with wpkh(), tr(), wsh(), etc.',
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
        errors.secret = 'Invalid descriptor format. Must start with wpkh(), tr(), wsh(), etc.';
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



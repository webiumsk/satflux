<template>
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="$emit('close')">
        <div class="relative top-10 mx-auto p-6 border max-w-3xl w-full mx-4 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Reveal Wallet Connection Secret</h3>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                    <p class="text-sm text-yellow-800">
                        Warning: Revealing the secret will be logged for audit purposes.
                    </p>
                </div>

                <div v-if="!revealed" class="space-y-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Confirm Password
                        </label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="Enter your password"
                            required
                            autofocus
                        />
                        <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="$emit('close')"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleReveal"
                            :disabled="submitting || !password"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {{ submitting ? 'Revealing...' : 'Reveal' }}
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <!-- Store Info -->
                    <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Store:</span>
                                <span class="ml-2 text-gray-900">{{ connection.store_name }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Type:</span>
                                <span class="ml-2 px-2 py-1 text-xs rounded-full" :class="connection.type === 'blink' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                    {{ connection.type === 'blink' ? 'Blink' : 'Aqua' }}
                                </span>
                            </div>
                            <div v-if="btcPayStoreId" class="col-span-2">
                                <span class="font-medium text-gray-700">BTCPay Store ID:</span>
                                <code class="ml-2 text-gray-900 font-mono text-xs">{{ btcPayStoreId }}</code>
                            </div>
                        </div>
                    </div>

                    <!-- Connection String -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Connection String (will auto-hide in {{ countdown }}s)
                        </label>
                        <div class="relative">
                            <textarea
                                :value="revealedSecret"
                                readonly
                                rows="3"
                                class="block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 font-mono text-sm pr-24 resize-none"
                                ref="secretInput"
                            ></textarea>
                            <button
                                @click="copyToClipboard"
                                :class="copySuccess ? 'bg-green-100 text-green-800' : 'bg-indigo-100 text-indigo-600 hover:bg-indigo-200'"
                                class="absolute right-2 top-2 px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                            >
                                {{ copySuccess ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="border border-gray-200 rounded-md overflow-hidden">
                        <button
                            @click="showInstructions = !showInstructions"
                            class="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between text-left transition-colors"
                        >
                            <span class="text-sm font-medium text-gray-700">Configuration Instructions</span>
                            <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': showInstructions }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="showInstructions" class="px-4 py-3 bg-white border-t border-gray-200">
                            <div class="prose prose-sm max-w-none">
                                <div v-if="connection.type === 'blink'" class="space-y-2 text-sm text-gray-700">
                                    <p><strong>Steps to configure Blink wallet:</strong></p>
                                    <ol class="list-decimal list-inside space-y-1 ml-2">
                                        <li>Click "Open BTCPay Store Settings" button below to open Lightning settings in a new tab</li>
                                        <li>Select "Use custom lightning node"</li>
                                        <li>In the Connection String field, paste the connection string above</li>
                                        <li>Save the settings</li>
                                        <li>Test by creating a test invoice</li>
                                    </ol>
                                    <p class="mt-2 text-xs text-gray-500">
                                        Connection string format: <code class="bg-gray-100 px-1 py-0.5 rounded">type=blink;server=https://api.blink.sv/graphql;api-key=...;wallet-id=...</code>
                                    </p>
                                </div>
                                <div v-else class="space-y-2 text-sm text-gray-700">
                                    <p><strong>Steps to configure Aqua (Boltz) wallet:</strong></p>
                                    <ol class="list-decimal list-inside space-y-1 ml-2">
                                        <li>Click "Open BTCPay Store Settings" button below to open Lightning settings in a new tab</li>
                                        <li>Select Boltz plugin (enable it first if not already enabled)</li>
                                        <li>In the Core Descriptor field, paste the descriptor above</li>
                                        <li>Save the settings</li>
                                        <li>Test by creating a test invoice</li>
                                    </ol>
                                    <p class="mt-2 text-xs text-gray-500">
                                        The descriptor is watch-only and does not contain private keys.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <a
                            v-if="btcPayStoreUrl"
                            :href="btcPayStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Open BTCPay Store Settings
                        </a>
                        <div class="flex space-x-3 ml-auto">
                            <button
                                @click="handleMarkConnected"
                                :disabled="markingConnected"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            >
                                {{ markingConnected ? 'Marking...' : 'Mark as Configured' }}
                            </button>
                            <button
                                @click="$emit('close')"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import api from '../../services/api';

interface Props {
    connection: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const password = ref('');
const submitting = ref(false);
const revealed = ref(false);
const revealedSecret = ref('');
const errors = ref<Record<string, string>>({});
const countdown = ref(60);
const secretInput = ref<HTMLTextAreaElement | null>(null);
const copySuccess = ref(false);
const showInstructions = ref(true);
const markingConnected = ref(false);
const btcPayStoreUrl = ref<string | null>(null);
const btcPayStoreId = ref<string | null>(null);
let countdownTimer: ReturnType<typeof setInterval> | null = null;
let copySuccessTimer: ReturnType<typeof setTimeout> | null = null;

async function handleReveal() {
    submitting.value = true;
    errors.value = {};

    try {
        const response = await api.post(`/support/wallet-connections/${props.connection.id}/reveal`, {
            password: password.value,
        });

        revealedSecret.value = response.data.data.secret;
        revealed.value = true;

        // Fetch BTCPay Store URL
        await fetchBtcPayStoreUrl();

        // Start countdown (60 seconds)
        countdown.value = 60;
        countdownTimer = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) {
                emit('close');
            }
        }, 1000);

        // Auto-focus and select secret for easy copying
        setTimeout(() => {
            if (secretInput.value) {
                secretInput.value.focus();
                secretInput.value.select();
            }
        }, 100);
    } catch (err: any) {
        if (err.response?.status === 422) {
            const validationErrors = err.response.data.errors || {};
            Object.keys(validationErrors).forEach(key => {
                errors.value[key] = Array.isArray(validationErrors[key])
                    ? validationErrors[key][0]
                    : validationErrors[key];
            });
        } else {
            errors.value.password = err.response?.data?.message || 'Failed to reveal secret';
        }
    } finally {
        submitting.value = false;
    }
}

async function copyToClipboard() {
    if (!revealedSecret.value) return;

    try {
        await navigator.clipboard.writeText(revealedSecret.value);
        copySuccess.value = true;

        // Reset copy success indicator after 2 seconds
        if (copySuccessTimer) {
            clearTimeout(copySuccessTimer);
        }
        copySuccessTimer = setTimeout(() => {
            copySuccess.value = false;
        }, 2000);
    } catch (err) {
        // Fallback for older browsers
        if (secretInput.value) {
            secretInput.value.select();
            document.execCommand('copy');
            copySuccess.value = true;
            if (copySuccessTimer) {
                clearTimeout(copySuccessTimer);
            }
            copySuccessTimer = setTimeout(() => {
                copySuccess.value = false;
            }, 2000);
        }
    }
}

async function fetchBtcPayStoreUrl() {
    try {
        const response = await api.get(`/support/wallet-connections/${props.connection.id}/btcpay-store-url`);
        btcPayStoreUrl.value = response.data.data.url;
        btcPayStoreId.value = response.data.data.store_id;
    } catch (err) {
        // If endpoint doesn't exist yet, btcPayStoreUrl will remain null
        console.warn('Could not fetch BTCPay store URL:', err);
    }
}

async function handleMarkConnected() {
    if (!confirm('Mark this wallet connection as configured in BTCPay?')) {
        return;
    }

    markingConnected.value = true;
    try {
        await api.put(`/support/wallet-connections/${props.connection.id}/mark-connected`);
        emit('close');
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to mark connection as connected');
    } finally {
        markingConnected.value = false;
    }
}

watch(() => props.connection, () => {
    // Reset state when connection changes
    password.value = '';
    revealed.value = false;
    revealedSecret.value = '';
    errors.value = {};
    copySuccess.value = false;
    showInstructions.value = true;
    btcPayStoreUrl.value = null;
    btcPayStoreId.value = null;
    markingConnected.value = false;
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
    if (copySuccessTimer) {
        clearTimeout(copySuccessTimer);
        copySuccessTimer = null;
    }
});

onUnmounted(() => {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
    if (copySuccessTimer) {
        clearTimeout(copySuccessTimer);
    }
});
</script>



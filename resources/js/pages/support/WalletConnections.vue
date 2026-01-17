<template>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Wallet Connections Needing Support</h1>
            <p class="mt-2 text-sm text-gray-600">Review and configure wallet connections submitted by merchants</p>
        </div>

        <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>

        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <p class="text-sm text-red-800">{{ error }}</p>
        </div>

        <div v-else-if="connections.length === 0" class="bg-gray-50 border border-gray-200 rounded-md p-8 text-center">
            <p class="text-gray-600">No wallet connections need support at this time.</p>
        </div>

        <div v-else class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Store
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Secret (Masked)
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Submitted
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="connection in connections" :key="connection.id">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ connection.store_name }}</div>
                            <div class="text-sm text-gray-500">{{ connection.submitted_by }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="connection.type === 'blink' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                {{ connection.type === 'blink' ? 'Blink' : 'Aqua' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="getStatusBadgeClass(connection.status)">
                                {{ formatStatus(connection.status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <code class="text-sm text-gray-600 font-mono">{{ connection.masked_secret }}</code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ formatDate(connection.submitted_at) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <button
                                @click="revealSecret(connection)"
                                class="text-indigo-600 hover:text-indigo-900 font-medium"
                            >
                                Reveal & Configure
                            </button>
                            <button
                                v-if="connection.status === 'needs_support'"
                                @click="markConnected(connection)"
                                class="text-green-600 hover:text-green-900 font-medium"
                            >
                                Mark Connected
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <RevealSecretModal
            v-if="showRevealModal"
            :connection="selectedConnection"
            @close="handleModalClose"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import RevealSecretModal from '../../components/support/RevealSecretModal.vue';

const loading = ref(true);
const error = ref<string | null>(null);
const connections = ref<any[]>([]);
const showRevealModal = ref(false);
const selectedConnection = ref<any>(null);

async function loadConnections() {
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get('/support/wallet-connections');
        connections.value = response.data.data || [];
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load wallet connections';
    } finally {
        loading.value = false;
    }
}

function formatDate(dateString: string): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
}

function formatStatus(status: string): string {
    const statusMap: Record<string, string> = {
        pending: 'Pending',
        needs_support: 'Needs Support',
        connected: 'Connected',
    };
    return statusMap[status] || status;
}

function getStatusBadgeClass(status: string): string {
    const classMap: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        needs_support: 'bg-red-100 text-red-800',
        connected: 'bg-green-100 text-green-800',
    };
    return classMap[status] || 'bg-gray-100 text-gray-800';
}

function revealSecret(connection: any) {
    selectedConnection.value = connection;
    showRevealModal.value = true;
}

function handleModalClose() {
    showRevealModal.value = false;
    // Refresh connections list when modal closes (e.g., after marking as connected)
    loadConnections();
}

async function markConnected(connection: any) {
    if (!confirm('Mark this wallet connection as connected?')) {
        return;
    }

    try {
        await api.put(`/support/wallet-connections/${connection.id}/mark-connected`);
        await loadConnections();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to mark connection as connected');
    }
}

onMounted(() => {
    loadConnections();
});
</script>



<template>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Wallet Connections Needing Support</h1>
            <p class="mt-2 text-sm text-gray-600">Review and configure wallet connections submitted by merchants</p>
        </div>

        <!-- Filters and Search -->
        <div class="mb-6 bg-white shadow rounded-lg p-4">
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by store name or user email..."
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>
                <!-- Status Filter -->
                <div>
                    <select
                        v-model="statusFilter"
                        class="block w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">All Statuses</option>
                        <option value="needs_support">Needs Support</option>
                        <option value="pending">Pending</option>
                        <option value="connected">Connected</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>

        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <p class="text-sm text-red-800">{{ error }}</p>
        </div>

        <div v-else-if="filteredConnections.length === 0" class="bg-gray-50 border border-gray-200 rounded-md p-8 text-center">
            <p class="text-gray-600">{{ connections.length === 0 ? 'No wallet connections need support at this time.' : 'No connections match your filters.' }}</p>
        </div>

        <div v-else class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Results count -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <p class="text-sm text-gray-600">
                    Showing {{ filteredConnections.length }} of {{ connections.length }} connection(s)
                </p>
            </div>
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
                    <tr v-for="connection in filteredConnections" :key="connection.id">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-3">
                                <button
                                    @click="revealSecret(connection)"
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Reveal & Configure
                                </button>
                                <button
                                    v-if="connection.status === 'needs_support'"
                                    @click="markConnected(connection)"
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                >
                                    <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Mark Connected
                                </button>
                            </div>
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
import { ref, onMounted, computed } from 'vue';
import api from '../../services/api';
import RevealSecretModal from '../../components/support/RevealSecretModal.vue';

const loading = ref(true);
const error = ref<string | null>(null);
const connections = ref<any[]>([]);
const showRevealModal = ref(false);
const selectedConnection = ref<any>(null);
const searchQuery = ref('');
const statusFilter = ref('');

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
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'N/A';
    
    // European format: DD.MM.YYYY HH:mm
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
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
        needs_support: 'bg-blue-100 text-blue-800',
        connected: 'bg-green-100 text-green-800',
    };
    return classMap[status] || 'bg-gray-100 text-gray-800';
}

// Filtered connections based on search and status filter
const filteredConnections = computed(() => {
    let filtered = connections.value;

    // Apply status filter
    if (statusFilter.value) {
        filtered = filtered.filter(conn => conn.status === statusFilter.value);
    }

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(conn => 
            conn.store_name?.toLowerCase().includes(query) ||
            conn.submitted_by?.toLowerCase().includes(query) ||
            conn.masked_secret?.toLowerCase().includes(query)
        );
    }

    return filtered;
});

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



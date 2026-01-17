<template>
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Wallet Connection</h1>
            <p class="mt-2 text-sm text-gray-600">Configure your Lightning wallet connection for this store</p>
        </div>

        <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>

        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <p class="text-sm text-red-800">{{ error }}</p>
        </div>

        <div v-else class="bg-white shadow rounded-lg">
            <div class="p-6">
                <WalletConnectionForm
                    :store-id="storeId"
                    :existing-connection="connection"
                    @submitted="handleSubmitted"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import WalletConnectionForm from '../../components/stores/WalletConnectionForm.vue';

const route = useRoute();
const router = useRouter();
const storeId = route.params.id as string;

const loading = ref(true);
const error = ref<string | null>(null);
const connection = ref<any>(null);

async function loadConnection() {
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get(`/stores/${storeId}/wallet-connection`);
        connection.value = response.data.data;
    } catch (err: any) {
        if (err.response?.status !== 404) {
            error.value = err.response?.data?.message || 'Failed to load wallet connection';
        }
    } finally {
        loading.value = false;
    }
}

function handleSubmitted() {
    router.push({ name: 'stores-show', params: { id: storeId } });
}

function handleCancel() {
    router.push({ name: 'stores-show', params: { id: storeId } });
}

onMounted(() => {
    loadConnection();
});
</script>



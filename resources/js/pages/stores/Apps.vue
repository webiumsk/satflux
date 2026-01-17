<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <router-link
        :to="`/stores/${storeId}`"
        class="text-indigo-600 hover:text-indigo-500 text-sm"
      >
        ← Back to Store
      </router-link>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Apps</h1>

    <div v-if="loading" class="text-center py-12">
      <p class="text-gray-500">Loading apps...</p>
    </div>

    <div v-else-if="apps.length === 0" class="text-center py-12">
      <p class="text-gray-500 mb-4">No apps yet.</p>
      <router-link
        :to="`/stores/${storeId}/apps/create`"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
      >
        Create App
      </router-link>
    </div>

    <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="app in apps"
        :key="app.id"
        class="bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-lg transition"
        @click="$router.push(`/stores/${storeId}/apps/${app.id}`)"
      >
        <div class="p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-2">{{ app.name }}</h3>
          <p class="text-sm text-gray-500">
            Type: {{ app.app_type }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAppsStore } from '../../store/apps';

const route = useRoute();
const appsStore = useAppsStore();

const storeId = route.params.id as string;
const loading = ref(false);
const apps = ref([]);

onMounted(async () => {
  loading.value = true;
  try {
    apps.value = await appsStore.fetchApps(storeId);
  } finally {
    loading.value = false;
  }
});
</script>


<template>
  <footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <div class="text-center">
        <p class="text-sm text-gray-500">
          UZOL21 <span v-if="version">v{{ version }}</span>
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';

const version = ref<string | null>(null);

onMounted(async () => {
  try {
    const response = await api.get('/version');
    version.value = response.data.version || null;
  } catch (error) {
    console.error('Failed to load app version:', error);
    // Silently fail - version is optional
  }
});
</script>


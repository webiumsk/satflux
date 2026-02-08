<template>
  <footer class="bg-gray-900 border-t border-gray-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="text-center">
        <p class="text-sm text-gray-400">
          satflux.io
          <span v-if="version" class="text-gray-600">v{{ version }}</span>
        </p>
        <p class="text-xs text-gray-600 mt-2">
          &copy; {{ new Date().getFullYear() }} satflux.io. All rights reserved.
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import api from "../../services/api";

const version = ref<string | null>(null);

onMounted(async () => {
  try {
    const response = await api.get("/version");
    version.value = response.data.version || null;
  } catch (error) {
    console.error("Failed to load app version:", error);
    // Silently fail - version is optional
  }
});
</script>

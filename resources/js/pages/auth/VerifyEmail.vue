<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8"
  >
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
          {{ verified ? "Email Verified" : "Verifying Email..." }}
        </h2>
        <p v-if="verified" class="mt-2 text-sm text-green-600">
          Your email has been verified successfully. Redirecting to dashboard...
        </p>
        <p v-else-if="error" class="mt-2 text-sm text-red-600">
          {{ error }}
        </p>
        <p v-else class="mt-2 text-sm text-gray-600">
          Please wait while we verify your email address...
        </p>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { useAuthStore } from "../../store/auth";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const verified = ref(false);
const error = ref("");

onMounted(async () => {
  try {
    // Parse id and hash from route params or URL path
    // First try route params (Vue router)
    let id = route.params.id as string;
    let hash = route.params.hash as string;
    
    // If route params are not available, parse from URL
    if (!id || !hash) {
      const pathMatch = window.location.pathname.match(/\/auth\/verify-email\/([^/]+)\/([^/?]+)/);
      if (pathMatch && pathMatch[1] && pathMatch[2]) {
        id = pathMatch[1];
        hash = pathMatch[2];
      }
    }
    
    if (!id || !hash) {
      error.value = "Invalid verification link format.";
      return;
    }
    
    const queryParams = new URLSearchParams(window.location.search);
    
    // Use api service instead of direct axios
    const api = axios.create({
      baseURL: '/api',
      withCredentials: true,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    const response = await api.get(`/auth/verify-email/${id}/${hash}?${queryParams.toString()}`, {
      validateStatus: (status) => status < 500, // Accept all status codes except 5xx
    });

    // Check if response indicates an error
    if (response.status >= 400) {
      error.value = response.data?.message || `Verification failed with status ${response.status}`;
      return;
    }

    const data = response.data;

    if (data.verified) {
      verified.value = true;
      
      // Set user in auth store if user data is provided
      if (data.user) {
        authStore.user = data.user;
      } else {
        // Fallback: fetch user data from API
        await authStore.fetchUser();
      }
      
      // Redirect to dashboard immediately after setting user
      router.push({ name: 'home' });
    } else {
      error.value = data.message || "Failed to verify email.";
    }
  } catch (err: any) {
    // Handle axios errors
    if (err.response) {
      // Server responded with error status
      error.value = err.response.data?.message || `Verification failed: ${err.response.status}`;
    } else if (err.request) {
      // Request was made but no response received
      error.value = "Failed to connect to server. Please try again.";
    } else {
      // Something else happened
      error.value = "Failed to verify email. The link may be invalid or expired.";
    }
  }
});
</script>


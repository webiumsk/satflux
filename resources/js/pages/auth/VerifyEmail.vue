<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gray-900">
      <div class="absolute top-0 -left-6 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"></div>
      <div class="absolute bottom-0 -right-6 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000"></div>
    </div>

    <div class="max-w-md w-full space-y-8 relative z-10">
      <div class="text-center">
        <router-link to="/" class="inline-block">
          <span class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-500 tracking-tight">UZOL21</span>
        </router-link>
      </div>

      <div class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl text-center">
        <h2 class="text-3xl font-extrabold text-white mb-6">
          {{ verified ? "Email Verified" : "Verifying Email..." }}
        </h2>
        
        <div v-if="verified" class="space-y-4">
           <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
           </div>
          <p class="text-gray-300">
            Your email has been verified successfully. Redirecting to dashboard...
          </p>
        </div>
        
        <div v-else-if="error" class="space-y-4">
           <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
           </div>
          <p class="text-red-400 font-medium">
            {{ error }}
          </p>
          <div class="mt-4">
             <router-link to="/dashboard" class="text-indigo-400 hover:text-indigo-300 font-medium transition-colors">
               Go to Dashboard
             </router-link>
          </div>
        </div>
        
        <div v-else class="space-y-4">
           <svg class="animate-spin h-10 w-10 text-indigo-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          <p class="text-gray-400">
            Please wait while we verify your email address...
          </p>
        </div>
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
      setTimeout(() => {
          router.push({ name: 'home' });
      }, 2000); // Small delay to show success message
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

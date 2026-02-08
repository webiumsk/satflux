<template>
  <div class="min-h-screen bg-gray-900 flex flex-col">
    <PublicHeader />
    
    <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
      <div class="max-w-md w-full bg-gray-800 rounded-2xl shadow-xl border border-gray-700 p-8 text-center">
        <div class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-500/20">
            <svg class="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        
        <div v-if="processing" class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-500/20 animate-pulse">
            <svg class="h-8 w-8 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-white mb-4 mt-4">Processing Subscription...</h1>
          <p class="text-gray-400">
            Please wait while we activate your subscription.
          </p>
        </div>
        
        <div v-else-if="error" class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-500/20">
            <svg class="h-8 w-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-white mb-4 mt-4">Subscription Activation Failed</h1>
          <p class="text-gray-400 mb-6">{{ error }}</p>
        </div>
        
        <div v-else>
          <h1 class="text-2xl font-bold text-white mb-4">{{ success ? 'Subscription Activated!' : 'Subscription Initiated!' }}</h1>
          
          <p v-if="success" class="text-gray-400 mb-6">
            Thank you for subscribing! Your subscription has been activated and your account has been upgraded.
          </p>
          <p v-else class="text-gray-400 mb-6">
            Thank you for subscribing. We've received your subscription request.
          </p>
          
          <div v-if="!success" class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-4 mb-6">
            <p class="text-sm text-yellow-400">
              <strong>Next Steps:</strong> Your subscription will be activated once payment is confirmed on the blockchain. 
              This usually takes a few minutes. You'll receive an email confirmation once your subscription is active.
            </p>
          </div>
        </div>
        
        <div class="space-y-3">
          <router-link
            to="/dashboard"
            class="block w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-500 transition-colors shadow-lg shadow-indigo-600/20"
          >
            Go to Dashboard
          </router-link>
          
          <router-link
            to="/stores"
            class="block w-full px-4 py-2 border border-gray-600 text-gray-300 font-medium rounded-lg hover:bg-gray-700 transition-colors"
          >
            View My Stores
          </router-link>
        </div>
      </div>
    </div>
    
    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../store/auth';
import api from '../services/api';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';

const route = useRoute();
const authStore = useAuthStore();
const processing = ref(false);
const error = ref('');
const success = ref(false);

onMounted(async () => {
  // Extract checkoutPlanId from query params (BTCPay adds this)
  const checkoutPlanId = route.query.checkoutPlanId as string;
  
  if (checkoutPlanId) {
    processing.value = true;
    try {
      // Call backend to process subscription success and update user role
      const response = await api.get('/subscriptions/success', {
        params: { checkoutPlanId },
      });

      if (response.data.role) {
        success.value = true;
        // Refresh user data to get updated role
        if (authStore.isAuthenticated) {
          await authStore.fetchUser();
        }
      }
    } catch (err: any) {
      console.error('Failed to process subscription success:', err);
      error.value = err.response?.data?.message || 'Failed to process subscription. Please contact support.';
    } finally {
      processing.value = false;
    }
  }
});
</script>


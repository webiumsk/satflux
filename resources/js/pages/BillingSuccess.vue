<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <PublicHeader />
    
    <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        
        <div v-if="processing" class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 animate-pulse">
            <svg class="h-8 w-8 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mb-4 mt-4">Processing Subscription...</h1>
          <p class="text-gray-600">
            Please wait while we activate your subscription.
          </p>
        </div>
        
        <div v-else-if="error" class="mb-6">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100">
            <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mb-4 mt-4">Subscription Activation Failed</h1>
          <p class="text-gray-600 mb-6">{{ error }}</p>
        </div>
        
        <div v-else>
          <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ success ? 'Subscription Activated!' : 'Subscription Initiated!' }}</h1>
          
          <p v-if="success" class="text-gray-600 mb-6">
            Thank you for subscribing! Your subscription has been activated and your account has been upgraded.
          </p>
          <p v-else class="text-gray-600 mb-6">
            Thank you for subscribing. We've received your subscription request.
          </p>
          
          <div v-if="!success" class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
            <p class="text-sm text-yellow-800">
              <strong>Next Steps:</strong> Your subscription will be activated once payment is confirmed on the blockchain. 
              This usually takes a few minutes. You'll receive an email confirmation once your subscription is active.
            </p>
          </div>
        </div>
        
        <div class="space-y-3">
          <router-link
            to="/dashboard"
            class="block w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 transition-colors"
          >
            Go to Dashboard
          </router-link>
          
          <router-link
            to="/stores"
            class="block w-full px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-md hover:bg-gray-50 transition-colors"
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


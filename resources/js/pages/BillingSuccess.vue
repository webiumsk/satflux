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
        
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Subscription Initiated!</h1>
        
        <p class="text-gray-600 mb-6">
          Thank you for subscribing. We've received your subscription request.
        </p>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
          <p class="text-sm text-yellow-800">
            <strong>Next Steps:</strong> Your subscription will be activated once payment is confirmed on the blockchain. 
            This usually takes a few minutes. You'll receive an email confirmation once your subscription is active.
          </p>
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
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';

const route = useRoute();

onMounted(() => {
  // Optionally extract checkout ID from query params for future tracking
  const checkoutId = route.query.checkout;
  if (checkoutId) {
    console.log('Checkout ID:', checkoutId);
    // Future: Track checkout completion for analytics or fulfillment
  }
});
</script>


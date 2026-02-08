<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
    @click.self="close"
  >
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            Upgrade Required
          </h3>
          <button
            @click="close"
            class="text-gray-400 hover:text-gray-500"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="mb-6">
          <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-yellow-100 rounded-full">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          
          <p class="text-center text-gray-700 mb-4">
            {{ message }}
          </p>

          <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <p class="text-sm text-gray-600 mb-2">
              <strong>Current plan limits:</strong>
            </p>
            <ul class="text-sm text-gray-600 space-y-1">
              <li v-for="limit in limits" :key="limit.feature" class="flex justify-between">
                <span>{{ limit.feature }}:</span>
                <span class="font-medium">{{ limit.current }}/{{ limit.max === null ? 'unlimited' : limit.max }}</span>
              </li>
            </ul>
          </div>

          <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
            <p class="text-sm font-medium text-indigo-900 mb-2">
              Upgrade to unlock more:
            </p>
            <ul class="text-sm text-indigo-700 space-y-1">
              <li v-for="benefit in benefits" :key="benefit" class="flex items-start">
                <svg class="w-4 h-4 text-indigo-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ benefit }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="error" class="mb-4 rounded-md bg-red-50 p-3">
          <p class="text-sm text-red-800">{{ error }}</p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col space-y-2">
          <button
            @click="handleUpgrade"
            :disabled="upgrading"
            class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ upgrading ? 'Processing...' : upgradeButtonText }}
          </button>
          
          <button
            @click="close"
            :disabled="upgrading"
            class="w-full px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-md hover:bg-gray-50 transition-colors disabled:opacity-50"
          >
            Maybe Later
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';

const router = useRouter();

interface Props {
  show: boolean;
  message?: string;
  limits?: Array<{ feature: string; current: number; max: number | null }>;
  recommendedPlan?: 'pro' | 'enterprise';
  upgradeButtonText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  message: 'You have reached the limit for your current plan.',
  limits: () => [],
  recommendedPlan: 'pro',
  upgradeButtonText: 'Upgrade Now',
});

const emit = defineEmits<{
  close: [];
}>();

const upgrading = ref(false);
const error = ref('');

const benefits = computed(() => {
  if (props.recommendedPlan === 'enterprise') {
    return [
      'Unlimited Lightning Addresses',
      'Unlimited stores',
      'Priority support',
      'Custom integrations',
      'SLA guarantees',
    ];
  }
  return [
    '3 Lightning Addresses (up from 1)',
    'Unlimited stores',
    'Priority support',
    'CSV exports',
    'API integrations',
  ];
});

function close() {
  emit('close');
}

async function handleUpgrade() {
  upgrading.value = true;
  error.value = '';

  try {
    const response = await api.post('/subscriptions/checkout', {
      plan: props.recommendedPlan,
    });

    if (response.data.checkoutUrl) {
      window.location.href = response.data.checkoutUrl;
    } else {
      error.value = 'Failed to create checkout. Please try again.';
      upgrading.value = false;
    }
  } catch (err: any) {
    console.error('Failed to create checkout:', err);
    error.value = err.response?.data?.message || 'Failed to create checkout. Please try again.';
    upgrading.value = false;
  }
}
</script>


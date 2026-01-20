<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">Account Settings</h3>

          <!-- Profile Information -->
          <div class="mb-8">
            <h4 class="text-md font-medium text-gray-900 mb-4">Profile Information</h4>
            <form @submit.prevent="handleUpdateProfile">
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    id="name"
                    v-model="profileForm.name"
                    type="text"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                  <input
                    id="email"
                    v-model="profileForm.email"
                    type="email"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
              <div class="mt-6">
                <button
                  type="submit"
                  :disabled="profileLoading"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ profileLoading ? 'Saving...' : 'Save Profile' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Subscription Plan -->
          <div class="mb-8">
            <h4 class="text-md font-medium text-gray-900 mb-4">Subscription Plan</h4>
            <div class="bg-gray-50 rounded-lg p-6">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h5 class="text-lg font-semibold text-gray-900">
                    {{ currentPlanName }}
                  </h5>
                  <p class="text-sm text-gray-600 mt-1">{{ currentPlanDescription }}</p>
                </div>
                <div class="text-right">
                  <div class="text-2xl font-bold text-gray-900">{{ currentPlanPrice }}</div>
                  <div class="text-sm text-gray-600">per month</div>
                </div>
              </div>

              <!-- Plan Features -->
              <div class="border-t border-gray-200 pt-4 mb-4">
                <h6 class="text-sm font-medium text-gray-900 mb-3">Current plan includes:</h6>
                <ul class="space-y-2">
                  <li v-for="feature in currentPlanFeatures" :key="feature" class="flex items-start text-sm text-gray-700">
                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ feature }}
                  </li>
                </ul>
              </div>

              <!-- Upgrade Options -->
              <div v-if="!isPaidPlan" class="border-t border-gray-200 pt-4">
                <h6 class="text-sm font-medium text-gray-900 mb-3">Upgrade to unlock more:</h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Pro Plan Card -->
                  <div class="border border-gray-200 rounded-lg p-4">
                    <h6 class="font-semibold text-gray-900 mb-2">Pro Plan</h6>
                    <div class="text-xl font-bold text-gray-900 mb-2">50,000 sats/month</div>
                    <ul class="text-sm text-gray-600 space-y-1 mb-4">
                      <li>• 3 Lightning Addresses</li>
                      <li>• Unlimited stores</li>
                      <li>• CSV exports</li>
                      <li>• API integrations</li>
                    </ul>
                    <button
                      @click="upgradePlan('pro')"
                      :disabled="upgrading"
                      class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                      {{ upgrading ? 'Processing...' : 'Upgrade to Pro' }}
                    </button>
                  </div>

                  <!-- Enterprise Plan Card -->
                  <div class="border border-gray-200 rounded-lg p-4">
                    <h6 class="font-semibold text-gray-900 mb-2">Enterprise Plan</h6>
                    <div class="text-xl font-bold text-gray-900 mb-2">200,000 sats/month</div>
                    <ul class="text-sm text-gray-600 space-y-1 mb-4">
                      <li>• Unlimited Lightning Addresses</li>
                      <li>• Everything from Pro</li>
                      <li>• Dedicated support</li>
                      <li>• Custom integrations</li>
                    </ul>
                    <button
                      @click="upgradePlan('enterprise')"
                      :disabled="upgrading"
                      class="w-full px-4 py-2 border-2 border-indigo-600 text-indigo-600 text-sm font-medium rounded-md hover:bg-indigo-50 transition-colors disabled:opacity-50"
                    >
                      {{ upgrading ? 'Processing...' : 'Upgrade to Enterprise' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Change Password -->
          <div>
            <h4 class="text-md font-medium text-gray-900 mb-4">Change Password</h4>
            <form @submit.prevent="handleUpdatePassword">
              <div class="space-y-4">
                <div>
                  <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                  <input
                    id="current_password"
                    v-model="passwordForm.current_password"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                  <input
                    id="password"
                    v-model="passwordForm.password"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                  <input
                    id="password_confirmation"
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
              <div class="mt-6">
                <button
                  type="submit"
                  :disabled="passwordLoading"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ passwordLoading ? 'Saving...' : 'Change Password' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';

const authStore = useAuthStore();

const profileForm = ref({
  name: '',
  email: '',
});

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const profileLoading = ref(false);
const passwordLoading = ref(false);
const upgrading = ref(false);

// Plan information
const currentPlanName = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') return 'Enterprise';
  if (role === 'pro') return 'Pro';
  return 'Free';
});

const currentPlanPrice = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') return '200,000 sats';
  if (role === 'pro') return '50,000 sats';
  return '0 sats';
});

const currentPlanDescription = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') return 'Unlimited features with dedicated support';
  if (role === 'pro') return 'Advanced features for growing businesses';
  return 'Basic features to get started';
});

const currentPlanFeatures = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') {
    return [
      'Unlimited Lightning Addresses',
      'Unlimited stores',
      'Priority support',
      'CSV exports',
      'API integrations',
      'Custom integrations',
      'SLA guarantees',
      'Automatic monthly reports',
    ];
  }
  if (role === 'pro') {
    return [
      '3 Lightning Addresses',
      'Unlimited stores',
      'Priority support',
      'CSV exports',
      'API integrations',
      'Automatic monthly reports',
    ];
  }
  return [
    '1 Lightning Address',
    '1 Store',
    'Basic features',
    'Point of Sale apps',
    'Invoice management',
  ];
});

const isPaidPlan = computed(() => {
  const role = authStore.user?.role || 'merchant';
  return role === 'pro' || role === 'enterprise';
});

onMounted(() => {
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || '';
    profileForm.value.email = authStore.user.email || '';
  }
});

async function upgradePlan(plan: string) {
  upgrading.value = true;
  try {
    const response = await api.post('/subscriptions/checkout', {
      plan: plan,
    });

    if (response.data.checkoutUrl) {
      window.location.href = response.data.checkoutUrl;
    } else {
      alert('Failed to create checkout. Please try again.');
      upgrading.value = false;
    }
  } catch (error: any) {
    console.error('Failed to create checkout:', error);
    alert(error.response?.data?.message || 'Failed to create checkout. Please try again.');
    upgrading.value = false;
  }
}

async function handleUpdateProfile() {
  profileLoading.value = true;
  try {
    await api.put('/user', profileForm.value);
    await authStore.fetchUser();
    alert('Profile updated successfully');
  } catch (error) {
    alert('Failed to update profile');
  } finally {
    profileLoading.value = false;
  }
}

async function handleUpdatePassword() {
  passwordLoading.value = true;
  try {
    await api.put('/user/password', passwordForm.value);
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    };
    alert('Password updated successfully');
  } catch (error) {
    alert('Failed to update password');
  } finally {
    passwordLoading.value = false;
  }
}
</script>









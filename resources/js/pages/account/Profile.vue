<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="space-y-6">
        <!-- Header -->
        <div>
          <h3 class="text-2xl font-bold text-white">Account Settings</h3>
          <p class="mt-1 text-sm text-gray-400">Manage your profile, password, and subscription plan.</p>
        </div>

        <!-- Profile Information -->
        <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
          <div class="px-6 py-8 sm:p-10">
            <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
               <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
               Profile Information
            </h4>
            <form @submit.prevent="handleUpdateProfile">
              <div class="grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-2">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                  <div class="mt-1">
                    <input
                      id="name"
                      v-model="profileForm.name"
                      type="text"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    />
                  </div>
                </div>
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
                  <div class="mt-1">
                    <input
                      id="email"
                      v-model="profileForm.email"
                      type="email"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    />
                  </div>
                </div>
              </div>
              <div class="mt-8 flex justify-end">
                <button
                  type="submit"
                  :disabled="profileLoading"
                  class="inline-flex justify-center py-2.5 px-6 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
                >
                  {{ profileLoading ? 'Saving...' : 'Save Profile' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Subscription Plan -->
        <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
          <div class="px-6 py-8 sm:p-10">
            <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
               <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
               Subscription Plan
            </h4>
            
            <div class="bg-gradient-to-br from-gray-700/50 to-gray-800 rounded-xl p-8 border border-gray-600 relative overflow-hidden">
               <!-- Decorative blob -->
               <div class="absolute top-0 right-0 -tr-10 w-32 h-32 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>

              <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 relative z-10">
                <div>
                  <h5 class="text-2xl font-bold text-white flex items-center gap-2">
                    {{ currentPlanName }}
                    <span v-if="!isPaidPlan" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-600 text-gray-100">
                      Standard
                    </span>
                    <span v-else-if="subscriber?.isActive" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                      Active
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-600 text-white">
                      Inactive
                    </span>
                  </h5>
                  <p class="text-gray-400 mt-2">{{ currentPlanDescription }}</p>
                  <div v-if="subscriber && subscriber.periodEnd" class="text-sm text-gray-400 mt-2">
                    Next billing: {{ formatDate(subscriber.periodEnd) }}
                  </div>
                </div>
                <div class="mt-4 md:mt-0 md:text-right">
                  <div class="text-3xl font-bold text-white">{{ currentPlanPrice }}</div>
                  <div class="text-sm text-gray-400">per month</div>
                </div>
              </div>

              <!-- Plan Features -->
              <div class="border-t border-gray-600 pt-6 mb-6">
                <h6 class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider">Current plan includes:</h6>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <li v-for="feature in currentPlanFeatures" :key="feature" class="flex items-center text-sm text-gray-300">
                    <svg class="w-5 h-5 text-green-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ feature }}
                  </li>
                </ul>
              </div>

              <!-- Billing Information -->
              <div v-if="isPaidPlan || creditBalance > 0" class="border-t border-gray-600 pt-6 mb-6">
                <h6 class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider">Billing Information</h6>
                <div class="space-y-4">
                  <div v-if="isPaidPlan" class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Payment Method</span>
                    <div class="flex items-center gap-2">
                      <span class="px-3 py-1 rounded-full bg-gray-700 text-sm text-gray-300">Credit balance: {{ formatSats(creditBalance) }}</span>
                      <button
                        @click="showAddCreditModal = true"
                        class="text-sm text-indigo-400 hover:text-indigo-300 flex items-center gap-1"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add credit
                      </button>
                    </div>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Notification Email</span>
                    <span class="text-sm text-gray-300 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                      {{ authStore.user?.email }}
                    </span>
                  </div>
                  <div v-if="isPaidPlan && subscriber && subscriber.periodEnd" class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Next charge on {{ formatDate(subscriber.periodEnd) }}</span>
                    <span class="text-sm font-medium text-white">{{ currentPlanPrice }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Auto renewal</span>
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-300">{{ subscriber?.autoRenew ? 'On' : 'Off' }}</span>
                      <div class="relative inline-block w-10 h-6 transition-colors duration-200 ease-in-out rounded-full"
                           :class="subscriber?.autoRenew ? 'bg-indigo-600' : 'bg-gray-600'">
                        <span class="absolute left-1 top-1 inline-block w-4 h-4 transform transition-transform duration-200 ease-in-out rounded-full bg-white"
                              :class="subscriber?.autoRenew ? 'translate-x-4' : 'translate-x-0'"></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Upgrade Options -->
              <div v-if="showUpgradeOptions" class="border-t border-gray-600 pt-8 mt-8">
                <h6 class="text-lg font-medium text-white mb-6">Upgrade to unlock more power:</h6>
                <div :class="showProUpgrade ? 'grid grid-cols-1 md:grid-cols-2 gap-6' : 'grid grid-cols-1 gap-6'">
                  <!-- Pro Plan Card -->
                  <div v-if="showProUpgrade" class="border border-gray-600 rounded-xl p-6 bg-gray-800/80 hover:border-indigo-500 transition-colors relative group">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative z-10">
                      <h6 class="text-lg font-bold text-white mb-2">Pro Plan</h6>
                      <div class="text-2xl font-bold text-indigo-400 mb-4">100 sats<span class="text-base font-normal text-gray-500">/mo</span></div>
                      <ul class="text-sm text-gray-400 space-y-2 mb-6">
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>3 Lightning Addresses</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>Unlimited stores</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>CSV exports</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>API integrations</li>
                      </ul>
                      <button
                        @click="upgradePlan('pro')"
                        :disabled="upgrading"
                        class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {{ upgrading ? 'Processing...' : 'Upgrade to Pro' }}
                      </button>
                    </div>
                  </div>

                  <!-- Enterprise Plan Card -->
                  <div v-if="showEnterpriseUpgrade" class="border border-gray-600 rounded-xl p-6 bg-gray-800/80 hover:border-purple-500 transition-colors relative group"
                       :class="!showProUpgrade ? 'md:max-w-md mx-auto' : ''">
                     <div class="absolute inset-0 bg-gradient-to-br from-purple-600/5 to-pink-600/5 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative z-10">
                      <h6 class="text-lg font-bold text-white mb-2">Enterprise Plan</h6>
                      <div class="text-2xl font-bold text-purple-400 mb-4">1,000 sats<span class="text-base font-normal text-gray-500">/mo</span></div>
                      <ul class="text-sm text-gray-400 space-y-2 mb-6">
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-2"></span>Unlimited Lightning Addresses</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-2"></span>Everything from Pro</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-2"></span>Dedicated support</li>
                        <li class="flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-2"></span>Custom integrations</li>
                      </ul>
                      <button
                        @click="upgradePlan('enterprise')"
                        :disabled="upgrading"
                        class="w-full px-4 py-2 border border-purple-500 text-purple-400 hover:bg-purple-500/10 text-sm font-bold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {{ upgrading ? 'Processing...' : (isPaidPlan ? 'Upgrade to Enterprise' : 'Upgrade to Enterprise') }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Change Password -->
        <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
          <div class="px-6 py-8 sm:p-10">
            <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
              <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
              Change Password
            </h4>
            <form @submit.prevent="handleUpdatePassword">
              <div class="space-y-6">
                <div>
                  <label for="current_password" class="block text-sm font-medium text-gray-300">Current Password</label>
                  <div class="mt-1">
                    <input
                      id="current_password"
                      v-model="passwordForm.current_password"
                      type="password"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    />
                  </div>
                </div>
                <div>
                  <label for="password" class="block text-sm font-medium text-gray-300">New Password</label>
                  <div class="mt-1">
                    <input
                      id="password"
                      v-model="passwordForm.password"
                      type="password"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    />
                  </div>
                </div>
                <div>
                  <label for="password_confirmation" class="block text-sm font-medium text-gray-300">Confirm New Password</label>
                  <div class="mt-1">
                    <input
                      id="password_confirmation"
                      v-model="passwordForm.password_confirmation"
                      type="password"
                      required
                      class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    />
                  </div>
                </div>
              </div>
              <div class="mt-8 flex justify-end">
                <button
                  type="submit"
                  :disabled="passwordLoading"
                  class="inline-flex justify-center py-2.5 px-6 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
                >
                  {{ passwordLoading ? 'Saving...' : 'Change Password' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Add Credit Modal -->
      <div v-if="showAddCreditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showAddCreditModal = false">
        <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full p-6">
          <div class="flex items-center justify-between mb-4">
            <h5 class="text-lg font-bold text-white">Add Credit</h5>
            <button @click="showAddCreditModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Amount (SATS)</label>
              <input
                v-model.number="creditAmount"
                type="number"
                min="1"
                step="1"
                class="w-full px-4 py-3 border border-gray-600 rounded-lg text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Enter amount"
              />
            </div>
            <div class="flex gap-3">
              <button
                @click="showAddCreditModal = false"
                class="flex-1 px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
              >
                Cancel
              </button>
              <button
                @click="handleAddCredit"
                :disabled="!creditAmount || creditAmount < 1 || addingCredit"
                class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {{ addingCredit ? 'Processing...' : 'Add Credit' }}
              </button>
            </div>
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
const subscriber = ref<any>(null);
const creditBalance = ref(0);
const showAddCreditModal = ref(false);
const creditAmount = ref<number | null>(null);
const addingCredit = ref(false);
const loadingSubscription = ref(false);

// Plan information
const currentPlanName = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') return 'Enterprise';
  if (role === 'pro') return 'Pro';
  return 'Free';
});

const currentPlanPrice = computed(() => {
  const role = authStore.user?.role || 'merchant';
  if (role === 'enterprise') return '1,000 sats';
  if (role === 'pro') return '100 sats';
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

// Upgrade options logic
const showUpgradeOptions = computed(() => {
  const role = authStore.user?.role || 'merchant';
  // Show upgrades if user is merchant (free) or pro (can upgrade to enterprise)
  // Don't show for enterprise (top tier) or admin/support (not applicable)
  return role === 'merchant' || role === 'pro';
});

const showProUpgrade = computed(() => {
  const role = authStore.user?.role || 'merchant';
  return role === 'merchant';
});

const showEnterpriseUpgrade = computed(() => {
  const role = authStore.user?.role || 'merchant';
  return role === 'merchant' || role === 'pro';
});

onMounted(async () => {
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || '';
    profileForm.value.email = authStore.user.email || '';
  }
  
  // Load subscription details if user has paid plan or might have subscription
  await loadSubscriptionDetails();
});

async function loadSubscriptionDetails() {
  loadingSubscription.value = true;
  try {
    const response = await api.get('/subscriptions/details');
    subscriber.value = response.data.subscriber;
    creditBalance.value = response.data.creditBalance || 0;
  } catch (error: any) {
    // If 404, user doesn't have subscription yet - that's ok
    if (error.response?.status !== 404) {
      console.error('Error loading subscription details:', error);
    }
    // Reset values on error
    subscriber.value = null;
    creditBalance.value = 0;
  } finally {
    loadingSubscription.value = false;
  }
}

async function handleAddCredit() {
  if (!creditAmount.value || creditAmount.value < 1) return;
  
  addingCredit.value = true;
  try {
    const response = await api.post('/subscriptions/credits', {
      amount: creditAmount.value,
      currency: 'SATS',
    });

    // Credit addition creates an invoice - redirect to payment
    if (response.data.invoiceUrl) {
      window.location.href = response.data.invoiceUrl;
    } else if (response.data.invoiceId) {
      // If only invoice ID is returned, construct URL
      const baseUrl = response.data.baseUrl || 'https://pay.dvadsatjeden.org';
      window.location.href = `${baseUrl}/i/${response.data.invoiceId}`;
    } else {
      // No invoice URL - this shouldn't happen, but handle gracefully
      alert('Credit invoice created. Please check your email or BTCPay dashboard for payment instructions.');
      showAddCreditModal.value = false;
      creditAmount.value = null;
    }
  } catch (error: any) {
    console.error('Failed to add credit:', error);
    alert(error.response?.data?.message || 'Failed to add credit. Please try again.');
  } finally {
    addingCredit.value = false;
  }
}

function formatDate(timestamp: number | string): string {
  if (!timestamp) return '';
  const date = typeof timestamp === 'number' ? new Date(timestamp * 1000) : new Date(timestamp);
  return date.toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
}

function formatSats(amount: number): string {
  return new Intl.NumberFormat('en-US').format(amount) + ' sats';
}

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

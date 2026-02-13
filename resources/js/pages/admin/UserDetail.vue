<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex items-center gap-4">
      <button
        @click="$router.push('/admin/users')"
        class="text-gray-400 hover:text-white transition-colors flex items-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        {{ t('admin.user_detail.back') }}
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-12 w-12 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <template v-else-if="user">
      <!-- User header -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-white">{{ user.email }}</h1>
            <div class="flex flex-wrap items-center gap-3 mt-2">
              <span
                class="px-2 py-1 text-xs font-semibold rounded-full"
                :class="getRoleBadgeClass(user.role || 'free')"
              >
                {{ formatRole(user.role || 'free') }}
              </span>
              <span
                v-if="user.email_verified_at"
                class="px-2 py-1 text-xs font-semibold rounded-full bg-green-500/20 text-green-400"
              >
                {{ t('admin.user_detail.verified') }}
              </span>
              <span
                v-else
                class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-500/20 text-yellow-400"
              >
                {{ t('admin.user_detail.unverified') }}
              </span>
              <span class="text-sm text-gray-400">
                {{ t('admin.user_detail.registered') }} {{ formatDate(user.created_at) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <button
              @click="openEditModal"
              class="px-4 py-2 text-sm font-medium text-indigo-400 border border-indigo-500/50 rounded-lg hover:bg-indigo-500/10 transition-colors"
            >
              {{ t('admin.user_detail.edit') }}
            </button>
            <button
              v-if="user.id !== currentUser?.id"
              @click="confirmDelete"
              class="px-4 py-2 text-sm font-medium text-red-400 border border-red-500/50 rounded-lg hover:bg-red-500/10 transition-colors"
            >
              {{ t('admin.user_detail.delete') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <p class="text-gray-400 text-xs font-medium mb-1">{{ t('admin.user_detail.stores_count') }}</p>
          <p class="text-xl font-bold text-white">{{ user.stores?.length ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <p class="text-gray-400 text-xs font-medium mb-1">{{ t('admin.user_detail.pos_terminals') }}</p>
          <p class="text-xl font-bold text-white">{{ posTerminalsTotal }}</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <p class="text-gray-400 text-xs font-medium mb-1">{{ t('admin.user_detail.pos_orders_paid') }}</p>
          <p class="text-xl font-bold text-white">{{ user.pos_orders_paid_total ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <p class="text-gray-400 text-xs font-medium mb-1">{{ t('admin.user_detail.subscription') }}</p>
          <p class="text-xl font-bold" :class="subscriptionStatusClass">
            {{ formatSubscriptionStatus(user.subscription) }}
          </p>
        </div>
      </div>

      <!-- Revenue (from stats) -->
      <div v-if="stats?.overall" class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ t('admin.user_detail.revenue_stats') }}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-gray-400 text-sm">{{ t('admin.user_detail.invoices_all') }}</p>
            <p class="text-white font-semibold">{{ stats.overall.invoice_count_all_time ?? 0 }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-sm">{{ t('admin.user_detail.invoices_30d') }}</p>
            <p class="text-white font-semibold">{{ stats.overall.invoice_count_30d ?? 0 }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-sm">{{ t('admin.user_detail.amount_all') }}</p>
            <p class="text-white font-semibold">{{ formatAmount(stats.overall.paid_amount_all_time) }} {{ primaryCurrency }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-sm">{{ t('admin.user_detail.amount_30d') }}</p>
            <p class="text-white font-semibold">{{ formatAmount(stats.overall.paid_amount_30d) }} {{ primaryCurrency }}</p>
          </div>
        </div>
      </div>

      <!-- Stores list -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <h2 class="text-lg font-semibold text-white px-6 py-4 border-b border-gray-700">{{ t('admin.user_detail.stores') }}</h2>
        <div v-if="!user.stores?.length" class="px-6 py-12 text-center text-gray-400">
          {{ t('admin.user_detail.no_stores') }}
        </div>
        <div v-else class="divide-y divide-gray-700">
          <div
            v-for="store in user.stores"
            :key="store.id"
            class="px-6 py-4 hover:bg-gray-700/30 transition-colors flex flex-wrap items-center justify-between gap-4"
          >
            <div>
              <p class="font-medium text-white">{{ store.name }}</p>
              <div class="flex flex-wrap gap-2 mt-1 text-sm text-gray-400">
                <span>{{ store.wallet_type || '—' }}</span>
                <span>•</span>
                <span>{{ t('admin.user_detail.pos_terminals') }}: {{ store.pos_terminal_count }}</span>
                <span
                  v-if="store.wallet_connection_status"
                  class="px-1.5 py-0.5 rounded text-xs"
                  :class="getWalletStatusClass(store.wallet_connection_status)"
                >
                  {{ store.wallet_connection_status }}
                </span>
              </div>
            </div>
            <div class="text-sm text-gray-400">
              {{ formatDate(store.created_at) }}
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="text-center py-16 text-gray-400">
      {{ t('admin.user_detail.not_found') }}
    </div>

    <!-- Edit modal (reuse logic from Users.vue - simplified) -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      @click.self="closeEditModal"
    >
      <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl max-w-md w-full p-6">
        <h2 class="text-2xl font-bold text-white mb-6">{{ t('admin.user_detail.edit_user') }}</h2>
        <form @submit.prevent="handleUpdateUser" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
            <input
              v-model="editForm.email"
              type="email"
              required
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{{ t('admin.user_detail.role') }}</label>
            <Select v-model="editForm.role" :options="roleEditOptions" placeholder="Select role" />
          </div>
          <div v-if="editError" class="rounded-lg bg-red-500/10 border border-red-500/20 p-3">
            <p class="text-sm text-red-400">{{ editError }}</p>
          </div>
          <div class="flex justify-end gap-3 pt-4">
            <button type="button" @click="closeEditModal" class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600">
              {{ t('admin.user_detail.cancel') }}
            </button>
            <button type="submit" :disabled="updateLoading" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 disabled:opacity-50">
              {{ updateLoading ? '...' : t('admin.user_detail.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete confirmation -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl max-w-md w-full p-6">
        <h2 class="text-2xl font-bold text-white mb-4">{{ t('admin.user_detail.delete_user') }}</h2>
        <p class="text-gray-300 mb-6">
          {{ t('admin.user_detail.delete_confirm', { email: user?.email }) }}
        </p>
        <div v-if="deleteError" class="rounded-lg bg-red-500/10 border border-red-500/20 p-3 mb-4">
          <p class="text-sm text-red-400">{{ deleteError }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600">
            {{ t('admin.user_detail.cancel') }}
          </button>
          <button @click="handleDeleteUser" :disabled="deleteLoading" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-500 disabled:opacity-50">
            {{ deleteLoading ? '...' : t('admin.user_detail.delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';
import Select from '../../components/ui/Select.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(true);
const user = ref<any>(null);
const showEditModal = ref(false);
const showDeleteConfirm = ref(false);
const editForm = ref({ email: '', role: 'free' });
const updateLoading = ref(false);
const deleteLoading = ref(false);
const editError = ref('');
const deleteError = ref('');

const currentUser = computed(() => authStore.user);

const roleEditOptions = [
  { label: 'Free', value: 'free' },
  { label: 'Pro', value: 'pro' },
  { label: 'Enterprise', value: 'enterprise' },
  { label: 'Support', value: 'support' },
  { label: 'Admin', value: 'admin' },
];

const stats = computed(() => user.value?.stats ?? null);
const primaryCurrency = computed(() => {
  const stores = stats.value?.stores ?? [];
  return stores[0]?.basic?.currency ?? 'EUR';
});

const posTerminalsTotal = computed(() => {
  const stores = user.value?.stores ?? [];
  return stores.reduce((sum: number, s: any) => sum + (s.pos_terminal_count ?? 0), 0);
});

const loadUser = async () => {
  const id = route.params.id;
  if (!id) return;
  loading.value = true;
  try {
    const res = await api.get(`/admin/users/${id}`);
    user.value = res.data.data;
  } catch (e) {
    console.error('Failed to load user:', e);
    user.value = null;
  } finally {
    loading.value = false;
  }
};

watch(() => route.params.id, loadUser, { immediate: true });
onMounted(loadUser);

const openEditModal = () => {
  if (!user.value) return;
  editForm.value = { email: user.value.email, role: user.value.role || 'free' };
  editError.value = '';
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
};

const handleUpdateUser = async () => {
  if (!user.value) return;
  updateLoading.value = true;
  editError.value = '';
  try {
    await api.put(`/admin/users/${user.value.id}`, editForm.value);
    user.value = { ...user.value, ...editForm.value };
    closeEditModal();
  } catch (e: any) {
    editError.value = e.response?.data?.message ?? 'Failed to update.';
  } finally {
    updateLoading.value = false;
  }
};

const confirmDelete = () => {
  deleteError.value = '';
  showDeleteConfirm.value = true;
};

const handleDeleteUser = async () => {
  if (!user.value) return;
  deleteLoading.value = true;
  deleteError.value = '';
  try {
    await api.delete(`/admin/users/${user.value.id}`);
    router.push('/admin/users');
  } catch (e: any) {
    deleteError.value = e.response?.data?.message ?? 'Failed to delete.';
  } finally {
    deleteLoading.value = false;
  }
};

const formatRole = (r: string) => r.charAt(0).toUpperCase() + r.slice(1);
const formatDate = (s: string) => new Date(s).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
const formatAmount = (n: number) => (n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const getRoleBadgeClass = (role: string) => {
  const m: Record<string, string> = { free: 'bg-gray-500/20 text-gray-300', pro: 'bg-blue-500/20 text-blue-300', enterprise: 'bg-purple-500/20 text-purple-300', support: 'bg-yellow-500/20 text-yellow-300', admin: 'bg-red-500/20 text-red-300' };
  return m[role] ?? m.free;
};

const formatSubscriptionStatus = (sub: any) => {
  if (!sub) return '—';
  const plan = sub.plan ?? 'free';
  const status = sub.status ?? 'none';
  if (status === 'none') return plan;
  return `${plan} (${status})`;
};

const subscriptionStatusClass = computed(() => {
  const s = user.value?.subscription?.status;
  if (s === 'active') return 'text-emerald-400';
  if (s === 'grace') return 'text-amber-400';
  if (s === 'expired') return 'text-red-400';
  return 'text-gray-400';
});

const getWalletStatusClass = (status: string) => {
  if (status === 'connected') return 'bg-green-500/20 text-green-400';
  if (status === 'needs_support') return 'bg-amber-500/20 text-amber-400';
  return 'bg-gray-500/20 text-gray-400';
};
</script>

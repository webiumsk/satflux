<template>
  <!-- Mobile menu button -->
  <button
    v-if="store"
    @click="showMobileMenu = !showMobileMenu"
    class="fixed top-4 left-4 z-50 lg:hidden p-2 bg-gray-800 text-white rounded-md"
  >
    <!-- Arrow right when closed, arrow left when open -->
    <svg v-if="!showMobileMenu" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
    </svg>
    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
    </svg>
  </button>

  <!-- Mobile overlay -->
  <div
    v-if="showMobileMenu"
    class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
    @click="showMobileMenu = false"
  ></div>

  <aside
    v-if="store"
    class="fixed lg:static inset-y-0 pt-12 md:pt-0 left-0 z-40 w-64 bg-gray-800 text-white flex-shrink-0 min-h-full transform transition-transform duration-300 ease-in-out flex flex-col"
    :class="{ '-translate-x-full lg:translate-x-0': !showMobileMenu }"
  >
    <div class="flex-1 overflow-y-auto p-4">
      <!-- Store Selector Dropdown -->
      <div class="mb-6 relative store-dropdown-container">
        <button
          @click.stop="showStoreDropdown = !showStoreDropdown"
          class="w-full flex items-center justify-between px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-left transition-colors"
        >
          <span class="text-sm font-medium truncate flex-1">{{ store.name }}</span>
          <svg 
            class="w-4 h-4 ml-2 flex-shrink-0 transition-transform"
            :class="{ 'rotate-180': showStoreDropdown }"
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Dropdown Menu -->
        <div
          v-if="showStoreDropdown"
          class="absolute z-50 mt-1 w-full bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 max-h-96 overflow-y-auto"
          @click.stop
        >
          <div class="py-1">
            <!-- Store List -->
            <button
              v-for="s in allStores"
              :key="s.id"
              @click="selectStore(s.id)"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
              :class="{ 'bg-gray-100 font-medium': s.id === store.id }"
            >
              <div class="flex items-center">
                <svg 
                  v-if="s.id === store.id"
                  class="w-4 h-4 mr-2 text-indigo-600" 
                  fill="currentColor" 
                  viewBox="0 0 20 20"
                >
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span class="truncate">{{ s.name }}</span>
              </div>
            </button>

            <!-- Divider -->
            <div v-if="allStores.length > 0" class="border-t border-gray-200 my-1"></div>

            <!-- Create Store Button -->
            <button
              @click="createStore"
              class="w-full text-left px-4 py-2 text-sm text-indigo-600 hover:bg-gray-100 font-medium transition-colors flex items-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Create Store
            </button>
          </div>
        </div>
      </div>

      <!-- Store Name -->
      <router-link
        :to="{ name: 'stores-show', params: { id: store.id } }"
        class="flex items-center text-lg font-semibold mb-6 text-white hover:text-gray-200 transition-colors cursor-pointer"
      >
        <img
          v-if="store.logo_url"
          :src="store.logo_url"
          :alt="`${store.name} logo`"
          class="mr-3 h-8 w-8 object-contain rounded flex-shrink-0"
        />
        <span>{{ store.name }}</span>
      </router-link>
      
      <!-- PAYMENTS Section -->
      <div class="mb-8">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">PAYMENTS</h3>
        <nav class="space-y-1">
          <button
            @click="handleSectionClick('invoices')"
            class="w-full flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors text-left"
            :class="
              $route.query.section === 'invoices'
                ? 'bg-gray-900 text-white'
                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Invoices
          </button>
          <button
            @click="handleSectionClick('exports')"
            class="w-full flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors text-left"
            :class="
              $route.query.section === 'exports'
                ? 'bg-gray-900 text-white'
                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Exports
          </button>
        </nav>
      </div>

      <!-- PLUGINS Section -->
      <div class="mb-8">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">PLUGINS</h3>
        <nav class="space-y-1">
          <!-- LN Address -->
          <div class="mb-2">
            <router-link
              :to="`/stores/${store.id}/lightning-addresses`"
              class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors"
              :class="
                $route.name === 'stores-lightning-addresses'
                  ? 'bg-gray-900 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white'
              "
            >
              <span>LN Address</span>
            </router-link>
          </div>          

          <!-- Point of Sale -->
          <div class="mb-2">
            <router-link
              :to="{ name: 'stores-apps-create', params: { id: store.id }, query: { type: 'PointOfSale' } }"
              class="flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer"
              :class="
                $route.name === 'stores-apps-create' && $route.query.type === 'PointOfSale'
                  ? 'bg-gray-900 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white'
              "
            >
              <span>Point of Sale</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </router-link>
            <div v-if="getAppsByType('PointOfSale').length > 0" class="ml-4 space-y-1">
              <router-link
                v-for="app in getAppsByType('PointOfSale')"
                :key="app.id"
                :to="`/stores/${store.id}/apps/${app.id}`"
                class="block px-3 py-2 rounded-md text-sm transition-colors"
                :class="
                  $route.params.appId === app.id
                    ? 'bg-gray-900 text-white'
                    : 'text-gray-400 hover:bg-gray-700 hover:text-white'
                "
              >
                {{ app.name }}
              </router-link>
            </div>
            <div v-else class="ml-4 text-xs text-gray-500">No PoS</div>
          </div>

          <!-- Crowdfund -->
          <div class="mb-2">
            <router-link
              :to="{ name: 'stores-apps-create', params: { id: store.id }, query: { type: 'Crowdfund' } }"
              class="flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer"
              :class="
                $route.name === 'stores-apps-create' && $route.query.type === 'Crowdfund'
                  ? 'bg-gray-900 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white'
              "
            >
              <span>Crowdfund</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </router-link>
            <div v-if="getAppsByType('Crowdfund').length > 0" class="ml-4 space-y-1">
              <router-link
                v-for="app in getAppsByType('Crowdfund')"
                :key="app.id"
                :to="`/stores/${store.id}/apps/${app.id}`"
                class="block px-3 py-2 rounded-md text-sm transition-colors"
                :class="
                  $route.params.appId === app.id
                    ? 'bg-gray-900 text-white'
                    : 'text-gray-400 hover:bg-gray-700 hover:text-white'
                "
              >
                {{ app.name }}
              </router-link>
            </div>
            <div v-else class="ml-4 text-xs text-gray-500">No crowdfunds</div>
          </div>

          <!-- Pay Button -->
          <div class="mb-2">
            <router-link
              :to="`/stores/${store.id}/pay-button`"
              class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors"
              :class="
                $route.name === 'stores-pay-button'
                  ? 'bg-gray-900 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white'
              "
            >
              <span>Pay Button</span>
            </router-link>
          </div>

          <!-- E-shop Integration -->
          <div class="mb-2">
            <router-link
              :to="`/stores/${store.id}/api-keys`"
              class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors"
              :class="
                $route.name === 'stores-api-keys'
                  ? 'bg-gray-900 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white'
              "
            >
              <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
              </svg>
              E-shop Integration
            </router-link>
          </div>
        </nav>
      </div>

      <!-- WALLET Section -->
      <div class="mb-8">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">WALLET</h3>
        <nav class="space-y-1">
          <router-link
            :to="`/stores/${store.id}/wallet-connection`"
            class="flex items-center justify-center px-3 py-2 rounded-md text-sm font-medium transition-colors"
            :class="
              $route.name === 'stores-wallet-connection'
                ? 'bg-gray-900 text-white'
                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
            "
          >
            <svg 
              class="w-5 h-5 mr-3" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
              :class="getWalletConnectionIconClass()"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span :class="getWalletConnectionIconClass()">Wallet Connection</span>
          </router-link>
        </nav>
      </div>

      <!-- Settings Link -->
      <div class="mt-8">
        <button
          @click="$emit('show-settings')"
          class="w-full flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-700 bg-gray-700 hover:bg-gray-600 text-white transition-colors"
          :class="{ 'bg-gray-900': $route.name === 'stores-show' && $route.query.section === 'settings' }"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Settings
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';

interface Props {
  store: {
    id: string;
    name: string;
    wallet_connection?: {
      status: 'pending' | 'needs_support' | 'connected';
    } | null;
  } | null;
  apps: Array<{
    id: string;
    name: string;
    app_type: string;
  }>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'create-app': [];
  'show-settings': [];
  'show-section': [section: string];
}>();

const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();
const showStoreDropdown = ref(false);
const showMobileMenu = ref(false);

const allStores = computed(() => storesStore.stores);

onMounted(async () => {
  // Load stores if not already loaded
  if (storesStore.stores.length === 0) {
    await storesStore.fetchStores();
  }

  // Close dropdown on outside click
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

function handleClickOutside(event: Event) {
  const target = event.target as HTMLElement;
  const dropdown = target.closest('.store-dropdown-container');
  if (!dropdown && showStoreDropdown.value) {
    showStoreDropdown.value = false;
  }
}

function selectStore(storeId: string) {
  showStoreDropdown.value = false;
  showMobileMenu.value = false; // Close mobile menu on store change
  if (storeId !== props.store?.id) {
    router.push({ name: 'stores-show', params: { id: storeId } });
  }
}

function createStore() {
  showStoreDropdown.value = false;
  showMobileMenu.value = false; // Close mobile menu
  router.push({ name: 'stores-create' });
}


function getAppsByType(type: string) {
  // Handle different type formats (Crowdfund, PointOfSale, PaymentButton)
  // Normalize both to lowercase for comparison
  const normalizedType = type.toLowerCase().replace(/[_-]/g, '');
  return props.apps.filter(app => {
    const appType = (app.app_type || '').toString().toLowerCase().replace(/[_-]/g, '');
    return appType === normalizedType || appType.includes(normalizedType);
  });
}

// createApp function removed - now using router links directly

function handleSectionClick(section: string) {
  showMobileMenu.value = false; // Close mobile menu on navigation
  emit('show-section', section);
}

function getWalletConnectionIconClass(): string {
  if (!props.store?.wallet_connection) {
    return 'text-red-400'; // Non-existent - red
  }
  
  const status = props.store.wallet_connection.status;
  switch (status) {
    case 'connected':
      return 'text-green-400'; // Connected - green
    case 'needs_support':
      return 'text-blue-400'; // Needs support - blue
    case 'pending':
    default:
      return 'text-gray-300'; // Pending/default - gray
  }
}

// Watch for route changes to close mobile menu
watch(() => router.currentRoute.value.fullPath, () => {
  showMobileMenu.value = false;
});
</script>


<template>
  <header class="bg-white shadow-sm border-b border-gray-200 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16 relative">
        <!-- Left side: Empty on mobile, Logo + Navigation on desktop -->
        <div class="hidden md:flex items-center space-x-8">
          <!-- Logo -->
          <router-link to="/dashboard" class="flex items-center">
            <div class="flex-shrink-0">
              <span class="text-2xl font-bold text-indigo-600">UZOL21</span>
            </div>
          </router-link>

          <!-- Navigation Menu (Desktop only) -->
          <nav class="hidden md:flex space-x-4">
          <router-link
            to="/dashboard"
            class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
            :class="
              $route.name === 'home'
                ? 'text-indigo-600 bg-indigo-50'
                : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50'
            "
          >
            Dashboard
          </router-link>
            <router-link
              to="/stores"
              class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
              :class="
                $route.name?.toString().startsWith('stores')
                  ? 'text-indigo-600 bg-indigo-50'
                  : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50'
              "
            >
              Stores
            </router-link>
            <router-link
              v-if="authStore.user?.role === 'support' || authStore.user?.role === 'admin'"
              to="/support/wallet-connections"
              class="px-3 py-2 rounded-md text-sm font-medium transition-colors relative"
              :class="
                $route.name === 'support-wallet-connections'
                  ? 'text-indigo-600 bg-indigo-50'
                  : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50'
              "
            >
              Support
              <span
                v-if="supportCount > 0"
                class="absolute top-1 -right-1 inline-flex items-center justify-center px-1 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full min-w-[20px]"
              >
                {{ supportCount > 99 ? '99+' : supportCount }}
              </span>
            </router-link>
          </nav>
        </div>

        <!-- Center: Logo on mobile only -->
        <div class="md:hidden absolute left-1/2 transform -translate-x-1/2">
          <router-link to="/dashboard" class="flex items-center">
            <div class="flex-shrink-0">
              <span class="text-2xl font-bold text-indigo-600">UZOL21</span>
            </div>
          </router-link>
        </div>

        <!-- Right side: Mobile menu button (mobile) / User button (desktop) -->
        <div class="flex items-center ml-auto md:ml-0">
          <!-- Mobile menu button (right side, mobile only) -->
          <button
            @click="showMobileMenu = !showMobileMenu"
            class="md:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Open menu"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- Desktop: User button with dropdown -->
          <div class="hidden md:block relative" v-click-outside="closeUserMenu">
            <button
              @click="handleUserButtonClick"
              class="flex items-center space-x-3 p-2 rounded-full hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              aria-label="User menu"
            >
              <div
                class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium"
              >
                {{ userInitials }}
              </div>
              <!-- Dropdown arrow -->
              <svg
                class="w-4 h-4 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </button>

            <!-- User Dropdown Menu (Desktop only) -->
            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-if="showUserMenu"
                class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
              >
                <div class="py-1">
                  <div class="px-4 py-2 border-b border-gray-200">
                    <p class="text-sm font-medium text-gray-900">
                      {{ authStore.user?.email }}
                    </p>
                  </div>
                  <router-link
                    to="/account"
                    @click="closeUserMenu"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                  >
                    Profile
                  </router-link>
                  <button
                    @click="handleLogout"
                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                  >
                    Sign out
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile overlay -->
    <div
      v-if="showMobileMenu"
      class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
      @click="showMobileMenu = false"
    ></div>

    <!-- Mobile drawer (right side) -->
    <aside
      class="fixed right-0 top-0 z-50 w-64 bg-white h-full shadow-xl transform transition-transform duration-300 ease-in-out flex flex-col md:hidden"
      :class="{ 'translate-x-full': !showMobileMenu, 'translate-x-0': showMobileMenu }"
    >
      <div class="flex-1 overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Menu</h2>
          <button
            @click="showMobileMenu = false"
            class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Close menu"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4 space-y-1">
          <router-link
            to="/dashboard"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-md text-base font-medium transition-colors"
            :class="
              $route.name === 'home'
                ? 'bg-indigo-50 text-indigo-600'
                : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
          </router-link>
          <router-link
            to="/stores"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-md text-base font-medium transition-colors"
            :class="
              $route.name?.toString().startsWith('stores')
                ? 'bg-indigo-50 text-indigo-600'
                : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            Stores
          </router-link>
          <router-link
            v-if="authStore.user?.role === 'support' || authStore.user?.role === 'admin'"
            to="/support/wallet-connections"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-md text-base font-medium transition-colors relative"
            :class="
              $route.name === 'support-wallet-connections'
                ? 'bg-indigo-50 text-indigo-600'
                : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Support
            <span
              v-if="supportCount > 0"
              class="ml-auto inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-[20px]"
            >
              {{ supportCount > 99 ? '99+' : supportCount }}
            </span>
          </router-link>
        </nav>
      </div>

      <!-- User Menu at bottom -->
      <div class="border-t border-gray-200 p-4">
        <div class="px-4 py-3 mb-3">
          <p class="text-sm font-medium text-gray-900">
            {{ authStore.user?.email }}
          </p>
        </div>
        <router-link
          to="/account"
          @click="closeMobileMenu"
          class="flex items-center px-4 py-3 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50 transition-colors"
        >
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Profile
        </router-link>
        <button
          @click="handleLogout"
          class="flex items-center w-full px-4 py-3 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50 transition-colors text-left"
        >
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          Sign out
        </button>
      </div>
    </aside>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';

const router = useRouter();
const authStore = useAuthStore();
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const supportCount = ref(0);
let supportCountInterval: ReturnType<typeof setInterval> | null = null;

const userInitials = computed(() => {
  if (!authStore.user?.email) return '?';
  const email = authStore.user.email;
  const firstChar = email.charAt(0).toUpperCase();
  // Try to get a second character if available
  const match = email.match(/[a-zA-Z]/g);
  if (match && match.length > 1) {
    return firstChar + match[1].toUpperCase();
  }
  return firstChar;
});

const closeUserMenu = () => {
  showUserMenu.value = false;
};

const closeMobileMenu = () => {
  showMobileMenu.value = false;
};

const handleUserButtonClick = () => {
  // Desktop only: toggle dropdown
  showUserMenu.value = !showUserMenu.value;
};

const handleLogout = async () => {
  closeUserMenu();
  closeMobileMenu();
  try {
    await authStore.logout();
    router.push({ name: 'login' });
  } catch (error) {
    console.error('Logout error:', error);
    // Still redirect to login even if logout API call fails
    router.push({ name: 'login' });
  }
};

const loadSupportCount = async () => {
  if (authStore.user?.role !== 'support' && authStore.user?.role !== 'admin') {
    return;
  }

  try {
    const response = await api.get('/support/count');
    supportCount.value = response.data.data?.total || 0;
  } catch (error) {
    console.error('Failed to load support count:', error);
    supportCount.value = 0;
  }
};

// Watch for route changes to refresh count and close mobile menu
router.afterEach(() => {
  // Close mobile menu on navigation
  closeMobileMenu();
  
  if (authStore.user?.role === 'support' || authStore.user?.role === 'admin') {
    // Small delay to ensure any status changes are saved
    setTimeout(() => {
      loadSupportCount();
    }, 1000);
  }
});

onMounted(() => {
  // Load support count if user has support/admin role
  if (authStore.user?.role === 'support' || authStore.user?.role === 'admin') {
    loadSupportCount();
    // Refresh count every 30 seconds
    supportCountInterval = setInterval(loadSupportCount, 30000);
  }
});

onUnmounted(() => {
  if (supportCountInterval) {
    clearInterval(supportCountInterval);
  }
});

onUnmounted(() => {
  if (supportCountInterval) {
    clearInterval(supportCountInterval);
  }
});

// Click outside directive
const vClickOutside = {
  mounted(el: HTMLElement, binding: any) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target as Node))) {
        binding.value();
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el: HTMLElement) {
    if (el.clickOutsideEvent) {
      document.removeEventListener('click', el.clickOutsideEvent);
    }
  },
};
</script>


<template>
  <header class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <!-- Logo and Navigation -->
        <div class="flex items-center space-x-8">
          <!-- Logo -->
          <router-link to="/" class="flex items-center">
            <div class="flex-shrink-0">
              <span class="text-2xl font-bold text-indigo-600">UZOL21</span>
            </div>
          </router-link>

          <!-- Navigation Menu -->
          <nav class="hidden md:flex space-x-4">
            <router-link
              to="/"
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
                class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full min-w-[20px]"
              >
                {{ supportCount > 99 ? '99+' : supportCount }}
              </span>
            </router-link>
          </nav>
        </div>

        <!-- User Menu -->
        <div class="flex items-center">
          <div class="relative" v-click-outside="closeUserMenu">
            <button
              @click="showUserMenu = !showUserMenu"
              class="flex items-center space-x-3 p-2 rounded-full hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              aria-label="User menu"
            >
              <div
                class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium"
              >
                {{ userInitials }}
              </div>
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

            <!-- User Dropdown Menu -->
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

const handleLogout = async () => {
  closeUserMenu();
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

// Watch for route changes to refresh count
router.afterEach(() => {
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


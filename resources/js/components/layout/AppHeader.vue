<template>
  <header class="bg-gray-900 border-b border-gray-800 relative z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16 relative">
        <!-- Left side: Empty on mobile, Logo + Navigation on desktop -->
        <div class="hidden md:flex items-center space-x-8">
          <!-- Logo -->
          <router-link to="/dashboard" class="flex items-center gap-3">
             <div class="w-8 h-8 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                U21
             </div>
             <span class="text-xl font-bold text-white tracking-tight">UZOL21</span>
          </router-link>

          <!-- Navigation Menu (Desktop only) -->
          <nav class="hidden md:flex space-x-2">
            <router-link
              to="/dashboard"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
              :class="
                $route.name === 'home'
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              Dashboard
            </router-link>
            <router-link
              to="/stores"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
              :class="
                $route.name?.toString().startsWith('stores')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              Stores
            </router-link>
            <router-link
              v-if="authStore.user?.role === 'support' || authStore.user?.role === 'admin'"
              to="/support/wallet-connections"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all relative"
              :class="
                $route.name === 'support-wallet-connections'
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              Support
              <span
                v-if="supportCount > 0"
                class="absolute top-1 -right-1 inline-flex items-center justify-center px-1 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full min-w-[18px] shadow-sm"
              >
                {{ supportCount > 99 ? '99+' : supportCount }}
              </span>
            </router-link>
          </nav>
        </div>

        <!-- Center: Logo on mobile only -->
        <div class="md:hidden absolute left-1/2 transform -translate-x-1/2">
          <router-link to="/dashboard" class="flex items-center gap-2">
             <div class="w-7 h-7 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">
                U21
             </div>
             <span class="text-lg font-bold text-white tracking-tight">UZOL21</span>
          </router-link>
        </div>

        <!-- Right side: Mobile menu button (mobile) / User button (desktop) -->
        <div class="flex items-center ml-auto md:ml-0 gap-4">
           <!-- Links visible on desktop right side -->
           <a href="https://docs.btcpayserver.org/" target="_blank" class="hidden md:block text-gray-500 hover:text-gray-300 text-sm font-medium transition-colors">
             Docs
           </a>
           
          <!-- Mobile menu button (right side, mobile only) -->
          <button
            @click="showMobileMenu = !showMobileMenu"
            class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Open menu"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-if="!showMobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Desktop: User button with dropdown -->
          <div class="hidden md:block relative" v-click-outside="closeUserMenu">
            <button
              @click="handleUserButtonClick"
              class="flex items-center space-x-3 p-1.5 pl-3 rounded-full hover:bg-gray-800 border border-transparent hover:border-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900"
              aria-label="User menu"
            >
               <span class="text-sm font-medium text-gray-300">{{ userName }}</span>
              <div
                class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold shadow-lg shadow-indigo-500/20"
              >
                {{ userInitials }}
              </div>
            </button>

            <!-- User Dropdown Menu (Desktop only) -->
            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95 translate-y-2"
              enter-to-class="transform opacity-100 scale-100 translate-y-0"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100 translate-y-0"
              leave-to-class="transform opacity-0 scale-95 translate-y-2"
            >
              <div
                v-if="showUserMenu"
                class="absolute right-0 mt-2 w-56 rounded-xl shadow-2xl bg-gray-800 border border-gray-700 ring-1 ring-black ring-opacity-5 z-50 overflow-hidden"
              >
                <div class="px-4 py-3 border-b border-gray-700 bg-gray-800/50">
                   <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Signed in as</p>
                   <p class="text-sm font-medium text-white truncate mt-1">
                    {{ authStore.user?.email }}
                   </p>
                </div>
                <div class="py-1">
                  <router-link
                    to="/account"
                    @click="closeUserMenu"
                    class="group flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                  >
                     <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                     </svg>
                    Profile Settings
                  </router-link>
                  <a href="#" class="group flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                      <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                     </svg>
                     Support
                  </a>
                  <div class="border-t border-gray-700 my-1"></div>
                  <button
                    @click="handleLogout"
                    class="group flex w-full items-center px-4 py-2 text-sm text-red-400 hover:bg-red-900/20 hover:text-red-300 transition-colors"
                  >
                    <svg class="mr-3 h-5 w-5 text-red-500/70 group-hover:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
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
      class="fixed inset-0 bg-black bg-opacity-70 z-40 md:hidden backdrop-blur-sm"
      @click="showMobileMenu = false"
    ></div>

    <!-- Mobile drawer (right side) -->
    <aside
      class="fixed right-0 top-0 z-50 w-72 bg-gray-900 border-l border-gray-800 h-full shadow-2xl transform transition-transform duration-300 ease-in-out flex flex-col md:hidden"
      :class="{ 'translate-x-full': !showMobileMenu, 'translate-x-0': showMobileMenu }"
    >
      <div class="flex-1 overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
          <h2 class="text-lg font-bold text-white">Menu</h2>
          <button
            @click="showMobileMenu = false"
            class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Close menu"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4 space-y-2">
          <router-link
            to="/dashboard"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors"
            :class="
              $route.name === 'home'
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
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
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors"
            :class="
              $route.name?.toString().startsWith('stores')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
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
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors relative"
            :class="
              $route.name === 'support-wallet-connections'
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
          >
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Support
            <span
              v-if="supportCount > 0"
              class="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[20px]"
            >
              {{ supportCount > 99 ? '99+' : supportCount }}
            </span>
          </router-link>
        </nav>
      </div>

      <!-- User Menu at bottom -->
      <div class="border-t border-gray-800 p-4 bg-gray-900/50">
        <div class="px-4 py-3 mb-2">
          <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Signed in as</p>
          <p class="text-sm font-medium text-white truncate">
            {{ authStore.user?.email }}
          </p>
        </div>
        <router-link
          to="/account"
          @click="closeMobileMenu"
          class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
        >
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Profile
        </router-link>
        <button
          @click="handleLogout"
          class="flex items-center w-full px-4 py-3 rounded-xl text-base font-medium text-red-400 hover:bg-red-900/10 hover:text-red-300 transition-colors text-left"
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

const userName = computed(() => {
  if (authStore.user?.email) {
    return authStore.user.email.split('@')[0];
  }
  return '';
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

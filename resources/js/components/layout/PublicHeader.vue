<template>
  <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <!-- Logo -->
        <router-link to="/" class="flex items-center">
          <span class="text-2xl font-bold text-indigo-600">UZOL21</span>
        </router-link>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center space-x-6" v-if="!authStore.isAuthenticated">
          <router-link
            to="/#features"
            @click="handleAnchorClick('/#features')"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Features
          </router-link>
          <router-link
            to="/#pricing"
            @click="handleAnchorClick('/#pricing')"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Pricing
          </router-link>
          <router-link
            to="/support"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Support
          </router-link>
          <router-link
            to="/login"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Sign In
          </router-link>
          <router-link
            to="/register"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
          >
            Get Started Free
          </router-link>
        </nav>
        <nav class="hidden md:flex items-center space-x-6" v-else>
          <router-link
            to="/#features"
            @click="handleAnchorClick('/#features')"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Features
          </router-link>
          <router-link
            to="/#pricing"
            @click="handleAnchorClick('/#pricing')"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Pricing
          </router-link>
          <router-link
            to="/support"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            Support
          </router-link>
          <router-link
            to="/stores"
            class="text-gray-700 hover:text-indigo-600 transition-colors"
          >
            My Stores
          </router-link>
          <router-link
            to="/dashboard"
            class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 transition-colors"
          >
            <div
              class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium"
            >
              {{ userInitials }}
            </div>
            <span>Dashboard</span>
          </router-link>
        </nav>

        <!-- Mobile Menu Button -->
        <button
          @click="showMobileMenu = !showMobileMenu"
          class="md:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          aria-label="Open menu"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"
            />
          </svg>
        </button>
      </div>

      <!-- Mobile Menu -->
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 transform -translate-y-1"
        enter-to-class="opacity-100 transform translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 transform translate-y-0"
        leave-to-class="opacity-0 transform -translate-y-1"
      >
        <div v-if="showMobileMenu" class="md:hidden py-4 border-t border-gray-200">
          <nav class="flex flex-col space-y-4" v-if="!authStore.isAuthenticated">
            <router-link
              to="/#features"
              @click="handleAnchorClick('/#features'); showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Features
            </router-link>
            <router-link
              to="/#pricing"
              @click="handleAnchorClick('/#pricing'); showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Pricing
            </router-link>
            <router-link
              to="/support"
              @click="showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Support
            </router-link>
            <router-link
              to="/login"
              @click="showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Sign In
            </router-link>
            <router-link
              to="/register"
              @click="showMobileMenu = false"
              class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
            >
              Get Started Free
            </router-link>
          </nav>
          <nav class="flex flex-col space-y-4" v-else>
            <router-link
              to="/#features"
              @click="handleAnchorClick('/#features'); showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Features
            </router-link>
            <router-link
              to="/#pricing"
              @click="handleAnchorClick('/#pricing'); showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Pricing
            </router-link>
            <router-link
              to="/support"
              @click="showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              Support
            </router-link>
            <router-link
              to="/stores"
              @click="showMobileMenu = false"
              class="text-gray-700 hover:text-indigo-600 transition-colors"
            >
              My Stores
            </router-link>
            <router-link
              to="/dashboard"
              @click="showMobileMenu = false"
              class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 transition-colors"
            >
              <div
                class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium"
              >
                {{ userInitials }}
              </div>
              <span>Dashboard</span>
            </router-link>
          </nav>
        </div>
      </transition>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../store/auth';

const router = useRouter();
const route = useRoute();
const showMobileMenu = ref(false);
const authStore = useAuthStore();

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

function handleAnchorClick(href: string) {
  const hash = href.split('#')[1];
  if (route.path === '/') {
    // Already on landing page, just scroll
    const target = document.querySelector(`#${hash}`);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  } else {
    // Navigate to landing page with hash
    router.push(href);
  }
}
</script>


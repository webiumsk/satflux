<template>
  <header
    class="bg-gray-900 border-b border-gray-800 sticky top-0 z-50 backdrop-blur-md bg-opacity-90"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-20">
        <!-- Logo -->
        <router-link to="/" class="flex items-center gap-3">
          <!-- Simple Logo Placeholder or SVG -->
          <div
            class="w-8 h-8 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg"
          >
            SF
          </div>
          <span class="text-2xl font-bold text-white tracking-tight"
            >satflux.io</span
          >
        </router-link>

        <!-- Desktop Navigation -->
        <nav
          class="hidden md:flex items-center space-x-8"
          v-if="!authStore.isAuthenticated"
        >
          <router-link
            to="/#features"
            @click="handleAnchorClick('/#features')"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Features
          </router-link>
          <router-link
            to="/#pricing"
            @click="handleAnchorClick('/#pricing')"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Pricing
          </router-link>
          <router-link
            to="/support"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Support
          </router-link>
          <LanguageSwitcher />
          <router-link
            to="/login"
            class="text-white hover:text-indigo-400 font-medium transition-colors"
          >
            Sign In
          </router-link>
          <router-link
            to="/register"
            class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all shadow-lg shadow-indigo-500/20"
          >
            Get Started
          </router-link>
        </nav>
        <nav class="hidden md:flex items-center space-x-8" v-else>
          <router-link
            to="/#features"
            @click="handleAnchorClick('/#features')"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Features
          </router-link>
          <router-link
            to="/#pricing"
            @click="handleAnchorClick('/#pricing')"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Pricing
          </router-link>
          <router-link
            to="/support"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            Support
          </router-link>
          <LanguageSwitcher />
          <router-link
            to="/stores"
            class="text-gray-300 hover:text-white font-medium transition-colors"
          >
            My Stores
          </router-link>
          <router-link
            to="/dashboard"
            class="flex items-center space-x-3 text-gray-300 hover:text-white transition-colors group"
          >
            <div
              class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-md group-hover:shadow-indigo-500/30 transition-shadow"
            >
              {{ userInitials }}
            </div>
          </router-link>
        </nav>

        <!-- Mobile Menu Button -->
        <button
          @click="showMobileMenu = !showMobileMenu"
          class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          aria-label="Open menu"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              v-if="!showMobileMenu"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>

      <!-- Mobile Menu -->
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 transform -translate-y-2"
        enter-to-class="opacity-100 transform translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 transform translate-y-0"
        leave-to-class="opacity-0 transform -translate-y-2"
      >
        <div
          v-if="showMobileMenu"
          class="md:hidden py-4 border-t border-gray-800 bg-gray-900 absolute top-20 left-0 right-0 px-4 shadow-xl"
        >
          <nav
            class="flex flex-col space-y-4"
            v-if="!authStore.isAuthenticated"
          >
            <router-link
              to="/#features"
              @click="
                handleAnchorClick('/#features');
                showMobileMenu = false;
              "
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Features
            </router-link>
            <router-link
              to="/#pricing"
              @click="
                handleAnchorClick('/#pricing');
                showMobileMenu = false;
              "
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Pricing
            </router-link>
            <router-link
              to="/support"
              @click="showMobileMenu = false"
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Support
            </router-link>
            <div class="pt-2 pb-2">
              <LanguageSwitcher />
            </div>
            <div class="pt-4 flex flex-col gap-3">
              <router-link
                to="/login"
                @click="showMobileMenu = false"
                class="text-center px-4 py-3 rounded-lg text-white bg-gray-800 hover:bg-gray-700 font-medium transition-colors"
              >
                Sign In
              </router-link>
              <router-link
                to="/register"
                @click="showMobileMenu = false"
                class="text-center px-4 py-3 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 font-bold transition-colors"
              >
                Get Started Free
              </router-link>
            </div>
          </nav>
          <nav class="flex flex-col space-y-4" v-else>
            <router-link
              to="/#features"
              @click="
                handleAnchorClick('/#features');
                showMobileMenu = false;
              "
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Features
            </router-link>
            <router-link
              to="/#pricing"
              @click="
                handleAnchorClick('/#pricing');
                showMobileMenu = false;
              "
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Pricing
            </router-link>
            <router-link
              to="/support"
              @click="showMobileMenu = false"
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              Support
            </router-link>
            <div class="pt-2 pb-2">
              <LanguageSwitcher />
            </div>
            <router-link
              to="/stores"
              @click="showMobileMenu = false"
              class="text-gray-300 hover:text-white text-lg font-medium transition-colors"
            >
              My Stores
            </router-link>
            <router-link
              to="/dashboard"
              @click="showMobileMenu = false"
              class="flex items-center space-x-3 text-gray-300 hover:text-white text-lg font-medium transition-colors"
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
import { ref, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../../store/auth";
import LanguageSwitcher from "../LanguageSwitcher.vue";

const router = useRouter();
const route = useRoute();
const showMobileMenu = ref(false);
const authStore = useAuthStore();

const userInitials = computed(() => {
  if (!authStore.user?.email) return "?";
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
  const hash = href.split("#")[1];
  if (route.path === "/") {
    // Already on landing page, just scroll
    const target = document.querySelector(`#${hash}`);
    if (target) {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  } else {
    // Navigate to landing page with hash
    router.push(href);
  }
}
</script>

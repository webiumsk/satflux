<template>
  <header
    class="bg-gray-900 border-b border-gray-800 sticky top-0 z-30 backdrop-blur-md bg-opacity-90"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16 relative">
        <!-- Left side: Logo + Navigation on desktop -->
        <div class="hidden md:flex items-center space-x-8">
          <!-- Logo -->
          <router-link to="/" class="flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center p-1">
              <img src="/img/logo-satflux-white.svg" alt="SatFlux" class="w-full h-full" />
            </div>
            <span class="text-xl font-bold text-white tracking-tight"
              >SATFLUX</span
            >
          </router-link>

          <!-- Navigation Menu (Desktop only) -->
          <nav class="hidden md:flex space-x-1">
            <router-link
              to="/#features"
              @click="handleAnchorClick('/#features')"
              class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
            >
              {{ t("header.features") }}
            </router-link>
            <router-link
              to="/#how-it-works"
              @click="handleAnchorClick('/#how-it-works')"
              class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
            >
              {{ t("header.how_it_works") }}
            </router-link>
            <router-link
              to="/#pricing"
              @click="handleAnchorClick('/#pricing')"
              class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
            >
              {{ t("header.pricing") }}
            </router-link>
            <router-link
              to="/support"
              class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
            >
              {{ t("header.support") }}
            </router-link>
          </nav>
        </div>

        <!-- Center: Logo on mobile only -->
        <div class="md:hidden absolute left-1/2 transform -translate-x-1/2">
          <router-link to="/" class="flex items-center gap-2">
            <div class="w-7 h-7 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center p-1">
              <img src="/img/logo-satflux-white.svg" alt="SatFlux" class="w-full h-full" />
            </div>
            <span class="text-lg font-bold text-white tracking-tight"
              >SATFLUX</span
            >
          </router-link>
        </div>

        <!-- Right side: Mobile menu button (mobile) / Auth buttons (desktop) -->
        <div class="flex items-center ml-auto md:ml-0 gap-4">
          <div class="hidden md:block">
            <!-- Language Switcher -->
            <LanguageSwitcher />
          </div>

          <!-- Desktop: Auth buttons or User info -->
          <div class="hidden md:flex items-center gap-3">
            <template v-if="!authStore.isAuthenticated">
              <router-link
                to="/login"
                class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
              >
                {{ t("header.sign_in") }}
              </router-link>
              <router-link
                to="/register"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg shadow-indigo-600/20"
              >
                {{ t("header.start_for_free") }}
              </router-link>
            </template>
            <template v-else>
              <router-link
                to="/stores"
                class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all"
              >
                {{ t("header.my_stores") }}
              </router-link>
              <router-link
                to="/dashboard"
                class="flex items-center space-x-3 p-1.5 pl-3 rounded-full hover:bg-gray-800 border border-transparent hover:border-gray-700 transition-all group"
              >
                <span class="text-sm font-medium text-gray-400 group-hover:text-white transition-colors">{{ userName }}</span>
                <div
                  class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold shadow-lg shadow-indigo-500/20"
                >
                  {{ userInitials }}
                </div>
              </router-link>
            </template>
          </div>

          <!-- Mobile Menu Button (Right side) -->
          <div class="flex items-center md:hidden ml-auto">
            <button
              @click="showMobileMenu = !showMobileMenu"
              class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :aria-label="t('header.open_menu')"
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
        </div>
      </div>
    </div>
  </header>

    <!-- Mobile overlay -->
    <transition
      enter-active-class="transition-opacity ease-linear duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-linear duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showMobileMenu"
        class="fixed inset-0 bg-black bg-opacity-70 z-40 md:hidden backdrop-blur-sm"
        @click="showMobileMenu = false"
      ></div>
    </transition>

    <!-- Mobile drawer (right side) -->
    <aside
      class="fixed right-0 top-0 z-50 w-72 bg-gray-900 border-l border-gray-800 h-full shadow-2xl transform transition-transform duration-300 ease-in-out flex flex-col md:hidden"
      :class="{
        'translate-x-full': !showMobileMenu,
        'translate-x-0': showMobileMenu,
      }"
    >
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-gray-800 shrink-0">
        <h2 class="text-lg font-bold text-white">{{ t("header.menu") }}</h2>
        <LanguageSwitcher />
        <button
          @click="showMobileMenu = false"
          class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :aria-label="t('header.close_menu')"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>

      <!-- Scrollable content -->
      <div class="flex-1 overflow-y-auto overflow-x-hidden">
        <nav class="p-4 space-y-2">
          <router-link
            to="/#features"
            @click="handleAnchorClick('/#features'); closeMobileMenu();"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
          >
            {{ t("header.features") }}
          </router-link>
          <router-link
            to="/#how-it-works"
            @click="handleAnchorClick('/#how-it-works'); closeMobileMenu();"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
          >
            {{ t("header.how_it_works") }}
          </router-link>
          <router-link
            to="/#pricing"
            @click="handleAnchorClick('/#pricing'); closeMobileMenu();"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
          >
            {{ t("header.pricing") }}
          </router-link>
          <router-link
            to="/support"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
          >
            {{ t("header.support") }}
          </router-link>


          <!-- Auth Links (Logged Out) -->
          <div v-if="!authStore.isAuthenticated" class="pt-4 flex flex-col gap-3">
            <router-link
              to="/login"
              @click="closeMobileMenu"
              class="text-center px-4 py-3 rounded-xl text-white bg-gray-800 hover:bg-gray-700 font-medium transition-colors"
            >
              {{ t("header.sign_in") }}
            </router-link>
            <router-link
              to="/register"
              @click="closeMobileMenu"
              class="text-center px-4 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 font-bold transition-colors shadow-lg shadow-indigo-600/20"
            >
              {{ t("header.start_for_free") }}
            </router-link>
          </div>

          <!-- Logged In Links -->
          <template v-else>
            <div class="border-t border-gray-800 my-4"></div>
            <router-link
              to="/dashboard"
              @click="closeMobileMenu"
              class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
            >
              <div
                class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold mr-3"
              >
                {{ userInitials }}
              </div>
              {{ t("header.dashboard") }}
            </router-link>
            <router-link
              to="/stores"
              @click="closeMobileMenu"
              class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
            >
              {{ t("header.my_stores") }}
            </router-link>
          </template>
        </nav>
      </div>

      <!-- Logged In User Menu at bottom -->
      <div v-if="authStore.isAuthenticated" class="border-t border-gray-800 p-4 bg-gray-900/50 shrink-0">
        <div class="px-4 py-3 mb-2">
          <p
            class="text-xs text-gray-500 uppercase font-semibold tracking-wider"
          >
            {{ t("header.signed_in_as") }}
          </p>
          <p class="text-sm font-medium text-white truncate">
            {{ authStore.user?.email }}
          </p>
        </div>
        <button
          @click="handleLogout"
          class="flex items-center w-full px-4 py-3 rounded-xl text-base font-medium text-red-400 hover:bg-red-900/10 hover:text-red-300 transition-colors text-left"
        >
          <svg
            class="w-5 h-5 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
            />
          </svg>
          {{ t("auth.sign_out") }}
        </button>
      </div>
    </aside>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../../store/auth";
import LanguageSwitcher from "../LanguageSwitcher.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

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

const userName = computed(() => {
  if (authStore.user?.email) {
    return authStore.user.email.split("@")[0];
  }
  return "";
});

function closeMobileMenu() {
  showMobileMenu.value = false;
}

const handleLogout = async () => {
  closeMobileMenu();
  try {
    await authStore.logout();
    router.push({ name: "login" });
  } catch (error) {
    console.error("Logout error:", error);
    // Still redirect to login even if logout API call fails
    router.push({ name: "login" });
  }
};

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

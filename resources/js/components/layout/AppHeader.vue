<template>
  <header class="bg-gray-900 border-b border-gray-800 relative z-30">
    <!-- Push notification toast for support/admin (new wallet connection needs support) -->
    <div
      v-if="supportToastVisible && supportToastMessage"
      class="fixed top-4 right-4 z-[100] max-w-sm rounded-lg border border-blue-500/30 bg-gray-800 shadow-lg p-4 flex items-start gap-3"
      role="alert"
    >
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center">
        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-white">{{ supportToastMessage }}</p>
        <a
          :href="supportToastUrl"
          class="mt-2 inline-block text-sm font-medium text-indigo-400 hover:text-indigo-300"
        >
          {{ t("header.view_support") || "View support" }}
        </a>
      </div>
      <button
        type="button"
        class="flex-shrink-0 text-gray-400 hover:text-white"
        aria-label="Close"
        @click="dismissSupportToast"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16 relative">
        <!-- Left side: Empty on mobile, Logo + Navigation on desktop -->
        <div class="hidden md:flex items-center space-x-8">
          <!-- Logo -->
          <component :is="isInertia ? Link : RouterLink" :href="isInertia ? '/' : undefined" :to="!isInertia ? '/' : undefined" class="flex items-center gap-3">
            <div
              class="w-8 h-8 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg"
            >
              SF
            </div>
            <span class="text-xl font-bold text-white tracking-tight"
              >satflux.io            </span
            >
          </component>

          <!-- Navigation Menu (Desktop only) -->
          <nav class="hidden md:flex space-x-2">
            <component
              :is="isInertia ? Link : RouterLink"
              :href="isInertia ? '/dashboard' : undefined"
              :to="!isInertia ? '/dashboard' : undefined"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
              :class="
                isActive('/dashboard', 'home')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              {{ t("header.dashboard") }}
            </component>
            <component
              :is="isInertia ? Link : RouterLink"
              :href="isInertia ? '/stores' : undefined"
              :to="!isInertia ? '/stores' : undefined"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
              :class="
                isActive('/stores')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              {{ t("header.stores") }}
            </component>
            <component
              v-if="
                authStore.user?.role === 'support' ||
                authStore.user?.role === 'admin'
              "
              :is="isInertia ? Link : RouterLink"
              :href="isInertia ? '/support/wallet-connections' : undefined"
              :to="!isInertia ? '/support/wallet-connections' : undefined"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all relative"
              :class="
                isActive('/support/wallet-connections', 'support-wallet-connections')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              Support
              <span
                v-if="supportCount > 0"
                class="absolute top-1 -right-1 inline-flex items-center justify-center px-1 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full min-w-[18px] shadow-sm"
              >
                {{ supportCount > 99 ? "99+" : supportCount }}
              </span>
            </component>
            <component
              v-if="authStore.user?.role === 'admin'"
              :is="isInertia ? Link : RouterLink"
              :href="isInertia ? '/admin' : undefined"
              :to="!isInertia ? '/admin' : undefined"
              class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
              :class="
                isActive('/admin', 'admin-users')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              {{ t("header.admin") }}
            </component>
          </nav>
        </div>

        <!-- Center: Logo on mobile only -->
        <div class="md:hidden absolute left-1/2 transform -translate-x-1/2">
          <component :is="isInertia ? Link : RouterLink" :href="isInertia ? '/' : undefined" :to="!isInertia ? '/' : undefined" class="flex items-center gap-2">
            <div
              class="w-7 h-7 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm"
            >
              SF
            </div>
            <span class="text-lg font-bold text-white tracking-tight"
              >satflux.io            </span
              >
            </component>
        </div>

        <!-- Right side: Mobile menu button (mobile) / User button (desktop) -->
        <div class="flex items-center ml-auto md:ml-0 gap-4">
          <div class="hidden md:block">
            <!-- Language Switcher -->
            <LanguageSwitcher />
          </div>

          <!-- Links visible on desktop right side -->
          <component
              :is="isInertia ? Link : RouterLink"
              :href="isInertia ? '/documentation' : undefined"
              :to="!isInertia ? '/documentation' : undefined"
              class="hidden md:block text-gray-500 hover:text-gray-300 text-sm font-medium transition-colors"
              :class="
                isActive('/documentation', 'home')
                  ? 'text-white bg-indigo-600/20 text-indigo-300'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              "
            >
              {{ t("header.docs") }}
            </component>

          <!-- Mobile menu button (right side, mobile only) -->
          <button
            @click="showMobileMenu = !showMobileMenu"
            class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
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

          <!-- Desktop: User button with dropdown -->
          <div class="hidden md:block relative" v-click-outside="closeUserMenu">
            <button
              @click="handleUserButtonClick"
              class="flex items-center space-x-3 p-1.5 pl-3 rounded-full hover:bg-gray-800 border border-transparent hover:border-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900"
              aria-label="User menu"
            >
              <span class="text-sm font-medium text-gray-300">{{
                userName
              }}</span>
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
                  <p
                    class="text-xs text-gray-400 uppercase tracking-wider font-semibold"
                  >
                    {{ t("header.signed_in_as") }}
                  </p>
                  <p class="text-sm font-medium text-white truncate mt-1">
                    {{ authStore.user?.email }}
                  </p>
                </div>
                <div class="py-1">
                  <component
                    :is="isInertia ? Link : RouterLink"
                    :href="isInertia ? '/account' : undefined"
                    :to="!isInertia ? '/account' : undefined"
                    @click="closeUserMenu"
                    class="group flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                  >
                    <svg
                      class="mr-3 h-5 w-5 text-gray-400 group-hover:text-white"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                      />
                    </svg>
                    {{ t("header.profile_settings") }}
                  </component>
                  <component
                    :is="isInertia ? Link : RouterLink"
                    :href="isInertia ? '/support' : undefined"
                    :to="!isInertia ? '/support' : undefined"
                    @click="closeUserMenu"
                    class="group flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                  >
                    <svg
                      class="mr-3 h-5 w-5 text-gray-400 group-hover:text-white"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
                      />
                    </svg>
                    {{ t("header.support") }}
                  </component>                  
                  <div class="border-t border-gray-700 my-1"></div>
                  <button
                    @click="handleLogout"
                    class="group flex w-full items-center px-4 py-2 text-sm text-red-400 hover:bg-red-900/20 hover:text-red-300 transition-colors"
                  >
                    <svg
                      class="mr-3 h-5 w-5 text-red-500/70 group-hover:text-red-400"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
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
      :class="{
        'translate-x-full': !showMobileMenu,
        'translate-x-0': showMobileMenu,
      }"
    >
      <div class="flex-1">
        <!-- Header -->
        <div
          class="flex items-center justify-between p-4 border-b border-gray-800"
        >
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

        <!-- Navigation Menu -->
        <nav class="p-4 space-y-2">
          <component
            :is="isInertia ? Link : RouterLink"
            :href="isInertia ? '/dashboard' : undefined"
            :to="!isInertia ? '/dashboard' : undefined"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors"
            :class="
              isActive('/dashboard', 'home')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
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
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
              />
            </svg>
            {{ t("header.dashboard") }}
          </component>
          <component
            :is="isInertia ? Link : RouterLink"
            :href="isInertia ? '/stores' : undefined"
            :to="!isInertia ? '/stores' : undefined"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors"
            :class="
              isActive('/stores')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
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
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
              />
            </svg>
            {{ t("header.stores") }}
          </component>
          <component
            :is="isInertia ? Link : RouterLink"
            :href="isInertia ? '/support' : undefined"
            :to="!isInertia ? '/support' : undefined"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors relative"
            :class="
              isActive('/support/wallet-connections', 'support-wallet-connections')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
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
                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
            {{ t("header.support") }}
          </component>
          <component
            v-if="
              authStore.user?.role === 'support' ||
              authStore.user?.role === 'admin'
            "
            :is="isInertia ? Link : RouterLink"
            :href="isInertia ? '/admin' : undefined"
            :to="!isInertia ? '/admin' : undefined"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors relative"
            :class="
              isActive('/admin', 'support-wallet-connections')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
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
                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
            Support
            <span
              v-if="supportCount > 0"
              class="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[20px]"
            >
              {{ supportCount > 99 ? "99+" : supportCount }}
            </span>
          </component>
          <component
            v-if="authStore.user?.role === 'admin'"
            :is="isInertia ? Link : RouterLink"
            :href="isInertia ? '/admin' : undefined"
            :to="!isInertia ? '/admin' : undefined"
            @click="closeMobileMenu"
            class="flex items-center px-4 py-3 rounded-xl text-base font-medium transition-colors"
            :class="
              isActive('/admin', 'admin-users')
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            "
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
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
            {{ t("header.admin") }}
          </component>
        </nav>
      </div>

      <!-- User Menu at bottom -->
      <div class="border-t border-gray-800 p-4 bg-gray-900/50">
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
        <component
          :is="isInertia ? Link : RouterLink"
          :href="isInertia ? '/account' : undefined"
          :to="!isInertia ? '/account' : undefined"
          @click="closeMobileMenu"
          class="flex items-center px-4 py-3 rounded-xl text-base font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
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
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
            />
          </svg>
          {{ t("header.profile_settings") }}
        </component>
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
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, inject } from "vue";
import { useRouter, useRoute, RouterLink } from "vue-router";
import { Link, router as inertiaRouter, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import LanguageSwitcher from "../LanguageSwitcher.vue";
import api from "../../services/api";
import { getEcho } from "../../echo";

const { t } = useI18n();
const isInertia = inject<boolean>("inertia", false);
const vueRouter = !isInertia ? useRouter() : null;
const route = !isInertia ? useRoute() : null;
const page = isInertia ? usePage() : null;

const authStore = useAuthStore();
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const supportCount = ref(0);
const supportToastMessage = ref("");
const supportToastUrl = ref("/support/wallet-connections");
const supportToastVisible = ref(false);
let supportCountInterval: ReturnType<typeof setInterval> | null = null;
let supportToastTimeout: ReturnType<typeof setTimeout> | null = null;
let echoUnsub: (() => void) | null = null;

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
    if (isInertia) inertiaRouter.visit("/login");
    else vueRouter!.push({ name: "login" });
  } catch (error) {
    console.error("Logout error:", error);
    if (isInertia) inertiaRouter.visit("/login");
    else vueRouter!.push({ name: "login" });
  }
};

function isActive(path: string, routeName?: string): boolean {
  if (isInertia && page) return page.url === path || page.url.startsWith(path + "/");
  if (route) return routeName ? route.name === routeName : route.path.startsWith(path);
  return false;
}

const loadSupportCount = async () => {
  if (authStore.user?.role !== "support" && authStore.user?.role !== "admin") {
    return;
  }

  try {
    const response = await api.get("/support/count");
    supportCount.value = response.data.data?.total || 0;
  } catch (error) {
    console.error("Failed to load support count:", error);
    supportCount.value = 0;
  }
};

function showSupportToast(message: string, url = "/support/wallet-connections") {
  if (supportToastTimeout) clearTimeout(supportToastTimeout);
  supportToastMessage.value = message;
  supportToastUrl.value = url;
  supportToastVisible.value = true;
  loadSupportCount();
  supportToastTimeout = setTimeout(() => {
    supportToastVisible.value = false;
    supportToastTimeout = null;
  }, 8000);
}

function dismissSupportToast() {
  if (supportToastTimeout) clearTimeout(supportToastTimeout);
  supportToastVisible.value = false;
  supportToastTimeout = null;
}

// Watch for route changes to refresh count and close mobile menu
if (vueRouter) {
  vueRouter.afterEach(() => {
    closeMobileMenu();
    if (authStore.user?.role === "support" || authStore.user?.role === "admin") {
      setTimeout(() => loadSupportCount(), 1000);
    }
  });
} else if (isInertia) {
  inertiaRouter.on("navigate", () => {
    closeMobileMenu();
    if (authStore.user?.role === "support" || authStore.user?.role === "admin") {
      setTimeout(() => loadSupportCount(), 1000);
    }
  });
}

onMounted(() => {
  // Load support count if user has support/admin role
  if (authStore.user?.role === "support" || authStore.user?.role === "admin") {
    loadSupportCount();
    supportCountInterval = setInterval(loadSupportCount, 30000);

    // Subscribe to instant push notifications (Reverb): new/updated wallet connection needs support
    const echo = getEcho();
    if (echo) {
      const channel = echo.private("support.wallet-connections");
      channel.listen(".wallet-connection.needs-support", (payload: { message?: string; store_name?: string; url?: string }) => {
        const msg = payload.message ?? (payload.store_name ? `New wallet connection: ${payload.store_name}` : "New wallet connection needs support");
        const url = payload.url ?? "/support/wallet-connections";
        showSupportToast(msg, url);
      });
      echoUnsub = () => {
        echo.leave("support.wallet-connections");
      };
    }
  }
});

onUnmounted(() => {
  if (supportCountInterval) clearInterval(supportCountInterval);
  if (echoUnsub) echoUnsub();
});

// Click outside directive
const vClickOutside = {
  mounted(el: HTMLElement, binding: any) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target as Node))) {
        binding.value();
      }
    };
    document.addEventListener("click", el.clickOutsideEvent);
  },
  unmounted(el: HTMLElement) {
    if (el.clickOutsideEvent) {
      document.removeEventListener("click", el.clickOutsideEvent);
    }
  },
};
</script>

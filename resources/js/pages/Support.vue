<template>
  <div class="min-h-screen bg-white">
    <PublicHeader />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ t('support.title') }}</h1>
        <p class="text-xl text-gray-600">{{ t('support.subtitle') }}</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Support Card -->
        <div class="bg-white border-2 border-gray-200 rounded-lg p-6">
          <div
            class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4"
          >
            <svg
              class="w-6 h-6 text-indigo-600"
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
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">
            {{ t('support.technical_support') }}
          </h3>
          <p class="text-gray-600 mb-4">
            {{ t('support.technical_support_description') }}
          </p>
          <a
            href="mailto:support@satflux.io.org"
            class="text-indigo-600 hover:text-indigo-700 font-medium"
          >
            support@satflux.io.org
          </a>
        </div>

        <!-- Pricing Card -->
        <div class="bg-white border-2 border-gray-200 rounded-lg p-6">
          <div
            class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4"
          >
            <svg
              class="w-6 h-6 text-indigo-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">
            {{ t('support.pricing_subscriptions') }}
          </h3>
          <p class="text-gray-600 mb-4">
            {{ t('support.pricing_description') }}
          </p>
          <router-link
            to="/#pricing"
            class="text-indigo-600 hover:text-indigo-700 font-medium"
          >
            {{ t('support.view_pricing') }}
          </router-link>
        </div>
      </div>

      <div class="bg-gray-50 rounded-lg p-8 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">
          {{ t('support.are_you_logged_in') }}
        </h2>
        <p class="text-gray-600 mb-6">
          {{ t('support.login_description') }}
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <router-link
            to="/login"
            class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
          >
            {{ t('auth.sign_in') }}
          </router-link>
          <router-link
            to="/register"
            class="inline-flex items-center justify-center px-6 py-3 border-2 border-indigo-600 text-base font-medium rounded-md text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
          >
            {{ t('support.create_account') }}
          </router-link>
        </div>
      </div>
    </div>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import PublicHeader from "../components/layout/PublicHeader.vue";
import AppFooter from "../components/layout/AppFooter.vue";

const { t } = useI18n();
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Public Header -->
    <PublicHeader />

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-indigo-600 to-purple-700 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
          <h1 class="text-4xl md:text-5xl font-bold mb-6">
            BTCPay Server Control Panel
          </h1>
          <p class="text-xl md:text-2xl mb-8 text-indigo-100 max-w-3xl mx-auto">
            Manage your BTCPay Server stores easily and efficiently.
            Multi-tenant solution for all your Bitcoin businesses.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center" v-if="!authStore.isAuthenticated">
            <router-link
              to="/register"
              class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white transition-colors"
            >
              Get Started Free
            </router-link>
            <router-link
              to="/login"
              class="inline-flex items-center justify-center px-8 py-3 border-2 border-white text-base font-medium rounded-md text-white hover:bg-white hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white transition-colors"
            >
              Sign In
            </router-link>
          </div>
          <div class="flex items-center justify-center gap-4" v-else>
            <router-link
              to="/dashboard"
              class="inline-flex items-center justify-center px-6 py-3 border-2 border-white text-base font-medium rounded-md text-white hover:bg-white hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white transition-colors"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Dashboard
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            Everything You Need
          </h2>
          <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Complete control over your BTCPay Server stores in one place
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Feature 1 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Multi-Store Management</h3>
            <p class="text-gray-600">
              Create and manage unlimited stores. Clear dashboard for all your businesses.
            </p>
          </div>

          <!-- Feature 2 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Point of Sale Apps</h3>
            <p class="text-gray-600">
              Create and configure PoS applications with products, categories, and custom design.
            </p>
          </div>

          <!-- Feature 3 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Lightning Fast</h3>
            <p class="text-gray-600">
              Lightning Addresses, instant payments, and everything you need for modern Bitcoin business.
            </p>
          </div>

          <!-- Feature 4 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Secure & Private</h3>
            <p class="text-gray-600">
              Your BTCPay API key stays with you. No central storage of sensitive data.
            </p>
          </div>

          <!-- Feature 5 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Invoice Management</h3>
            <p class="text-gray-600">
              Clear invoice list, filtering, CSV export, and complete payment transaction management.
            </p>
          </div>

          <!-- Feature 6 -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mb-4">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">API Integration</h3>
            <p class="text-gray-600">
              API keys for e-shop integrations. Simple connection of your web stores.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            Simple and Transparent Pricing
          </h2>
          <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Start free and upgrade when you need more features
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <!-- Free Plan -->
          <div class="bg-white border-2 border-gray-200 rounded-lg p-8 relative">
            <div class="text-center">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
              <div class="mb-6">
                <span class="text-4xl font-bold text-gray-900">0</span>
                <span class="text-gray-600"> sats/month</span>
              </div>
              <ul class="text-left space-y-3 mb-8">
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">1 Store</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Basic features</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Point of Sale apps</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Invoice management</span>
                </li>
              </ul>
              <router-link
                v-if="!authStore.isAuthenticated"
                to="/register"
                class="block w-full text-center px-6 py-3 border-2 border-indigo-600 text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                Get Started Free
              </router-link>
              <router-link
                v-else
                to="/stores"
                class="block w-full text-center px-6 py-3 border-2 border-indigo-600 text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                View My Stores
              </router-link>
            </div>
          </div>

          <!-- Pro Plan -->
          <div class="bg-gradient-to-br from-indigo-600 to-purple-700 text-white rounded-lg p-8 relative transform scale-105 shadow-xl">
            <div class="absolute top-0 right-0 bg-yellow-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-bl-lg rounded-tr-lg">
              RECOMMENDED
            </div>
            <div class="text-center">
              <h3 class="text-2xl font-bold mb-2">Pro</h3>
              <div class="mb-6">
                <span class="text-4xl font-bold">50,000</span>
                <span class="text-indigo-200"> sats/month</span>
              </div>
              <ul class="text-left space-y-3 mb-8">
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-300 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Unlimited stores</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-300 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>All Free features</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-300 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Priority support</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-300 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>CSV exports</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-300 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>API integrations</span>
                </li>
              </ul>
              <a
                v-if="authStore.isAuthenticated"
                href="#"
                @click.prevent="handleUpgrade('pro')"
                class="block w-full text-center px-6 py-3 bg-white text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                Upgrade
              </a>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-3 bg-white text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                Get Started
              </router-link>
            </div>
          </div>

          <!-- Enterprise Plan -->
          <div class="bg-white border-2 border-gray-200 rounded-lg p-8 relative">
            <div class="text-center">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">Enterprise</h3>
              <div class="mb-6">
                <span class="text-4xl font-bold text-gray-900">200,000</span>
                <span class="text-gray-600"> sats/month</span>
              </div>
              <ul class="text-left space-y-3 mb-8">
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Everything from Pro</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Dedicated support</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">Custom integrations</span>
                </li>
                <li class="flex items-start">
                  <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-gray-700">SLA guarantees</span>
                </li>
              </ul>
              <a
                v-if="authStore.isAuthenticated"
                href="#"
                @click.prevent="handleUpgrade('enterprise')"
                class="block w-full text-center px-6 py-3 border-2 border-indigo-600 text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                Contact Us
              </a>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-3 border-2 border-indigo-600 text-indigo-600 font-medium rounded-md hover:bg-indigo-50 transition-colors"
              >
                Get Started
              </router-link>
            </div>
          </div>
        </div>

        <div class="text-center mt-12 text-gray-600">
          <p class="text-lg">
            All plans are paid with Bitcoin via BTCPay Server. Automatic monthly subscription.
          </p>
          <p class="text-sm mt-2">
            For more information contact <router-link to="/support" class="text-indigo-600 hover:text-indigo-700">support</router-link>
          </p>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { onMounted } from 'vue';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';

const router = useRouter();
const authStore = useAuthStore();

// Ensure user is fetched on mount to show correct buttons
onMounted(async () => {
  if (!authStore.user) {
    try {
      await authStore.fetchUser();
    } catch (error) {
      // User not authenticated, which is fine
    }
  }
});

function handleUpgrade(plan: string) {
  // TODO: Redirect to subscription page with plan parameter
  // This will integrate with BTCPay Server subscription
  router.push({ name: 'account', query: { upgrade: plan } });
}

// Smooth scroll for anchor links
onMounted(() => {
  // Handle initial hash if present
  if (window.location.hash) {
    setTimeout(() => {
      const target = document.querySelector(window.location.hash);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }, 100);
  }

  // Handle anchor link clicks
  const anchors = document.querySelectorAll('a[href^="#"]');
  anchors.forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const href = this.getAttribute('href');
      if (href && href !== '#') {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  });
});
</script>

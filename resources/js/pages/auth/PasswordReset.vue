<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gray-900">
      <div class="absolute top-0 -left-6 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"></div>
      <div class="absolute bottom-0 -right-6 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000"></div>
    </div>

    <div class="max-w-md w-full space-y-8 relative z-10">
      <div class="text-center">
        <router-link to="/" class="inline-block">
          <span class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-500 tracking-tight">UZOL21</span>
        </router-link>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
          Reset password
        </h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          Enter your email to receive a reset link
        </p>
      </div>

      <div class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl">
        <form class="space-y-6" @submit.prevent="handleReset">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
            <div class="mt-1">
              <input
                id="email"
                v-model="form.email"
                name="email"
                type="email"
                autocomplete="email"
                required
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                placeholder="you@example.com"
              />
            </div>
          </div>

          <div v-if="error" class="rounded-md bg-red-900/30 border border-red-500/30 p-4">
            <div class="text-sm text-red-200">{{ error }}</div>
          </div>

          <div v-if="success" class="rounded-md bg-green-900/30 border border-green-500/30 p-4">
            <div class="text-sm text-green-200">{{ success }}</div>
          </div>

          <div>
            <button
              type="submit"
              :disabled="loading"
              class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
            >
              {{ loading ? 'Sending...' : 'Send reset link' }}
            </button>
          </div>

          <div class="text-center text-sm">
            <router-link to="/login" class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors">
              <span aria-hidden="true"> &larr;</span> Back to login
            </router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import api from '../../services/api';

const form = ref({
  email: '',
});

const error = ref('');
const success = ref('');
const loading = ref(false);

async function handleReset() {
  error.value = '';
  success.value = '';
  loading.value = true;

  try {
    await api.post('/auth/password/reset-link', {
      email: form.value.email,
    });
    success.value = 'Password reset link sent to your email.';
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to send reset link. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

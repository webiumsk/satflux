<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">Dashboard</h1>
      <p class="text-gray-400 mt-1">Welcome back, {{ userName }}</p>
    </div>
    
    <!-- Dashboard Stats / Placeholder -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Card 1 -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
           <svg class="w-24 h-24 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">Active Stores</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : storeCount }}</h3>
          <div class="mt-4">
             <router-link v-if="storeCount === 0" to="/stores/create" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 flex items-center">
               Create Store <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
             </router-link>
             <router-link v-else to="/stores" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 flex items-center">
               View Stores <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
             </router-link>
          </div>
        </div>
      </div>

       <!-- Card 2 -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
           <svg class="w-24 h-24 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">Total Revenue</p>
          <h3 class="text-3xl font-bold text-white">{{ loading ? '...' : formatSats(totalRevenue) }} <span class="text-base font-normal text-gray-500">sats</span></h3>
          <div class="mt-4">
             <span v-if="totalRevenue === 0" class="text-sm font-medium text-gray-500 flex items-center">
               No transactions yet
             </span>
             <span v-else class="text-sm font-medium text-gray-400 flex items-center">
               All-time total
             </span>
          </div>
        </div>
      </div>

       <!-- Card 3 -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
           <svg class="w-24 h-24 text-purple-500" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
        </div>
        <div class="relative z-10">
          <p class="text-gray-400 text-sm font-medium mb-1">System Status</p>
          <h3 class="text-3xl font-bold text-white flex items-center">
             <span class="flex h-3 w-3 rounded-full bg-green-500 mr-3"></span>
             Online
          </h3>
          <div class="mt-4">
             <router-link to="/support" class="text-sm font-medium text-purple-400 hover:text-purple-300 flex items-center">
               Help Center <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
             </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions / Placeholder -->
    <div v-if="storeCount === 0" class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg p-8 text-center">
      <div class="max-w-md mx-auto">
        <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
           <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">Get Started</h3>
        <p class="text-gray-400 mb-6">Create a store to start accepting Bitcoin payments.</p>
        <router-link to="/stores/create" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-lg shadow-indigo-600/20">
           Create New Store
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../store/auth';
import api from '../services/api';

const authStore = useAuthStore();

const userName = computed(() => {
  if (authStore.user?.email) {
    return authStore.user.email.split('@')[0];
  }
  return 'User';
});

const loading = ref(true);
const storeCount = ref(0);
const totalRevenue = ref(0);

async function loadDashboardData() {
  loading.value = true;
  try {
    const response = await api.get('/dashboard');
    storeCount.value = response.data.store_count || 0;
    totalRevenue.value = response.data.total_revenue || 0;
  } catch (error: any) {
    console.error('Failed to load dashboard data:', error);
    // Keep defaults (0) on error
  } finally {
    loading.value = false;
  }
}

function formatSats(sats: number): string {
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
  }).format(sats);
}

onMounted(() => {
  loadDashboardData();
});
</script>

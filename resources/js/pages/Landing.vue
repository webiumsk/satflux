<template>
  <div class="min-h-screen bg-gray-900 text-white font-sans selection:bg-indigo-500 selection:text-white">
    <!-- Public Header -->
    <PublicHeader />

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
      <!-- Background Effects -->
      <div class="absolute inset-0 bg-gray-900">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32">
        <div class="text-center">
          <div class="inline-flex items-center px-4 py-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 text-indigo-300 text-sm font-medium mb-8 backdrop-blur-sm">
            <span class="flex h-2 w-2 rounded-full bg-indigo-400 mr-2"></span>
            {{ t('landing.payments_for_future') }}
          </div>
          <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-8 bg-clip-text text-transparent bg-gradient-to-r from-white via-indigo-200 to-indigo-400">
            {{ t('landing.accept_bitcoin') }} <br class="hidden md:block" />
            <span class="text-white">{{ t('landing.without_limits') }}</span>
          </h1>
          <p class="text-xl md:text-2xl mb-10 text-gray-400 max-w-3xl mx-auto leading-relaxed">
            {{ t('landing.hero_description') }}
          </p>
          
          <div class="flex flex-col sm:flex-row gap-5 justify-center" v-if="!authStore.isAuthenticated">
            <router-link
              to="/register"
              class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-600/20 hover:scale-105"
            >
              {{ t('landing.start_for_free') }}
              <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
            </router-link>
            <router-link
              to="/login"
              class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-xl text-white border border-gray-700 hover:bg-gray-800 transition-all hover:border-gray-600"
            >
              {{ t('landing.sign_in') }}
            </router-link>
          </div>
          <div class="flex items-center justify-center gap-4" v-else>
            <router-link
              to="/dashboard"
              class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-600/20 hover:scale-105"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {{ t('landing.go_to_dashboard') }}
            </router-link>
          </div>

          <!-- Hero Image/Dashboard Preview -->
          <div class="mt-16 relative mx-auto max-w-5xl">
            <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl blur opacity-20"></div>
            <div class="relative bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl overflow-hidden">
               <!-- Abstract Dashboard UI Mockup -->
               <div class="flex items-center px-4 py-2 border-b border-gray-700 bg-gray-900/50">
                 <div class="flex space-x-2">
                   <div class="w-3 h-3 rounded-full bg-red-500"></div>
                   <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                   <div class="w-3 h-3 rounded-full bg-green-500"></div>
                 </div>
               </div>
               <div class="p-4 md:p-8 bg-gray-900/80 backdrop-blur min-h-[300px] flex items-center justify-center">
                 <div class="text-center">
                   <p class="text-gray-500 mb-2 text-sm uppercase tracking-wider">{{ t('landing.total_revenue') }}</p>
                   <p class="text-4xl md:text-6xl font-bold text-white mb-4">2.14502100 <span class="text-orange-500">BTC</span></p>
                   <div class="h-16 w-full max-w-md mx-auto flex items-end gap-1">
                     <div class="w-1/12 bg-indigo-500/20 h-1/3 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/40 h-1/2 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/30 h-1/4 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/60 h-2/3 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/40 h-1/2 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/80 h-3/4 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500 h-full rounded-t shadow-[0_0_15px_rgba(99,102,241,0.5)]"></div>
                     <div class="w-1/12 bg-indigo-500/70 h-5/6 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/50 h-2/3 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/30 h-1/3 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/20 h-1/4 rounded-t"></div>
                     <div class="w-1/12 bg-indigo-500/10 h-1/6 rounded-t"></div>
                   </div>
                 </div>
               </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Key Points Section -->
    <section class="py-24 bg-gray-900 border-t border-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
           <!-- Point 1 -->
           <div class="p-6 rounded-2xl bg-gray-800/50 border border-gray-700 hover:border-indigo-500/50 transition-colors">
             <div class="w-12 h-12 bg-green-500/20 text-green-400 rounded-lg flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.non_custodial') }}</h3>
             <p class="text-gray-400 text-sm">{{ t('landing.non_custodial_description') }}</p>
           </div>

           <!-- Point 2 -->
           <div class="p-6 rounded-2xl bg-gray-800/50 border border-gray-700 hover:border-indigo-500/50 transition-colors">
             <div class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-lg flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.lightning_ready') }}</h3>
             <p class="text-gray-400 text-sm">{{ t('landing.lightning_ready_description') }}</p>
           </div>

           <!-- Point 3 -->
           <div class="p-6 rounded-2xl bg-gray-800/50 border border-gray-700 hover:border-indigo-500/50 transition-colors">
             <div class="w-12 h-12 bg-purple-500/20 text-purple-400 rounded-lg flex items-center justify-center mb-4">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.smart_exports') }}</h3>
             <p class="text-gray-400 text-sm">{{ t('landing.smart_exports_description') }}</p>
           </div>

           <!-- Point 4 -->
           <div class="p-6 rounded-2xl bg-gray-800/50 border border-gray-700 hover:border-indigo-500/50 transition-colors">
             <div class="w-12 h-12 bg-orange-500/20 text-orange-400 rounded-lg flex items-center justify-center mb-4">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.monthly_recap') }}</h3>
             <p class="text-gray-400 text-sm">{{ t('landing.monthly_recap_description') }}</p>
           </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-gray-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
            {{ t('landing.everything_you_need') }}
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto">
            {{ t('landing.everything_you_need_description') }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Multi-Store (Large Card) -->
          <div class="md:col-span-2 bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-8 border border-gray-700 shadow-xl overflow-hidden relative group">
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
               <svg class="w-48 h-48 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
            <div class="relative z-10">
              <h3 class="text-2xl font-bold text-white mb-4">{{ t('landing.multi_store_management') }}</h3>
              <p class="text-gray-400 text-lg mb-6 max-w-md">{{ t('landing.multi_store_description') }}</p>
              <div class="inline-flex items-center text-indigo-400 font-semibold group-hover:translate-x-1 transition-transform cursor-pointer">
                {{ t('landing.learn_more') }} <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </div>
            </div>
          </div>

          <!-- POS Apps -->
          <div class="bg-gray-800 rounded-3xl p-8 border border-gray-700 shadow-xl relative group hover:bg-gray-750 transition-colors">
             <div class="absolute inset-0 bg-indigo-600/5 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
             <div class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center mb-6 text-indigo-400">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.point_of_sale') }}</h3>
             <p class="text-gray-400">{{ t('landing.point_of_sale_description') }}</p>
          </div>

          <!-- Invoice Management -->
          <div class="bg-gray-800 rounded-3xl p-8 border border-gray-700 shadow-xl relative group hover:bg-gray-750 transition-colors">
            <div class="absolute inset-0 bg-indigo-600/5 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
             <div class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center mb-6 text-indigo-400">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
             </div>
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.invoicing') }}</h3>
             <p class="text-gray-400">{{ t('landing.invoicing_description') }}</p>
          </div>

          <!-- API & Secure (Large Card) -->
          <div class="md:col-span-2 bg-gradient-to-br from-indigo-900/50 to-gray-900 rounded-3xl p-8 border border-gray-700 shadow-xl relative overflow-hidden group">
            <div class="absolute z-0 right-0 bottom-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
             <div class="relative z-10 flex flex-col md:flex-row items-center gap-8">
               <div class="flex-1">
                 <h3 class="text-2xl font-bold text-white mb-4">{{ t('landing.developer_friendly_api') }}</h3>
                 <p class="text-gray-400 text-lg mb-6">{{ t('landing.api_description') }}</p>
                 <div class="flex gap-2">
                   <span class="px-3 py-1 rounded-md bg-gray-800 border border-gray-600 text-xs font-mono text-gray-300">REST API</span>
                   <span class="px-3 py-1 rounded-md bg-gray-800 border border-gray-600 text-xs font-mono text-gray-300">Webhooks</span>
                 </div>
               </div>
               <div class="w-16 h-16 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 shrink-0 animation-pulse">
                 <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
               </div>
             </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 bg-gray-900 relative">
      <div class="absolute inset-0 bg-indigo-900/10 skew-y-3 transform origin-bottom-right"></div>
      <div class=" max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
            {{ t('landing.transparent_pricing') }}
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto">
            {{ t('landing.pricing_description') }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto items-center">
          <!-- Free Plan -->
          <div class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors">
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.free') }}</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-4xl font-extrabold text-white">{{ t('landing.free_price') }}</span>
                <span class="text-gray-400 ml-2">{{ t('landing.free_price_unit') }}</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">{{ t('landing.free_description') }}</p>
             <ul class="space-y-4 mb-8">
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.free_store') }}
               </li>
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.basic_features') }}
               </li>
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.community_support') }}
               </li>
             </ul>
             <router-link
               v-if="!authStore.isAuthenticated"
               to="/register"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               {{ t('landing.start_free') }}
             </router-link>
             <router-link
               v-else
               to="/stores"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               {{ t('landing.view_stores') }}
             </router-link>
          </div>

          <!-- Pro Plan (Highlighted) -->
          <div class="bg-gray-800 rounded-2xl p-8 border-2 border-indigo-500 shadow-2xl relative transform md:scale-105 z-10">
             <div class="absolute top-0 right-0 -mt-4 mr-4 bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
               {{ t('landing.popular') }}
             </div>
             <h3 class="text-2xl font-bold text-white mb-2">{{ t('landing.pro') }}</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-5xl font-extrabold text-white">{{ t('landing.pro_price') }}</span>
                <span class="text-indigo-300 ml-2">{{ t('landing.pro_price_unit') }}</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">{{ t('landing.pro_description') }}</p>
             <ul class="space-y-4 mb-8">
               <li class="flex items-center text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.unlimited_stores') }}
               </li>
               <li class="flex items-center text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.priority_support') }}
               </li>
               <li class="flex items-center text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.csv_exports') }}
               </li>
               <li class="flex items-center text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.api_integrations') }}
               </li>
               <li class="flex items-center text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.monthly_reports') }}
               </li>
             </ul>
             <button
                v-if="authStore.isAuthenticated"
                @click="handleUpgrade('pro')"
                :disabled="subscribing"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ subscribing ? t('landing.processing') : t('landing.upgrade_now') }}
              </button>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
              >
                {{ t('landing.get_started') }}
              </router-link>
          </div>

          <!-- Enterprise Plan -->
          <div class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors">
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.enterprise') }}</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-4xl font-extrabold text-white">{{ t('landing.enterprise_price') }}</span>
                <span class="text-gray-400 ml-2">{{ t('landing.enterprise_price_unit') }}</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">{{ t('landing.enterprise_description') }}</p>
             <ul class="space-y-4 mb-8">
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.everything_in_pro') }}
               </li>
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.dedicated_agent') }}
               </li>
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.custom_solutions') }}
               </li>
               <li class="flex items-center text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 {{ t('landing.sla_guarantees') }}
               </li>
             </ul>
             <button
                v-if="authStore.isAuthenticated"
                @click="handleUpgrade('enterprise')"
                :disabled="subscribing"
                class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ subscribing ? t('landing.processing') : t('landing.upgrade') }}
              </button>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
              >
                {{ t('landing.contact_sales') }}
              </router-link>
          </div>
        </div>
        
        <!-- Error message -->
        <div v-if="subscribeError" class="mt-8 max-w-md mx-auto">
          <div class="bg-red-900/50 border border-red-500/50 rounded-lg p-4 text-center">
            <p class="text-sm text-red-200">{{ subscribeError }}</p>
          </div>
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
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';
import api from '../services/api';

const { t } = useI18n();

const router = useRouter();
const authStore = useAuthStore();
const subscribing = ref(false);
const subscribeError = ref('');

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

async function handleUpgrade(plan: string) {
  // Both Pro and Enterprise can use the same checkout flow now
  subscribing.value = true;
  subscribeError.value = '';

  try {
    // Call checkout endpoint with plan name
    // Backend will look up storeUuid, offeringId, and planId from config
    const response = await api.post('/subscriptions/checkout', {
      plan: plan,
    });

    if (response.data.checkoutUrl) {
      // Redirect to BTCPay checkout
      window.location.href = response.data.checkoutUrl;
    } else {
      subscribeError.value = t('landing.failed_to_create_checkout');
      subscribing.value = false;
    }
  } catch (error: any) {
    console.error('Failed to create checkout:', error);
    subscribeError.value = error.response?.data?.message || t('landing.failed_to_create_checkout');
    subscribing.value = false;
  }
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

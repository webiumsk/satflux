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
            {{ t('landing.hero_subheadline') }}
          </div>
          <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-8 bg-clip-text text-transparent bg-gradient-to-r from-white via-indigo-200 to-indigo-400">
            {{ t('landing.hero_headline') }}
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
      <!-- Thin gradient line at bottom of hero -->
      <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent" aria-hidden="true"></div>
    </section>

    <!-- Features Section -->
    <section id="features" class="relative py-24 bg-gray-900">
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent" aria-hidden="true"></div>
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
              <svg
                class="w-48 h-48 text-indigo-500"
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

    <!-- How it works Section (between Features and Pricing) -->
    <!-- How it works Section (Zig-Zag) -->
    <section id="how-it-works" class="relative py-24 bg-gray-800/40">
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent" aria-hidden="true"></div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-24">
          <h2 class="text-4xl md:text-6xl font-bold text-white mb-6 tracking-tight">
            {{ t('landing.how_it_works') }}
          </h2>
          <p class="text-xl text-indigo-300 max-w-2xl mx-auto">
            {{ t('landing.how_it_works_intro') }}
          </p>
        </div>

        <!-- How It Works Timeline -->
        <div class="relative">
           <!-- Vertical Connector Line -->
           <div class="absolute left-6 md:left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-transparent via-indigo-500/30 to-transparent transform -translate-x-1/2"></div>
           
           <div class="space-y-12 md:space-y-0">
            <!-- Step 1: Registration -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
              <!-- Dot on timeline (Desktop) -->
              <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
              <!-- Number on timeline (Mobile) -->
              <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">1</div>
              
              <div class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step1_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step1_text') }}
                 </p>
              </div>
              
              <div class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2">
                <router-link
                  to="/register"
                  class="block w-full max-w-md rounded-2xl border border-gray-700 shadow-2xl overflow-hidden group hover:border-indigo-500/50 transition-colors duration-500 relative"
                >
                  <img
                    src="/img/satflux-register.webp"
                    :alt="t('landing.alt_register')"
                    class="w-full h-auto block"
                  />
                  <img
                    src="/img/satflux-register-hover.webp"
                    alt=""
                    aria-hidden="true"
                    class="absolute inset-0 w-full h-full object-cover object-top opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"
                  />
                </router-link>
              </div>
            </div>

            <!-- Step 2: Wallet Connection -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">2</div>

               <div class="md:w-5/12 flex justify-center md:justify-end pl-16 md:pl-0 pr-0 md:pr-16 order-2 md:order-1">
                  <div class="flex flex-col gap-10 w-full max-w-sm mt-12 md:mt-0">
                      <!-- Blink Card (Shifted left) -->
                      <div class="relative group/card cursor-pointer transform md:-translate-x-12 transition-transform duration-500 hover:scale-105">
                          <!-- Card Content -->
                          <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex items-center gap-6 transition-all duration-300 group-hover/card:border-orange-500/50 group-hover/card:bg-gray-800/80">
                              <img src="/img/wallets/blink-64.webp" :alt="t('landing.blink_wallet_title')" class="w-20 h-20 rounded-2xl object-contain p-2 bg-white/5 border border-white/10 shadow-inner">
                              <div>
                                  <h4 class="text-2xl font-bold text-white mb-1">{{ t('landing.blink_wallet_title') }}</h4>
                                  <p class="text-sm text-orange-400 font-medium">{{ t('landing.blink_wallet_tagline') }}</p>
                              </div>
                          </div>

                          <!-- Hover Details (Tooltip/Overlay) -->
                          <div class="absolute inset-0 bg-gray-900/95 backdrop-blur-xl rounded-2xl p-6 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300 flex flex-col justify-center border border-orange-500/50 z-20 pointer-events-none group-hover/card:pointer-events-auto shadow-[0_0_30px_rgba(249,115,22,0.3)]">
                             <div class="space-y-4">
                                 <div>
                                     <span class="text-green-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1">
                                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                       {{ t('landing.wallet_pros') }}
                                     </span>
                                     <p class="text-gray-300 text-sm leading-relaxed">{{ t('landing.blink_pros') }}</p>
                                 </div>
                                 <div>
                                     <span class="text-red-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1">
                                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                       {{ t('landing.wallet_cons') }}
                                     </span>
                                     <p class="text-gray-300 text-sm leading-relaxed">{{ t('landing.blink_cons') }}</p>
                                 </div>
                             </div>
                          </div>
                      </div>

                      <!-- Aqua Card (Shifted right/default) -->
                      <div class="relative group/card cursor-pointer transition-transform duration-500 hover:scale-105">
                          <!-- Card Content -->
                          <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex items-center gap-6 transition-all duration-300 group-hover/card:border-blue-500/50 group-hover/card:bg-gray-800/80">
                              <div class="w-20 h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center p-2 shadow-inner">
                                <img src="/img/wallets/aqua-64.webp" :alt="t('landing.aqua_wallet_title')" class="w-full h-full object-contain">
                              </div>
                              <div>
                                  <h4 class="text-2xl font-bold text-white mb-1">{{ t('landing.aqua_wallet_title') }}</h4>
                                  <p class="text-sm text-blue-400 font-medium">{{ t('landing.aqua_wallet_tagline') }}</p>
                              </div>
                          </div>

                           <!-- Hover Details -->
                          <div class="absolute inset-0 bg-gray-900/95 backdrop-blur-xl rounded-2xl p-6 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300 flex flex-col justify-center border border-blue-500/50 z-20 pointer-events-none group-hover/card:pointer-events-auto shadow-[0_0_30px_rgba(59,130,246,0.3)]">
                             <div class="space-y-4">
                                 <div>
                                     <span class="text-green-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1">
                                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                       {{ t('landing.wallet_pros') }}
                                     </span>
                                     <p class="text-gray-300 text-sm leading-relaxed">{{ t('landing.aqua_pros') }}</p>
                                 </div>
                                 <div>
                                     <span class="text-red-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1">
                                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                       {{ t('landing.wallet_cons') }}
                                     </span>
                                     <p class="text-gray-300 text-sm leading-relaxed">{{ t('landing.aqua_cons') }}</p>
                                 </div>
                             </div>
                          </div>
                      </div>
                  </div>
               </div>

               <div class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step2_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step2_text') }}
                 </p>
               </div>
            </div>

            <!-- Step 3: PoS Terminal (Interactive) -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[100vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">3</div>

               <div class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step3_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed mb-6">
                   {{ t('landing.step3_text') }}
                 </p>
                 <p class="text-sm text-green-400/80 hidden md:block">
                    {{ t('landing.how_it_works_demo_real') }}
                 </p>
                 
                 <!-- Mobile Button -->
                 <div class="md:hidden mt-4 flex justify-center">
                    <button
                      @click="showPosModal = true"
                      class="inline-flex items-center justify-center px-6 py-3 text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 transition-all shadow-lg"
                    >
                       {{ t('landing.how_it_works_open_pos_mobile') }}
                    </button>
                 </div>
               </div>

               <div class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2">
                  <div class="hidden md:block w-full max-w-[380px] bg-gray-900 rounded-[2.5rem] border-[8px] border-gray-800 shadow-2xl overflow-hidden h-[800px] relative">
                     <!-- Phone Notches -->
                     <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-gray-800 rounded-b-xl z-20"></div>
                     <!-- Iframe -->
                     <iframe
                        :src="posDemoUrl"
                        title="SATFLUX PoS Demo"
                        class="w-full h-full border-0 bg-gray-900"
                        allow="payment"
                        loading="lazy"
                      />
                      <!-- Overlay hint -->
                      <div class="absolute bottom-4 left-0 right-0 text-center pointer-events-none">
                         <span class="inline-block px-3 py-1 bg-black/50 text-white/50 text-xs rounded-full backdrop-blur-sm">{{ t('landing.live_demo') }}</span>
                      </div>
                  </div>
                  <!-- Mobile Visual (Placeholder card instead of full iframe) -->
                  <div class="md:hidden w-full max-w-xs bg-gray-800 rounded-2xl p-4 border border-gray-700">
                      <div class="grid grid-cols-3 gap-2">
                         <div v-for="n in 9" :key="n" class="aspect-square bg-gray-700/50 rounded flex items-center justify-center text-gray-500">{{ n }}</div>
                      </div>
                  </div>
               </div>
            </div>

            <!-- Step 4: Integrations -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">4</div>

               <div class="md:w-5/12 flex justify-start pl-16 md:justify-end pr-8 md:pr-16 order-2 md:order-1">
                  <div class="flex flex-wrap gap-4 max-w-md justify-start md:justify-end">
                     <div class="bg-gray-100 text-gray-900 px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-75">Woo</div>
                     <div class="bg-[#95BF47] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-100">Shopify</div>
                     <div class="bg-[#F46F25] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-150">Magento</div>
                     <div class="bg-[#0678BE] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-200">Drupal</div>
                     <div class="w-full text-center text-gray-400 text-xs mt-2 font-mono bg-gray-900/50 p-2 rounded border border-gray-700/50">{{ t('landing.api_key_example') }}</div>
                  </div>
               </div>

               <div class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step4_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step4_text') }}
                 </p>
               </div>
            </div>

            <!-- Step 5: LN Address -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">5</div>

               <div class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step5_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step5_text') }}
                 </p>
               </div>
               
               <div class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2">
                  <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-1 rounded-2xl w-full max-w-sm shadow-xl shadow-purple-900/20 transform group-hover:scale-105 transition-transform duration-500">
                     <div class="bg-gray-900 rounded-xl p-8 text-center relative overflow-hidden">
                        <!-- Shine effect -->
                        <div class="absolute inset-0 bg-gradient-to-tr from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                        <div class="w-16 h-16 bg-gray-800 rounded-full mb-4 flex items-center justify-center shadow-inner mx-auto">
                          <svg class="w-8 h-8 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="text-white font-mono text-lg md:text-xl">satoshi<span class="text-indigo-400">@satflux.org</span></div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Step 6: Ticket Sale -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">6</div>

               <div class="md:w-5/12 flex justify-start pl-16 md:justify-end pr-8 md:pr-16 order-2 md:order-1">
                  <div class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 flex flex-col items-center gap-4 transform group-hover:scale-105 transition-transform duration-500 relative overflow-hidden">
                     <!-- Background ticket watermark -->
                     <div class="absolute top-0 right-0 p-4 opacity-5">
                        <svg class="w-36 h-36 text-orange-400" fill="currentColor" viewBox="0 0 24 24"><path d="M15.58 16.8L12 14.5l-3.58 2.3 1.08-4.12L6.21 10l4.25-.26L12 5.8l1.54 3.94 4.25.26-3.29 2.68 1.08 4.12zM22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24z"/></svg>
                     </div>

                     <!-- Event card header -->
                     <div class="w-full bg-gradient-to-r from-orange-500/20 to-yellow-500/20 rounded-xl border border-orange-500/30 p-4 relative z-10">
                        <div class="flex items-center gap-3 mb-2">
                           <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center">
                              <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                           </div>
                           <div>
                              <div class="text-white font-bold text-sm">{{ t('landing.step6_badge_event') }}</div>
                              <div class="text-orange-300/70 text-xs">14 Mar 2026 &middot; 18:00</div>
                           </div>
                        </div>
                        <div class="flex gap-4 text-xs">
                           <div class="flex items-center gap-1.5">
                              <svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 100 4v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2a2 2 0 100-4V6z" /></svg>
                              <span class="text-gray-300">{{ t('landing.step6_badge_tickets') }}: <span class="text-green-400 font-bold">142</span></span>
                           </div>
                           <div class="flex items-center gap-1.5">
                              <svg class="w-3.5 h-3.5 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                              <span class="text-gray-300">{{ t('landing.step6_badge_checkin') }}: <span class="text-indigo-400 font-bold">89</span></span>
                           </div>
                        </div>
                     </div>

                     <!-- QR code / scan area -->
                     <div class="w-full bg-gray-900 rounded-xl border border-gray-700 p-4 flex items-center gap-4 relative z-10">
                        <div class="w-16 h-16 bg-white rounded-lg p-1.5 flex-shrink-0">
                           <!-- Stylized QR code -->
                           <svg viewBox="0 0 100 100" class="w-full h-full">
                              <rect x="0" y="0" width="100" height="100" fill="white"/>
                              <rect x="5" y="5" width="25" height="25" rx="3" fill="#1a1a2e"/>
                              <rect x="10" y="10" width="15" height="15" rx="2" fill="white"/>
                              <rect x="13" y="13" width="9" height="9" rx="1" fill="#1a1a2e"/>
                              <rect x="70" y="5" width="25" height="25" rx="3" fill="#1a1a2e"/>
                              <rect x="75" y="10" width="15" height="15" rx="2" fill="white"/>
                              <rect x="78" y="13" width="9" height="9" rx="1" fill="#1a1a2e"/>
                              <rect x="5" y="70" width="25" height="25" rx="3" fill="#1a1a2e"/>
                              <rect x="10" y="75" width="15" height="15" rx="2" fill="white"/>
                              <rect x="13" y="78" width="9" height="9" rx="1" fill="#1a1a2e"/>
                              <rect x="35" y="5" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="48" y="5" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="35" y="18" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="5" y="35" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="18" y="35" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="35" y="35" width="8" height="8" rx="1" fill="#f59e0b"/>
                              <rect x="48" y="35" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="61" y="35" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="5" y="48" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="35" y="48" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="48" y="48" width="8" height="8" rx="1" fill="#f59e0b"/>
                              <rect x="61" y="48" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="5" y="61" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="48" y="61" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="70" y="48" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="83" y="48" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="70" y="61" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="83" y="61" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="70" y="70" width="8" height="8" rx="1" fill="#1a1a2e"/>
                              <rect x="83" y="83" width="12" height="12" rx="2" fill="#f59e0b"/>
                           </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                           <div class="text-xs text-gray-400 mb-1.5">{{ t('landing.step6_scan_label') }}</div>
                           <div class="flex items-center gap-2">
                              <div class="flex-1 bg-gray-800 rounded-lg px-3 py-2 border border-gray-600 text-xs text-gray-500 font-mono truncate">TKT-2026-00482</div>
                              <div class="w-8 h-8 bg-green-500/20 border border-green-500/40 rounded-lg flex items-center justify-center flex-shrink-0">
                                 <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                              </div>
                           </div>
                        </div>
                     </div>

                     <!-- Price in sats -->
                     <div class="w-full flex items-center justify-between px-1 relative z-10">
                        <span class="text-xs text-gray-500 font-mono">21,000 sats / ticket</span>
                        <span class="text-xs font-bold text-orange-400 bg-orange-400/10 px-2.5 py-1 rounded-full border border-orange-400/20">&#9889; Lightning</span>
                     </div>
                  </div>
               </div>

               <div class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step6_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed mb-6">
                   {{ t('landing.step6_text') }}
                 </p>
                 <div class="space-y-3">
                   <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ t('landing.step6_plugins_title') }}</p>
                   <div class="flex flex-wrap gap-3">
                     <a
                       href="https://github.com/webiumsk/btcpay-greenfield-tickets/releases"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="BTCPay Satoshi Tickets for WooCommerce - GitHub Releases"
                       class="inline-flex flex-wrap items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800 border border-gray-600 hover:border-orange-500/50 hover:bg-gray-800/80 transition-all group"
                     >
                       <span class="font-semibold text-white group-hover:text-orange-400 transition-colors">{{ t('landing.step6_plugin_woocommerce') }}</span>
                       <img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT" class="h-4" />
                       <img src="https://img.shields.io/github/v/release/webiumsk/btcpay-greenfield-tickets?label=release" alt="release" class="h-4" />
                     </a>
                     <!-- Future platforms can be added here -->
                   </div>
                 </div>
               </div>
            </div>

            <!-- Step 7: Accounting Exports -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
               <div class="absolute left-6 top-12 flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 border-2 border-indigo-500 text-indigo-400 font-bold text-lg transform -translate-x-1/2 md:hidden shadow-[0_0_10px_rgba(99,102,241,0.5)] z-10">7</div>

               <div class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step7_title') }}</h3>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step7_text') }}
                 </p>
               </div>

               <div class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2">
                  <div class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 flex flex-col items-center gap-6 transform group-hover:scale-105 transition-transform duration-500 relative overflow-hidden">
                     <div class="absolute top-0 right-0 p-4 opacity-10">
                        <svg class="w-32 h-32 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                     </div>
                     <div class="grid grid-cols-2 gap-4 w-full relative z-10">
                        <div class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-green-500/10 hover:border-green-500/50 transition-colors">
                           <span class="font-bold text-white text-lg mb-1">CSV</span>
                           <span class="text-xs text-gray-400">{{ t('landing.export_csv_label') }}</span>
                        </div>
                        <div class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-red-500/10 hover:border-red-500/50 transition-colors">
                           <span class="font-bold text-white text-lg mb-1">PDF</span>
                           <span class="text-xs text-gray-400">{{ t('landing.export_pdf_label') }}</span>
                        </div>
                     </div>
                     <div class="w-full bg-gray-900 rounded-lg p-3 border border-gray-700 flex items-center justify-between text-xs text-gray-400 font-mono">
                        <span>export_2026_02.csv</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                     </div>
                  </div>
               </div>
            </div>
           </div>
        </div>


      </div>
    </section>

    <!-- PoS Demo Modal (mobile) -->
    <Teleport to="body">
      <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showPosModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pos-modal-title"
        >
          <div class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm" @click="showPosModal = false" />
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg transform rounded-2xl bg-gray-800 border border-gray-700 shadow-2xl transition-all">
              <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                <h2 id="pos-modal-title" class="text-lg font-bold text-white">{{ t('landing.how_it_works_demo_title') }}</h2>
                <button
                  type="button"
                  @click="showPosModal = false"
                  class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  :aria-label="t('landing.how_it_works_close_modal')"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="p-2 bg-gray-900 rounded-b-2xl" style="min-height: 500px;">
                <iframe
                  :src="posDemoUrl"
                  title="SATFLUX PoS Demo"
                  class="w-full h-[500px] rounded-lg border-0"
                  allow="payment"
                />
              </div>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Pricing Section -->
    <section id="pricing" class="relative py-24 bg-gray-900 overflow-hidden">
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent z-10" aria-hidden="true"></div>
      <div class="absolute inset-0 bg-indigo-900/10 skew-y-3 transform origin-bottom-right pointer-events-none z-0" aria-hidden="true"></div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Pricing Hero -->
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
            {{ t('landing.pricing_hero_headline') }}
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto">
            {{ t('landing.pricing_hero_subheadline') }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto items-start">
          <!-- Free Plan -->
          <div class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors">
             <h3 class="text-xl font-bold text-white mb-2">{{ t('landing.pricing_free_name') }}</h3>
             <p class="text-gray-400 text-sm mb-4">{{ t('landing.pricing_free_tagline') }}</p>
             <div class="flex items-baseline mb-6">
                <span class="text-4xl font-extrabold text-white">{{ formatSats(pricing.free.sats_per_year) }}</span>
                <span class="text-gray-400 ml-2">{{ t('landing.pricing_free_price_period') }}</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">
               {{ t('landing.pricing_free_description') }}
             </p>
             <ul class="space-y-3 mb-8">
               <li
                 v-for="key in planFeatures.free.feature_keys"
                 :key="key"
                 class="flex items-start text-gray-300 text-sm"
               >
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>{{ t('plans.features.' + key) }}</span>
               </li>
             </ul>
             <router-link
               v-if="!authStore.isAuthenticated"
               to="/register"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               {{ t('landing.pricing_free_cta') }}
             </router-link>
             <router-link
               v-else
               to="/stores"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               {{ t('landing.pricing_free_cta_logged_in') }}
             </router-link>
          </div>

          <!-- Pro Plan (Highlighted) -->
          <div class="bg-gray-800 rounded-2xl p-8 border-2 border-indigo-500 shadow-2xl relative transform md:scale-[1.02] z-10">
             <div class="absolute top-0 right-0 -mt-4 mr-4 bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
               {{ t('landing.pricing_pro_badge') }}
             </div>
             <h3 class="text-2xl font-bold text-white mb-2">{{ t('landing.pricing_pro_name') }}</h3>
             <p class="text-gray-400 text-sm mb-4">{{ t('landing.pricing_pro_tagline') }}</p>
             <div class="flex items-baseline flex-wrap gap-x-1 mb-6">
                <span v-if="pricing.pro.sats_per_month_display !== BETA_PRO_SATS_PER_MONTH" class="text-xl font-medium text-gray-500 line-through mr-2">{{ formatSats(pricing.pro.sats_per_month_display) }}</span>
                <span class="text-5xl font-extrabold text-white">{{ formatSats(BETA_PRO_SATS_PER_MONTH) }}</span>
                <span class="text-indigo-300">{{ t('landing.pricing_pro_price_period') }}</span>
                <span class="text-indigo-300/80 text-sm">{{ t('landing.pricing_pro_price_note') }}</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">
               {{ t('landing.pricing_pro_description') }}
             </p>
             <ul class="space-y-3 mb-8">
               <li
                 v-for="key in planFeatures.pro.feature_keys"
                 :key="key"
                 class="flex items-start text-white font-medium text-sm"
               >
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>{{ t('plans.features.' + key) }}</span>
               </li>
             </ul>
             <button
                v-if="authStore.isAuthenticated"
                @click="handleUpgrade('pro')"
                :disabled="subscribing"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ subscribing ? t('stores.processing') : t('landing.pricing_pro_cta') }}
              </button>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
              >
                {{ t('landing.pricing_pro_cta_guest') }}
              </router-link>
          </div>
        </div>

        <!-- Need more? Enterprise teaser -->
        <div class="mt-12 max-w-2xl mx-auto">
          <div class="bg-gray-800/50 rounded-2xl p-6 border border-gray-700">
            <h3 class="text-lg font-bold text-white mb-2">{{ t('landing.pricing_need_more_headline') }}</h3>
            <p class="text-gray-400 text-sm mb-4">
              {{ t('landing.pricing_need_more_text') }}
            </p>
            <ul class="space-y-2 mb-6 text-gray-300 text-sm">
              <li
                v-for="key in planFeatures.enterprise.feature_keys"
                :key="key"
                class="flex items-center gap-2"
              >
                <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ t('plans.features.' + key) }}</span>
              </li>
            </ul>
            <a
              href="mailto:hello@satflux.io"
              class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 font-medium text-sm transition-colors"
            >
              {{ t('landing.pricing_need_more_cta') }}
            </a>
          </div>
        </div>
        
        <!-- Error message -->
        <div v-if="subscribeError" class="mt-8 max-w-md mx-auto">
          <div class="bg-red-900/50 border border-red-500/50 rounded-lg p-4 text-center">
            <p class="text-sm text-red-200">{{ subscribeError }}</p>
          </div>
        </div>

        <!-- Your payments never stop -->
        <div class="mt-16 max-w-3xl mx-auto">
          <div class="bg-indigo-900/20 border border-indigo-500/30 rounded-xl p-6 text-center">
            <div class="flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-indigo-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <h3 class="text-lg font-bold text-white">{{ t('landing.pricing_trust_headline') }}</h3>
            </div>
            <p class="text-gray-300 text-sm leading-relaxed">
              {{ t('landing.pricing_trust_text') }}
            </p>
          </div>
        </div>

        <!-- FAQ -->
        <div class="mt-16 max-w-2xl mx-auto">
          <h3 class="text-xl font-bold text-white mb-6 text-center">{{ t('landing.pricing_faq_title') }}</h3>
          <div class="space-y-4">
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4 class="font-semibold text-white mb-2">{{ t('landing.pricing_faq_custody_q') }}</h4>
              <p class="text-gray-400 text-sm">{{ t('landing.pricing_faq_custody_a') }}</p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4 class="font-semibold text-white mb-2">{{ t('landing.pricing_faq_switch_q') }}</h4>
              <p class="text-gray-400 text-sm">{{ t('landing.pricing_faq_switch_a') }}</p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4 class="font-semibold text-white mb-2">{{ t('landing.pricing_faq_expire_q') }}</h4>
              <p class="text-gray-400 text-sm">{{ t('landing.pricing_faq_expire_a') }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '../store/auth';
import { usePricing, BETA_PRO_SATS_PER_MONTH } from '../composables/usePricing';
import { usePlanFeatures } from '../composables/usePlanFeatures';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';
import api from '../services/api';

const { t } = useI18n();
const { pricing, formatSats, load: loadPricing } = usePricing();
const { planFeatures, load: loadPlanFeatures } = usePlanFeatures();

//const router = useRouter();
const authStore = useAuthStore();
const subscribing = ref(false);
const subscribeError = ref('');
const showPosModal = ref(false);

const posDemoUrl = 'https://satflux.org/apps/rivBmZNgktMaJ3CqMrtCuekoDex/pos';

// Ensure user, pricing and plan features are fetched on mount
onMounted(async () => {
  await Promise.all([loadPricing(), loadPlanFeatures()]);
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
      // Fix: use e.currentTarget instead of this, and cast to HTMLAnchorElement
      const targetAnchor = e.currentTarget as HTMLAnchorElement;
      const href = targetAnchor.getAttribute('href');
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

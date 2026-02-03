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
           <!-- Vertical Connector Line (Desktop) -->
           <div class="absolute left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-transparent via-indigo-500/50 to-transparent hidden md:block transform -translate-x-1/2 z-0"></div>
           
           <div class="space-y-12 md:space-y-0">
            <!-- Step 1: Registration -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
              <!-- Dot on timeline -->
              <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>
              
              <div class="md:w-5/12 text-right pr-8 md:pr-16 order-2 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step1_title') }}</h3>
                   <span class="flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50 md:hidden">1</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step1_text') }}
                 </p>
              </div>
              
              <div class="md:w-5/12 flex justify-start pl-8 md:pl-16 order-1 md:order-2">
                <!-- Removed hover scaling to avoid UX issues with form inputs -->
                <div class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 shadow-2xl relative overflow-hidden group hover:border-indigo-500/50 transition-colors duration-500">
                  <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                  <!-- Embedded Registration Form -->
                  <div class="relative z-10">
                    <RegistrationForm :show-login-link="false" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 2: Wallet Connection -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>

               <div class="md:w-5/12 flex justify-end pr-8 md:pr-16 order-1">
                  <div class="flex flex-col gap-10 w-full max-w-sm mt-12 md:mt-0">
                      <!-- Blink Card (Shifted left) -->
                      <div class="relative group/card cursor-pointer transform md:-translate-x-12 transition-transform duration-500 hover:scale-105">
                          <!-- Card Content -->
                          <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex items-center gap-6 transition-all duration-300 group-hover/card:border-orange-500/50 group-hover/card:bg-gray-800/80">
                              <img src="/img/wallets/blink.png" alt="Blink Wallet" class="w-20 h-20 rounded-2xl object-contain p-2 bg-white/5 border border-white/10 shadow-inner">
                              <div>
                                  <h4 class="text-2xl font-bold text-white mb-1">Blink Wallet</h4>
                                  <p class="text-sm text-orange-400 font-medium">Lightning Speed</p>
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
                                <img src="/img/wallets/aqua.png" alt="Aqua Wallet" class="w-full h-full object-contain">
                              </div>
                              <div>
                                  <h4 class="text-2xl font-bold text-white mb-1">Aqua Wallet</h4>
                                  <p class="text-sm text-blue-400 font-medium">Non-custodial</p>
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

               <div class="md:w-5/12 text-left pl-8 md:pl-16 order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step2_title') }}</h3>
                   <span class="md:hidden flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50">2</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step2_text') }}
                 </p>
               </div>
            </div>

            <!-- Step 3: PoS Terminal (Interactive) -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[100vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>

               <div class="md:w-5/12 text-right pr-8 md:pr-16 order-2 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step3_title') }}</h3>
                   <span class="md:hidden flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50">3</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed mb-6">
                   {{ t('landing.step3_text') }}
                 </p>
                 <p class="text-sm text-green-400/80 hidden md:block">
                    {{ t('landing.how_it_works_demo_real') }}
                 </p>
                 
                 <!-- Mobile Button -->
                 <div class="md:hidden mt-4">
                    <button
                      @click="showPosModal = true"
                      class="inline-flex items-center justify-center px-6 py-3 text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 transition-all shadow-lg"
                    >
                       {{ t('landing.how_it_works_open_pos_mobile') }}
                    </button>
                 </div>
               </div>

               <div class="md:w-5/12 flex justify-start pl-8 md:pl-16 order-1 md:order-2">
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
                         <span class="inline-block px-3 py-1 bg-black/50 text-white/50 text-xs rounded-full backdrop-blur-sm">Live Demo</span>
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

               <div class="md:w-5/12 flex justify-end pr-8 md:pr-16 order-1">
                  <div class="flex flex-wrap gap-4 max-w-md justify-end">
                     <div class="bg-gray-100 text-gray-900 px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-75">Woo</div>
                     <div class="bg-[#95BF47] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-100">Shopify</div>
                     <div class="bg-[#F46F25] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-150">Magento</div>
                     <div class="bg-[#0678BE] text-white px-4 py-2 rounded-lg font-bold shadow-lg transform group-hover:-translate-y-1 transition-transform delay-200">Drupal</div>
                     <div class="w-full text-center text-gray-400 text-xs mt-2 font-mono bg-gray-900/50 p-2 rounded border border-gray-700/50">API Key: sk_live_...</div>
                  </div>
               </div>

               <div class="md:w-5/12 text-left pl-8 md:pl-16 order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step4_title') }}</h3>
                   <span class="md:hidden flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50">4</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step4_text') }}
                 </p>
               </div>
            </div>

            <!-- Step 5: LN Address -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>

               <div class="md:w-5/12 text-right pr-8 md:pr-16 order-2 md:order-1">
                 <div class="inline-flex items-center gap-3 mb-4 md:flex-row-reverse">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step5_title') }}</h3>
                   <span class="md:hidden flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50">5</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step5_text') }}
                 </p>
               </div>
               
               <div class="md:w-5/12 flex justify-start pl-8 md:pl-16 order-1 md:order-2">
                  <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-1 rounded-2xl w-full max-w-sm shadow-xl shadow-purple-900/20 transform group-hover:scale-105 transition-transform duration-500">
                     <div class="bg-gray-900 rounded-xl p-8 text-center relative overflow-hidden">
                        <!-- Shine effect -->
                        <div class="absolute inset-0 bg-gradient-to-tr from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                        <div class="w-16 h-16 mx-auto bg-gray-800 rounded-full mb-4 flex items-center justify-center shadow-inner">
                          <svg class="w-8 h-8 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="text-white font-mono text-lg md:text-xl">satoshi<span class="text-indigo-400">@satflux.org</span></div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Step 6: Accounting Exports -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:min-h-[75vh] py-12 relative group">
               <div class="absolute left-1/2 top-1/2 w-4 h-4 bg-gray-900 border-2 border-indigo-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 hidden md:block shadow-[0_0_10px_rgba(99,102,241,0.5)] group-hover:scale-125 transition-transform z-10"></div>

               <div class="md:w-5/12 flex justify-end pr-8 md:pr-16 order-1">
                  <div class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 flex flex-col items-center gap-6 transform group-hover:scale-105 transition-transform duration-500 relative overflow-hidden">
                     <div class="absolute top-0 right-0 p-4 opacity-10">
                        <svg class="w-32 h-32 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                     </div>
                     <div class="grid grid-cols-2 gap-4 w-full relative z-10">
                        <div class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-green-500/10 hover:border-green-500/50 transition-colors">
                           <span class="font-bold text-white text-lg mb-1">CSV</span>
                           <span class="text-xs text-gray-400">Excel / Pohoda</span>
                        </div>
                        <div class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-red-500/10 hover:border-red-500/50 transition-colors">
                           <span class="font-bold text-white text-lg mb-1">PDF</span>
                           <span class="text-xs text-gray-400">Tlač</span>
                        </div>
                     </div>
                     <div class="w-full bg-gray-900 rounded-lg p-3 border border-gray-700 flex items-center justify-between text-xs text-gray-400 font-mono">
                        <span>export_2026_02.csv</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                     </div>
                  </div>
               </div>

               <div class="md:w-5/12 text-left pl-8 md:pl-16 order-2">
                 <div class="inline-flex items-center gap-3 mb-4">
                   <h3 class="text-3xl md:text-5xl font-bold text-white">{{ t('landing.step6_title') }}</h3>
                   <span class="md:hidden flex items-center justify-center w-12 h-12 rounded-full bg-indigo-500/20 text-indigo-400 font-bold text-xl border border-indigo-500/50">6</span>
                 </div>
                 <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                   {{ t('landing.step6_text') }}
                 </p>
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
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
            {{ t('landing.transparent_pricing') }}
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto">
            {{ t('landing.pricing_description') }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto items-start">
          <!-- Free Plan -->
          <div class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors">
             <h3 class="text-xl font-bold text-white mb-2">Free</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-4xl font-extrabold text-white">€0</span>
                <span class="text-gray-400 ml-2">/ year</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">Perfect for getting started with Bitcoin payments</p>
             <ul class="space-y-3 mb-8">
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>1 store</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Unlimited PoS terminals per store</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Unlimited Lightning addresses</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>1 API key</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Manual CSV exports</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Basic payment overview</span>
               </li>
             </ul>
             <router-link
               v-if="!authStore.isAuthenticated"
               to="/register"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               Start Free
             </router-link>
             <router-link
               v-else
               to="/stores"
               class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
             >
               View Stores
             </router-link>
          </div>

          <!-- Pro Plan (Highlighted) -->
          <div class="bg-gray-800 rounded-2xl p-8 border-2 border-indigo-500 shadow-2xl relative transform md:scale-105 z-10">
             <div class="absolute top-0 right-0 -mt-4 mr-4 bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
               Popular
             </div>
             <h3 class="text-2xl font-bold text-white mb-2">Pro</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-5xl font-extrabold text-white">€99</span>
                <span class="text-indigo-300 ml-2">/ year</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">For growing businesses with multiple stores</p>
             <ul class="space-y-3 mb-8">
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>3 stores</span>
               </li>
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Unlimited PoS terminals per store</span>
               </li>
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>3 API keys</span>
               </li>
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Manual + automatic monthly CSV exports</span>
               </li>
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Advanced statistics (per store, per PoS, overall)</span>
               </li>
               <li class="flex items-start text-white font-medium text-sm">
                 <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Basic payment overview</span>
               </li>
             </ul>
             <button
                v-if="authStore.isAuthenticated"
                @click="handleUpgrade('pro')"
                :disabled="subscribing"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ subscribing ? 'Processing...' : 'Upgrade Now' }}
              </button>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
              >
                Start Free
              </router-link>
          </div>

          <!-- Enterprise Plan -->
          <div class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors">
             <h3 class="text-xl font-bold text-white mb-2">Enterprise</h3>
             <div class="flex items-baseline mb-6">
                <span class="text-4xl font-extrabold text-white">€299</span>
                <span class="text-gray-400 ml-2">/ year</span>
             </div>
             <p class="text-gray-400 text-sm mb-6">For businesses that need unlimited scale</p>
             <ul class="space-y-3 mb-8">
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Unlimited stores</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Unlimited API keys</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>All Pro features</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Per-store user management (roles)</span>
               </li>
               <li class="flex items-start text-gray-300 text-sm">
                 <svg class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span>Priority support</span>
               </li>
             </ul>
             <button
                v-if="authStore.isAuthenticated"
                @click="handleUpgrade('enterprise')"
                :disabled="subscribing"
                class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ subscribing ? 'Processing...' : 'Upgrade' }}
              </button>
              <router-link
                v-else
                to="/register"
                class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
              >
                Start Free
              </router-link>
          </div>
        </div>
        
        <!-- Error message -->
        <div v-if="subscribeError" class="mt-8 max-w-md mx-auto">
          <div class="bg-red-900/50 border border-red-500/50 rounded-lg p-4 text-center">
            <p class="text-sm text-red-200">{{ subscribeError }}</p>
          </div>
        </div>

        <!-- Important Note -->
        <div class="mt-12 max-w-3xl mx-auto">
          <div class="bg-indigo-900/20 border border-indigo-500/30 rounded-xl p-6 text-center">
            <div class="flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-indigo-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <h3 class="text-lg font-bold text-white">Non-Custodial & Payment Guarantee</h3>
            </div>
            <p class="text-gray-300 text-sm leading-relaxed">
              <strong class="text-white">Payments are NEVER blocked.</strong> Subscription plans only affect management features and limits. 
              Your existing stores, PoS terminals, Lightning addresses, and payment acceptance continue to work regardless of your plan status. 
              This is a non-custodial system - you always maintain full control of your funds.
            </p>
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
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';
import RegistrationForm from '../components/auth/RegistrationForm.vue';
import api from '../services/api';

const { t } = useI18n();

//const router = useRouter();
const authStore = useAuthStore();
const subscribing = ref(false);
const subscribeError = ref('');
const showPosModal = ref(false);

const posDemoUrl = 'https://satflux.org/apps/rivBmZNgktMaJ3CqMrtCuekoDex/pos';

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

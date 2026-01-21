<template>
  <AppShowLayout ref="layoutRef">
    <template #default="{ app, store }">
      <!-- Header -->
      <AppShowHeader
        :title="app.name || 'Point of Sale'"
        :subtitle="`PoS - ${store.name}`"
        :app-url="btcpayAppUrl"
        open-button-text="Open PoS"
        form-id="pos-settings-form"
        save-button-text="Save Settings"
        saving-text="Saving..."
        :saving="saving"
        :error="displayedError"
        :success="success"
      />

      <!-- Content Container -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form id="pos-settings-form" @submit.prevent="handleSubmit" class="space-y-6">
          
          <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-6 sm:p-8 space-y-6">
              
              <!-- App Name and Title -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="appName" class="block text-sm font-medium text-gray-300 mb-1">
                    App Name <span class="text-red-400">*</span>
                  </label>
                  <input
                    id="appName"
                    v-model="form.appName"
                    type="text"
                    required
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>

                <div>
                  <label for="title" class="block text-sm font-medium text-gray-300 mb-1">
                    Title display to customer
                  </label>
                  <input
                    id="title"
                    v-model="form.title"
                    type="text"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>
              </div>

              <!-- Default View Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">
                  Point of Sale Style
                </label>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <!-- Light (Keypad) -->
                  <div 
                    @click="form.defaultView = 'Light'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Light' 
                        ? 'border-indigo-500 bg-indigo-500/10' 
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800'
                    ]"
                  >
                    <div :class="[
                      'p-3 rounded-full',
                      form.defaultView === 'Light' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-400'
                    ]">
                       <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                    </div>
                    <span class="text-sm font-medium text-white">Keypad</span>
                    <div v-if="form.defaultView === 'Light'" class="absolute top-2 right-2 text-indigo-500">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    </div>
                  </div>

                  <!-- Static (Product List) -->
                  <div 
                    @click="form.defaultView = 'Static'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Static' 
                        ? 'border-indigo-500 bg-indigo-500/10' 
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800'
                    ]"
                  >
                     <div :class="[
                      'p-3 rounded-full',
                      form.defaultView === 'Static' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-400'
                    ]">
                       <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                    </div>
                    <span class="text-sm font-medium text-white">Product list</span>
                     <div v-if="form.defaultView === 'Static'" class="absolute top-2 right-2 text-indigo-500">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    </div>
                  </div>

                  <!-- Cart (Product List + Cart) -->
                  <div 
                    @click="form.defaultView = 'Cart'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Cart' 
                        ? 'border-indigo-500 bg-indigo-500/10' 
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800'
                    ]"
                  >
                     <div :class="[
                      'p-3 rounded-full',
                      form.defaultView === 'Cart' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-400'
                    ]">
                       <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                    <span class="text-sm font-medium text-white">List with cart</span>
                     <div v-if="form.defaultView === 'Cart'" class="absolute top-2 right-2 text-indigo-500">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    </div>
                  </div>

                  <!-- Print -->
                  <div 
                    @click="form.defaultView = 'Print'"
                    :class="[
                      'relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 flex flex-col items-center text-center gap-3',
                      form.defaultView === 'Print' 
                        ? 'border-indigo-500 bg-indigo-500/10' 
                        : 'border-gray-700 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-800'
                    ]"
                  >
                     <div :class="[
                      'p-3 rounded-full',
                      form.defaultView === 'Print' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-400'
                    ]">
                       <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    </div>
                    <span class="text-sm font-medium text-white">Print Display</span>
                     <div v-if="form.defaultView === 'Print'" class="absolute top-2 right-2 text-indigo-500">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <div v-if="form.defaultView === 'Static' || form.defaultView === 'Cart'">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-1">
                  Description
                </label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                ></textarea>
              </div>

              <!-- Keypad Settings -->
              <div v-if="form.defaultView === 'Light'" class="mt-6">
                <div class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl">
                  <input
                    id="showItems"
                    v-model="form.showItems"
                    type="checkbox"
                    class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                  />
                  <label for="showItems" class="ml-3 block text-sm font-medium text-gray-200">
                    Display item selection for keypad
                  </label>
                </div>
              </div>

              <!-- Products Editor -->
              <div v-if="shouldShowProductsEditor">
                <PointOfSaleProductsEditor
                  v-model:products="products"
                  :currency="form.currency || store?.default_currency || 'EUR'"
                  @add="addProduct"
                  @edit="editProduct"
                />
              </div>              

              <div class="border-t border-gray-700/50 pt-6">
                 <h3 class="text-lg font-medium text-white mb-4">Checkout Settings</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <!-- Currency -->
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-300 mb-1">
                          Currency
                        </label>
                         <input
                          id="currency"
                          v-model="form.currency"
                          type="text"
                          list="currency-selection-suggestion"
                          placeholder="Select or type currency"
                          class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        />
                        <datalist id="currency-selection-suggestion">
                          <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                            {{ currency.code }} - {{ currency.name }}
                          </option>
                        </datalist>
                        <p class="mt-1 text-xs text-gray-500">Uses store default ({{ store?.default_currency || 'EUR' }}) if empty</p>
                    </div>

                    <!-- Tax Rate -->
                    <div>
                        <label for="defaultTaxRate" class="block text-sm font-medium text-gray-300 mb-1">
                          Default Tax Rate
                        </label>
                        <div class="flex rounded-xl shadow-sm">
                          <input
                            id="defaultTaxRate"
                            v-model.number="form.defaultTaxRate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="flex-1 min-w-0 block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-l-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="0.00"
                          />
                          <span class="inline-flex items-center px-4 rounded-r-xl border border-l-0 border-gray-600 bg-gray-700 text-gray-300 text-sm font-medium">
                            %
                          </span>
                        </div>
                    </div>

                    <!-- Customer Data -->
                    <div>
                        <label for="requestCustomerData" class="block text-sm font-medium text-gray-300 mb-1">
                           Request customer data
                        </label>
                        <select
                          id="requestCustomerData"
                          v-model="form.requestCustomerData"
                          class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all appearance-none"
                        >
                          <option value="">Do not request</option>
                          <option value="email">Email only</option>
                          <option value="name">Name only</option>
                          <option value="email_name">Email and Name</option>
                        </select>
                    </div>
                 </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <!-- Tips -->
                 <div class="bg-gray-900 border border-gray-700 p-4 rounded-xl">
                    <div class="flex items-center mb-4">
                      <input
                        id="enableTips"
                        v-model="form.enableTips"
                        type="checkbox"
                        class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                      />
                      <label for="enableTips" class="ml-3 block text-sm font-medium text-white">
                        Enable tips
                      </label>
                    </div>
                    
                    <div v-if="form.enableTips">
                      <label for="tipsMessage" class="block text-xs font-medium text-gray-400 mb-1">
                        Tip Message <span class="text-red-400">*</span>
                      </label>
                      <input
                        id="tipsMessage"
                        v-model="form.tipsMessage"
                        type="text"
                        required
                        class="block w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                      />
                    </div>
                 </div>

                 <!-- Custom Payments -->
                 <div class="bg-gray-900 border border-gray-700 p-4 rounded-xl">
                    <div class="flex items-center mb-4">
                      <input
                        id="showCustomAmount"
                        v-model="form.showCustomAmount"
                        type="checkbox"
                        class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                      />
                      <label for="showCustomAmount" class="ml-3 block text-sm font-medium text-white">
                        Allow custom amount
                      </label>
                    </div>
                    
                    <div v-if="form.showCustomAmount">
                      <label for="customAmountPayButtonText" class="block text-xs font-medium text-gray-400 mb-1">
                        Pay Button Text <span class="text-red-400">*</span>
                      </label>
                      <input
                        id="customAmountPayButtonText"
                        v-model="form.customAmountPayButtonText"
                        type="text"
                        required
                        class="block w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                      />
                    </div>
                 </div>
                 
                 <!-- Discounts -->
                 <div class="bg-gray-900 border border-gray-700 p-4 rounded-xl">
                     <div class="flex items-center">
                      <input
                        id="showDiscount"
                        v-model="form.showDiscount"
                        type="checkbox"
                        class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                      />
                      <label for="showDiscount" class="ml-3 block text-sm font-medium text-white">
                        Allow discount entry (%)
                      </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 ml-8">Not recommended for self-checkout.</p>
                 </div>
              </div>


              <!-- Cart Options -->
              <div v-if="form.defaultView === 'Cart'" class="border-t border-gray-700/50 pt-6">
                 <h3 class="text-lg font-medium text-white mb-4">Cart Settings</h3>
                 <div class="flex flex-col sm:flex-row gap-6">
                    <div class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl flex-1">
                      <input
                        id="showSearch"
                        v-model="form.showSearch"
                        type="checkbox"
                        class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                      />
                      <label for="showSearch" class="ml-3 block text-sm font-medium text-white">
                        Display search bar
                      </label>
                    </div>

                    <div class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl flex-1">
                      <input
                        id="showCategories"
                        v-model="form.showCategories"
                        type="checkbox"
                        class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                      />
                      <label for="showCategories" class="ml-3 block text-sm font-medium text-white">
                        Display categories
                      </label>
                    </div>
                 </div>
              </div>

               <!-- Button Text -->
              <div v-if="form.defaultView === 'Static' || form.defaultView === 'Cart'" class="border-t border-gray-700/50 pt-6">
                 <label for="fixedAmountPayButtonText" class="block text-sm font-medium text-gray-300 mb-1">
                    Buy Button Text <span class="text-red-400">*</span>
                 </label>
                  <input
                    id="fixedAmountPayButtonText"
                    v-model="form.fixedAmountPayButtonText"
                    type="text"
                    required
                    placeholder="Buy for {0}"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                  <p class="mt-1 text-xs text-gray-500">Use {0} as placeholder for price.</p>
              </div>


              <!-- Additional Options (Accordion style) -->
              <div class="border-t border-gray-700/50 pt-6 space-y-4">
                  <h3 class="text-xl font-bold text-white mb-4">Advanced Options</h3>
                  
                  <!-- HTML Headers -->
                 <div class="border border-gray-700 rounded-xl overflow-hidden">
                    <button
                      type="button"
                      @click="accordionSections.htmlHeaders = !accordionSections.htmlHeaders"
                      class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                    >
                      <span class="font-medium text-white">HTML Headers</span>
                      <svg class="h-5 w-5 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-180': accordionSections.htmlHeaders }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="accordionSections.htmlHeaders" class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4">
                       <div>
                        <label for="htmlLang" class="block text-sm font-medium text-gray-300 mb-1">HTML Lang</label>
                        <input id="htmlLang" v-model="form.htmlLang" type="text" placeholder="en" class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                       </div>
                       <div>
                        <label for="htmlMetaTags" class="block text-sm font-medium text-gray-300 mb-1">Meta Tags</label>
                        <textarea id="htmlMetaTags" v-model="form.htmlMetaTags" rows="3" class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                       </div>
                    </div>
                 </div>

                 <!-- Redirects -->
                 <div class="border border-gray-700 rounded-xl overflow-hidden">
                    <button
                      type="button"
                      @click="accordionSections.redirects = !accordionSections.redirects"
                      class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                    >
                      <span class="font-medium text-white">Redirects</span>
                       <svg class="h-5 w-5 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-180': accordionSections.redirects }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="accordionSections.redirects" class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4">
                       <div>
                        <label for="redirectUrl" class="block text-sm font-medium text-gray-300 mb-1">Redirect URL</label>
                        <input id="redirectUrl" v-model="form.redirectUrl" type="url" placeholder="https://example.com/thanks" class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                       </div>
                       <div>
                        <label for="redirectAutomatically" class="block text-sm font-medium text-gray-300 mb-1">Redirect Automatically</label>
                        <select id="redirectAutomatically" v-model="form.redirectAutomatically" class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                           <option value="">Use Store Settings</option>
                           <option value="true">Yes</option>
                           <option value="false">No</option>
                        </select>
                       </div>
                    </div>
                 </div>

                 <!-- Notifications -->
                 <div class="border border-gray-700 rounded-xl overflow-hidden">
                    <button
                      type="button"
                      @click="accordionSections.notifications = !accordionSections.notifications"
                      class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                    >
                      <span class="font-medium text-white">Notification URL Callbacks</span>
                       <svg class="h-5 w-5 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-180': accordionSections.notifications }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                     <div v-show="accordionSections.notifications" class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-4">
                        <div>
                        <label for="notificationUrl" class="block text-sm font-medium text-gray-300 mb-1">Callback Notification URL</label>
                        <input id="notificationUrl" v-model="form.notificationUrl" type="url" placeholder="https://example.com/callback" class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                       </div>
                       <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl">
                          <p class="text-sm text-red-400 font-bold mb-2">⚠️ Security Warning</p>
                          <p class="text-xs text-red-300">Never trust any field other than the invoice ID. Verify details with your backend.</p>
                       </div>
                    </div>
                 </div>
                 
                 <!-- Embeds -->
                 <div class="border border-gray-700 rounded-xl overflow-hidden">
                    <button
                      type="button"
                      @click="accordionSections.embed = !accordionSections.embed"
                      class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-800 hover:bg-gray-700 transition-colors"
                    >
                      <span class="font-medium text-white">Embed Codes</span>
                       <svg class="h-5 w-5 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-180': accordionSections.embed }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="accordionSections.embed" class="px-6 py-6 bg-gray-900 border-t border-gray-700 space-y-6">
                       <div>
                          <p class="text-sm font-medium text-gray-300 mb-2">Button Embed Code</p>
                          <div class="bg-gray-950 p-4 rounded-xl border border-gray-700 overflow-x-auto">
                             <pre class="text-xs text-gray-400 font-mono">{{ embedFormCode }}</pre>
                          </div>
                       </div>
                       <div>
                          <p class="text-sm font-medium text-gray-300 mb-2">Iframe Embed Code</p>
                           <div class="bg-gray-950 p-4 rounded-xl border border-gray-700 overflow-x-auto">
                             <pre class="text-xs text-gray-400 font-mono">{{ embedIframeCode }}</pre>
                          </div>
                       </div>
                    </div>
                 </div>
              </div>


              <!-- Delete Button -->
              <div class="border-t border-gray-700/50 pt-6">
                <button
                  type="button"
                  @click="showDeleteModal = true"
                  :disabled="saving || deleting"
                  class="inline-flex items-center px-4 py-2 border border-red-600 rounded-xl text-sm font-medium text-red-400 hover:bg-red-600 hover:text-white transition-colors"
                >
                   <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  Delete App
                </button>
              </div>

            </div>
          </div>
        </form>
      </div>

    </template>
  </AppShowLayout>

  <!-- Delete Confirmation Modal -->
  <DeleteAppModal
    :is-open="showDeleteModal"
    :app-name="layoutRef?.app?.name || ''"
    :deleting="deleting"
    :error="deleteError"
    @close="showDeleteModal = false"
    @delete="handleDelete"
  />

  <!-- Product Edit Drawer -->
  <ProductEditDrawer
    :is-open="showProductDrawer"
    :product="currentProduct"
    :currency="form.currency || layoutRef?.store?.default_currency || 'EUR'"
    :store-id="storeId"
    @close="showProductDrawer = false"
    @save="handleProductSave"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch, watchEffect, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { useStoresStore } from '../../store/stores';
import ProductEditDrawer from '../../components/stores/ProductEditDrawer.vue';
import PointOfSaleProductsEditor from '../../components/stores/PointOfSaleProductsEditor.vue';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import AppShowHeader from '../../components/stores/AppShowHeader.vue';
import DeleteAppModal from '../../components/stores/DeleteAppModal.vue';
import { currencies } from '../../data/currencies';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);
const saving = ref(false);
const error = ref('');
const success = ref('');

// Computed property for displayed error
const displayedError = computed(() => {
  if (success.value) {
    return '';
  }
  return error.value;
});

const showDeleteModal = ref(false);
const deleteError = ref('');
const deleting = ref(false);

// Products editor state
const products = ref<any[]>([]);
const showProductDrawer = ref(false);
const editingProductIndex = ref<number | null>(null);

const form = ref({
  appName: '',
  title: '',
  description: '',
  defaultView: 'Light',
  currency: '',
  showItems: false,
  showCustomAmount: false,
  showDiscount: false,
  showSearch: false,
  showCategories: false,
  enableTips: false,
  tipsMessage: 'Do you want to leave a tip?',
  defaultTaxRate: 0,
  requestCustomerData: '',
  fixedAmountPayButtonText: 'Buy for {0}',
  customAmountPayButtonText: 'Pay',
  htmlLang: '',
  htmlMetaTags: '',
  redirectUrl: '',
  redirectAutomatically: '',
  notificationUrl: '',
});

const accordionSections = ref({
  htmlHeaders: false,
  redirects: false,
  notifications: false,
  embed: false,
});

// Computed property to determine if products editor should be shown
const shouldShowProductsEditor = computed(() => {
  if (form.value.defaultView === 'Static' || form.value.defaultView === 'Cart' || form.value.defaultView === 'Print') {
    return true;
  }
  if (form.value.defaultView === 'Light' && form.value.showItems) {
    return true;
  }
  return false;
});

// Computed property for BTCPay app URL
const btcpayAppUrl = computed(() => {
  const app = layoutRef.value?.app;
  if (!app) return '';
  const baseUrl = import.meta.env.VITE_BTCPAY_BASE_URL || 'https://pay.dvadsatjeden.org';
  let id = app.btcpay_app_id || 
              (app.config && app.config.id) ||
              (app.config && app.config.appId);
  
  if (!id && app.btcpay_app_url) {
    const urlParts = app.btcpay_app_url.split('/');
    id = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }
  
  if (!id) return '';
  return `${baseUrl}/apps/${id}/pos`;
});

const embedFormCode = computed(() => {
  if (!btcpayAppUrl.value) return '';
  return `<form method="POST" action="${btcpayAppUrl.value}">
  <input type="hidden" name="email" value="customer@example.com" />
  <input type="hidden" name="orderId" value="CustomOrderId" />
  <input type="hidden" name="notificationUrl" value="https://example.com/callbacks" />
  <input type="hidden" name="redirectUrl" value="https://example.com/thankyou" />
  <button type="submit" name="choiceKey" value="produkt">Buy now</button>
</form>`;
});

const embedIframeCode = computed(() => {
  if (!btcpayAppUrl.value) return '';
  return `<iframe src='${btcpayAppUrl.value}' style='max-width: 100%; border: 0;'></iframe>`;
});

// Helper function to generate product ID
function generateProductId(title: string): string {
  if (!title) return '';
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function addProduct() {
  editingProductIndex.value = null;
  showProductDrawer.value = true;
}

function editProduct(index: number) {
  editingProductIndex.value = index;
  showProductDrawer.value = true;
}

function handleProductSave(product: any) {
  if (editingProductIndex.value !== null) {
    products.value[editingProductIndex.value] = product;
  } else {
    products.value.push(product);
  }
  editingProductIndex.value = null;
}

const currentProduct = computed(() => {
  if (editingProductIndex.value !== null && products.value[editingProductIndex.value]) {
    return products.value[editingProductIndex.value];
  }
  return null;
});

async function loadApp(clearErrors: boolean = true) {
  const app = layoutRef.value?.app;
  const store = layoutRef.value?.store;
  
  if (!app || !store) return;
  
  const hadSuccess = success.value;
  if (clearErrors && !hadSuccess) {
    error.value = '';
  }
  if (hadSuccess) {
    return;
  }
  
  form.value.appName = app.name || '';
  
  if (app.config) {
    const config = app.config;
    form.value.title = config.title || '';
    form.value.description = config.description || '';
    form.value.defaultView = config.defaultView || 'Light';
    form.value.currency = config.currency || (store?.default_currency || 'EUR');
    form.value.showItems = config.showItems || false;
    form.value.showCustomAmount = config.showCustomAmount || false;
    form.value.showDiscount = config.showDiscount || false;
    form.value.showSearch = config.showSearch ?? true;
    form.value.showCategories = config.showCategories ?? true;
    form.value.enableTips = config.enableTips || false;
    form.value.tipsMessage = config.tipsMessage || 'Do you want to leave a tip?';
    form.value.defaultTaxRate = config.defaultTaxRate ? parseFloat(String(config.defaultTaxRate)) : 0;
    form.value.requestCustomerData = config.requestCustomerData || '';
    form.value.fixedAmountPayButtonText = config.fixedAmountPayButtonText || 'Buy for {0}';
    form.value.customAmountPayButtonText = config.customAmountPayButtonText || 'Pay';
    form.value.htmlLang = config.htmlLang || '';
    form.value.htmlMetaTags = config.htmlMetaTags || '';
    form.value.redirectUrl = config.redirectUrl || '';
    form.value.redirectAutomatically = config.redirectAutomatically !== undefined ? String(config.redirectAutomatically) : '';
    form.value.notificationUrl = config.notificationUrl || '';

    let templateArray: any[] = [];
    const productsSource = config.items || config.template;
    
    if (productsSource) {
      if (Array.isArray(productsSource)) {
        templateArray = productsSource;
      } else if (typeof productsSource === 'string') {
        try {
          const parsed = JSON.parse(productsSource);
          templateArray = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.template) ? parsed.template : (Array.isArray(parsed.items) ? parsed.items : [parsed]));
        } catch (e) {
          console.warn('Failed to parse products', e);
          templateArray = [];
        }
      }
    }
    
    if (templateArray && templateArray.length > 0) {
      products.value = templateArray.map((p: any) => {
        let inventory: number | null = null;
        if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) {
            inventory = invNum;
          }
        }
        
        return {
          id: p.id || '',
          title: p.title || '',
          priceType: p.priceType || 'Fixed',
          price: p.price ? parseFloat(String(p.price)) : 0,
          taxRate: p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== '' ? parseFloat(String(p.taxRate)) : null,
          image: p.image || '',
          description: p.description || '',
          categories: p.categories ? (Array.isArray(p.categories) ? p.categories.join(', ') : String(p.categories)) : null,
          inventory: inventory,
          buyButtonText: p.buyButtonText || null,
          disabled: p.disabled !== undefined ? p.disabled : false,
        };
      });
    } else {
      products.value = [];
    }
  } else {
    form.value.currency = store?.default_currency || 'EUR';
    products.value = [];
  }
}

const isReloadingAfterSubmit = ref(false);

watch(() => layoutRef.value?.app, () => {
  if (isReloadingAfterSubmit.value) return;
  if (success.value) return;
  loadApp();
}, { immediate: true, deep: true });

watch(success, (newSuccess) => {
  if (newSuccess) error.value = '';
});

watch(error, (newError) => {
  if (newError && success.value) {
    setTimeout(() => { if (success.value) error.value = ''; }, 0);
  }
}, { flush: 'post' });

watch(saving, (newSaving, oldSaving) => {
  if (oldSaving && !newSaving && success.value) {
     setTimeout(() => {
        if (success.value) {
           error.value = '';
           success.value = 'Settings saved successfully'; // Reinforce
        }
     }, 0);
  }
});

watchEffect(() => {
    if (success.value && error.value) error.value = '';
});


async function handleSubmit() {
  const app = layoutRef.value?.app;
  if (!app) return;
  
  saving.value = true;
  error.value = '';
  success.value = '';
  
  try {
     const template = products.value
        .filter(p => p.title)
        .map(p => {
          let inventory: number | null = null;
          if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
            const invNum = Number(p.inventory);
            if (!isNaN(invNum) && invNum >= 0) inventory = invNum;
          }
          
          return {
            id: p.id || generateProductId(p.title),
            title: p.title,
            disabled: p.disabled || false,
            description: p.description || null,
            categories: p.categories ? String(p.categories).split(',').map((c: string) => c.trim()).filter((c: string) => c) : null,
            image: p.image || null,
            priceType: p.priceType || 'Fixed',
            price: p.priceType !== 'Free' && p.priceType !== 'Topup' ? String(p.price || 0) : null,
            buyButtonText: p.buyButtonText || null,
            inventory: inventory,
            taxRate: p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== '' ? String(p.taxRate) : null,
          };
        });

    const config: any = {
      appName: form.value.appName,
      title: form.value.title,
      defaultView: form.value.defaultView,
      currency: form.value.currency,
      showItems: form.value.showItems,
      showCustomAmount: form.value.showCustomAmount,
      showDiscount: form.value.showDiscount,
      showSearch: form.value.showSearch,
      showCategories: form.value.showCategories,
      enableTips: form.value.enableTips,
      tipsMessage: form.value.tipsMessage || null,
      defaultTaxRate: form.value.defaultTaxRate ? String(form.value.defaultTaxRate) : null,
      requestCustomerData: form.value.requestCustomerData || null,
      fixedAmountPayButtonText: form.value.fixedAmountPayButtonText,
      customAmountPayButtonText: form.value.customAmountPayButtonText,
      htmlLang: form.value.htmlLang || null,
      htmlMetaTags: form.value.htmlMetaTags || null,
      redirectUrl: form.value.redirectUrl || null,
      redirectAutomatically: form.value.redirectAutomatically ? (form.value.redirectAutomatically === 'true' ? true : (form.value.redirectAutomatically === 'false' ? false : null)) : null,
      notificationUrl: form.value.notificationUrl || null,
    };

    if (form.value.defaultView === 'Static' || form.value.defaultView === 'Cart') {
      config.description = form.value.description || null;
    }

    if (shouldShowProductsEditor.value) {
      config.template = template;
    }

    await appsStore.updateApp(storeId.value, appId.value, {
      name: form.value.appName,
      config,
    });
    
    success.value = 'Settings saved successfully';
    error.value = '';
    isReloadingAfterSubmit.value = true;
    
    try {
      await new Promise(resolve => setTimeout(resolve, 500));
      if (layoutRef.value) {
        await (layoutRef.value as any).loadApp();
      }
      await new Promise(resolve => setTimeout(resolve, 0));
      
      // Manually refresh form data from updated app
       const updatedApp = layoutRef.value?.app;
       // ... (logic to sync form data handled by loadApp potentially or we can re-run loadApp logic here if needed, 
       // but strictly speaking, loadApp is reactive to layoutRef.app changes if we allow it or we can force it)
       // Since we set isReloadingAfterSubmit=true, watcher didn't run. We updated layoutRef.app via loadApp(). 
       // We should manually run loadApp(false) to sync form.
       await loadApp(false);

    } finally {
      isReloadingAfterSubmit.value = false;
    }

  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save settings';
    success.value = '';
  } finally {
    const hadSuccess = success.value;
    saving.value = false;
    if (hadSuccess) {
        error.value = '';
    }
  }
}


async function handleDelete() {
  const app = layoutRef.value?.app;
  if (!app) return;
  
  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    router.push({ name: 'stores-show', params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}
</script>

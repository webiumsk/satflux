<template>
  <div v-if="loading && !app" class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Loading app...</p>
  </div>

  <div v-else-if="store && app" class="flex h-screen bg-gray-100 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 overflow-hidden flex flex-col">
      <!-- Scrollable Content Area -->
      <div class="flex-1 overflow-y-auto">
        <!-- Point of Sale Header - Sticky -->
        <div v-if="app && app.app_type === 'PointOfSale'" class="sticky top-0 z-20 bg-white  shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-4">
          
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-xl font-bold text-gray-900 mb-2">{{ app.name || 'Point of Sale' }}</h1>
                <p class="text-sm text-gray-500">PoS - {{ store.name }}</p>
              </div>
              <div class="flex items-center gap-2">
                <a
                  v-if="app.btcpay_app_url"
                  :href="app.btcpay_app_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center px-2 md:px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                  Open PoS
                </a>
                <button
                  type="submit"
                  form="pos-settings-form"
                  :disabled="saving"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : 'Save Settings' }}
                </button>
              </div>
            </div>
          
        </div>
        </div>

        <!-- Crowdfund Header - Sticky -->
        <div v-if="app && app.app_type === 'Crowdfund'" class="sticky top-0 z-20 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-4">         
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-xl font-bold text-gray-900 mb-2">Update Crowdfund</h1>
              </div>
              <div class="flex items-center gap-3">
                <a
                  v-if="app.btcpay_app_url"
                  :href="app.btcpay_app_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center px-2 md:px-4 py-2 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                  Open Crowdfund
                </a>
                <button
                  type="submit"
                  form="crowdfund-form"
                  :disabled="(crowdfundFormRef?.saving ?? false) || saving"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
                >
                  {{ ((crowdfundFormRef?.saving ?? false) || saving) ? 'Saving...' : 'Update Crowdfund' }}
                </button>
              </div>
            </div>
          
        </div>
        </div>

        <template v-if="app.app_type === 'PointOfSale'">
          <!-- Content Container -->
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <!-- Settings Form -->
          <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Settings</h2>
          </div>

          <form id="pos-settings-form" @submit.prevent="handleSubmit" class="px-6 py-5 space-y-6">
            <!-- App Name and Title in one row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="appName" class="block text-sm font-medium text-gray-700">
                  App Name
                </label>
                <input
                  id="appName"
                  v-model="form.appName"
                  type="text"
                  required
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>

              <div>
                <label for="title" class="block text-sm font-medium text-gray-700">
                  Title
                </label>
                <input
                  id="title"
                  v-model="form.title"
                  type="text"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>
            </div>

            <!-- Default View -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-3">
                Point of Sale Style
              </label>
              <div class="btcpay-list-select grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="relative">
                  <input
                    type="radio"
                    value="Light"
                    id="DefaultView_Light"
                    v-model="form.defaultView"
                    class="sr-only"
                  />
                  <label
                    for="DefaultView_Light"
                    class="btcpay-list-select-item flex flex-col items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                    :class="form.defaultView === 'Light' ? 'border-indigo-600 bg-indigo-100' : 'border-gray-200 hover:border-indigo-500 hover:bg-indigo-50'"
                  >
                    <svg role="img" class="icon icon-pos-light w-8 h-8 mb-2" :class="form.defaultView === 'Light' ? 'text-indigo-600' : 'text-gray-600'">
                      <use href="/img/icon-sprite.svg#pos-light"></use>
                    </svg>
                    <span class="text-xs md:text-sm font-medium text-center">Keypad</span>
                  </label>
                </div>
                
                <div class="relative">
                  <input
                    type="radio"
                    value="Static"
                    id="DefaultView_Static"
                    v-model="form.defaultView"
                    class="sr-only"
                  />
                  <label
                    for="DefaultView_Static"
                    class="btcpay-list-select-item flex flex-col items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                    :class="form.defaultView === 'Static' ? 'border-indigo-600 bg-indigo-100' : 'border-gray-200 hover:border-indigo-500 hover:bg-indigo-50'"
                  >
                    <svg role="img" class="icon icon-pos-static w-8 h-8 mb-2" :class="form.defaultView === 'Static' ? 'text-indigo-600' : 'text-gray-600'">
                      <use href="/img/icon-sprite.svg#pos-static"></use>
                    </svg>
                    <span class="text-xs md:text-sm font-medium text-center">Product list</span>
                  </label>
                </div>

                <div class="relative">
                  <input
                    type="radio"
                    value="Cart"
                    id="DefaultView_Cart"
                    v-model="form.defaultView"
                    class="sr-only"
                  />
                  <label
                    for="DefaultView_Cart"
                    class="btcpay-list-select-item flex flex-col items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                    :class="form.defaultView === 'Cart' ? 'border-indigo-600 bg-indigo-100' : 'border-gray-200 hover:border-indigo-500 hover:bg-indigo-50'"
                  >
                    <svg role="img" class="icon icon-pos-cart w-8 h-8 mb-2" :class="form.defaultView === 'Cart' ? 'text-indigo-600' : 'text-gray-600'">
                      <use href="/img/icon-sprite.svg#pos-cart"></use>
                    </svg>
                    <span class="text-xs md:text-sm font-medium text-center">Product list with cart</span>
                  </label>
                </div>                

                <div class="relative">
                  <input
                    type="radio"
                    value="Print"
                    id="DefaultView_Print"
                    v-model="form.defaultView"
                    class="sr-only"
                  />
                  <label
                    for="DefaultView_Print"
                    class="btcpay-list-select-item flex flex-col items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                    :class="form.defaultView === 'Print' ? 'border-indigo-600 bg-indigo-100' : 'border-gray-200 hover:border-indigo-500 hover:bg-indigo-50'"
                  >
                    <svg role="img" class="icon icon-pos-print w-8 h-8 mb-2" :class="form.defaultView === 'Print' ? 'text-indigo-600' : 'text-gray-600'">
                      <use href="/img/icon-sprite.svg#pos-print"></use>
                    </svg>
                    <span class="text-xs md:text-sm font-medium text-center">Print Display</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Description - Only show for Product List and Product List with Cart -->
            <div v-if="form.defaultView === 'Static' || form.defaultView === 'Cart'">
              <label for="description" class="block text-sm font-medium text-gray-700">
                Description
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              ></textarea>
            </div>

            <!-- Products Editor - Show for: Static, Cart, Print, or Light (Keypad) with showItems enabled -->
            <div v-if="shouldShowProductsEditor" class="mt-6">
              <div class="border-b border-gray-200 mb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Products</h3>
                <!-- Tabs for Editor/Code view -->
                <div class="flex space-x-4">
                  <button
                    type="button"
                    @click="productsViewMode = 'editor'"
                    :class="productsViewMode === 'editor' 
                      ? 'border-b-2 border-indigo-600 text-indigo-600 font-medium' 
                      : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm"
                  >
                    Editor
                  </button>
                  <button
                    type="button"
                    @click="productsViewMode = 'code'"
                    :class="productsViewMode === 'code' 
                      ? 'border-b-2 border-indigo-600 text-indigo-600 font-medium' 
                      : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm"
                  >
                    Code
                  </button>
                </div>
              </div>

              <!-- Editor View - Compact Table List -->
              <div v-if="productsViewMode === 'editor'">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                        
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                        Title
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                         Price 
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                         Tax 
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                         Status 
                        </th>
                        
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Actions
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr
                        v-for="(product, index) in products"
                        :key="product.id || index"
                        :draggable="true"
                        :data-index="index"
                        :class="[
                          'hover:bg-gray-50 cursor-pointer transition-colors',
                          draggedIndex === index ? 'opacity-50' : '',
                          dragOverIndex === index ? 'bg-blue-50' : ''
                        ]"
                        @dragstart="handleDragStart($event, index)"
                        @dragover.prevent="handleDragOver($event, index)"
                        @dragenter.prevent="dragOverIndex = index"
                        @dragleave="dragOverIndex = null"
                        @drop="handleDrop($event, index)"
                        @dragend="handleDragEnd"
                        @click="editProduct(index)"
                      >
                        <td class="px-4 py-3 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 mr-3 cursor-move" @click.stop @mousedown.stop>
                              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                              </svg>
                            </div>
                          </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div v-if="product.image" class="flex-shrink-0 h-10 w-10 mr-3">
                              <img :src="product.image" :alt="product.title" class="h-10 w-10 rounded object-cover" @error="product.image = ''" />
                            </div>
                            <div>
                              <div class="text-sm font-medium text-gray-900">{{ product.title || 'Untitled' }}</div>
                              <div v-if="product.id" class="text-xs text-gray-500">{{ product.id }}</div>
                            </div>                          
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                          <span v-if="product.priceType === 'Free'">Free</span>
                          <span v-else-if="product.priceType === 'Topup'">Any amount</span>
                          <span v-else-if="product.priceType === 'Minimum'">Min. {{ product.price || 0 }} {{ form.currency || store?.default_currency || 'EUR' }}</span>
                          <span v-else>{{ product.price || 0 }} {{ form.currency || store?.default_currency || 'EUR' }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                          {{ product.taxRate !== null && product.taxRate !== undefined ? product.taxRate + '%' : '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                          <span
                            :class="product.disabled ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                          >
                            {{ product.disabled ? 'Disabled' : 'Enabled' }}
                          </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium" @click.stop>
                          <button
                            type="button"
                            @click.stop="editProduct(index)"
                            class="text-indigo-600 hover:text-indigo-900 mr-4"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            @click.stop="removeProduct(index)"
                            class="text-red-600 hover:text-red-900"
                          >
                            Remove
                          </button>
                        </td>
                      </tr>
                      <tr v-if="products.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                          No products yet. Click "Add Item" to create one.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="mt-4">
                  <button
                    type="button"
                    @click="addProduct"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Item
                  </button>
                </div>
              </div>

              <!-- Code View -->
              <div v-if="productsViewMode === 'code'">
                <label for="products-json" class="block text-sm font-medium text-gray-700 mb-2">
                  Template JSON
                </label>
                <textarea
                  id="products-json"
                  v-model="productsJson"
                  rows="15"
                  class="font-mono text-sm block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  @blur="parseProductsJson"
                ></textarea>
                <p class="mt-2 text-xs text-gray-500">
                  Edit the JSON template directly. Changes are saved when you switch back to Editor view.
                </p>
              </div>
            </div>

            <!-- Keypad Section - Only show for Keypad view (full width) -->
            <div v-if="form.defaultView === 'Light'" class="mt-6">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Keypad</h3>
              <div class="flex items-center">
                <input
                  id="showItems"
                  v-model="form.showItems"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="showItems" class="ml-2 block text-sm text-gray-900">
                  Display item selection for keypad
                </label>
              </div>
            </div>

            <!-- Settings Grid: Currency, Checkout, Tips, Taxes, Discounts, Custom Payments -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
              <!-- Currency -->
               
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Currency</h3>
                <label for="currency" class="block text-xs font-medium text-gray-700 mb-1">
                  PoS Currency
                </label>
                <input
                  id="currency"
                  v-model="form.currency"
                  type="text"
                  list="currency-selection-suggestion"
                  placeholder="Select or type currency (e.g., USD, BTC, EUR)"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
                <p class="mt-1 text-xs text-gray-500">Uses the store's default currency ({{ store?.default_currency || 'EUR' }}) if empty</p>
              </div>

              <!-- Taxes Section -->
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Taxes</h3>
                <div>
                  <label for="defaultTaxRate" class="block text-xs font-medium text-gray-700 mb-1">
                    Default Tax Rate
                  </label>
                  <div class="mt-1 flex rounded-md shadow-sm">
                    <input
                      id="defaultTaxRate"
                      v-model.number="form.defaultTaxRate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                      placeholder="0.00"
                    />
                    <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                      %
                    </span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">This rate can also be overridden per item.</p>
                </div>
              </div>

              <!-- Checkout Section -->
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Checkout</h3>
                <div>
                  <label for="requestCustomerData" class="block text-xs font-medium text-gray-700 mb-1">
                    Request customer data on checkout
                  </label>
                  <select
                    id="requestCustomerData"
                    v-model="form.requestCustomerData"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  >
                    <option value="">Do not request any information</option>
                    <option value="email">Email</option>
                    <option value="name">Name</option>
                    <option value="email_name">Email and Name</option>
                  </select>
                </div>
              </div>

              <!-- Tips Section -->
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Tips</h3>
                <div class="flex items-center mb-2">
                  <input
                    id="enableTips"
                    v-model="form.enableTips"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="enableTips" class="ml-2 block text-sm text-gray-900">
                    Enable tips
                  </label>
                </div>

                <div v-if="form.enableTips">
                  <label for="tipsMessage" class="block text-xs font-medium text-gray-700 mb-1">
                    Text to display in the tip input <span class="text-red-500">*</span>
                  </label>
                  <input
                    id="tipsMessage"
                    v-model="form.tipsMessage"
                    type="text"
                    required
                    placeholder="Do you want to leave a tip?"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>              

              <!-- Discounts Section -->
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Discounts</h3>
                <div class="flex items-center mb-1">
                  <input
                    id="showDiscount"
                    v-model="form.showDiscount"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="showDiscount" class="ml-2 block text-sm text-gray-900">
                    User can input discount in %
                  </label>
                </div>
                <p class="text-xs text-gray-500">Not recommended for customer self-checkout.</p>
              </div>

              <!-- Custom Payments Section -->
              <div>
                <h3 class="text-sm font-medium text-gray-900 mb-2">Custom Payments</h3>
                <div class="flex items-center mb-2">
                  <input
                    id="showCustomAmount"
                    v-model="form.showCustomAmount"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="showCustomAmount" class="ml-2 block text-sm text-gray-900">
                    User can input custom amount
                  </label>
                </div>

                <div v-if="form.showCustomAmount">
                  <label for="customAmountPayButtonText" class="block text-xs font-medium text-gray-700 mb-1">
                    Text to display on buttons <span class="text-red-500">*</span>
                  </label>
                  <input
                    id="customAmountPayButtonText"
                    v-model="form.customAmountPayButtonText"
                    type="text"
                    required
                    placeholder="Pay"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
            </div>            

            <!-- Cart Section - Only show for Product list with cart (full width) -->
            <div v-if="form.defaultView === 'Cart'" class="mt-6">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Cart</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                  <input
                    id="showSearch"
                    v-model="form.showSearch"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="showSearch" class="ml-2 block text-sm text-gray-900">
                    Display the search bar
                  </label>
                </div>

                <div class="flex items-center">
                  <input
                    id="showCategories"
                    v-model="form.showCategories"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="showCategories" class="ml-2 block text-sm text-gray-900">
                    Display the category list
                  </label>
                </div>
              </div>
            </div>

            <!-- Button Text - Only show for Product List views -->
            <div v-if="form.defaultView === 'Static' || form.defaultView === 'Cart'" class="mt-6">
              <label for="fixedAmountPayButtonText" class="block text-sm font-medium text-gray-700">
                Text to display on each button for items with a specific price <span class="text-red-500">*</span>
              </label>
              <input
                id="fixedAmountPayButtonText"
                v-model="form.fixedAmountPayButtonText"
                type="text"
                required
                placeholder="Buy for {0}"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Additional Options - Accordion -->
            <div class="mt-8 space-y-4">
              <h2 class="text-2xl font-bold text-gray-900">Additional Options</h2>
              
              <!-- HTML Headers -->
              <div class="border border-gray-200 rounded-lg">
                <button
                  type="button"
                  @click="accordionSections.htmlHeaders = !accordionSections.htmlHeaders"
                  class="w-full px-4 py-3 flex items-center justify-between text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset rounded-t-lg"
                  :class="{ 'rounded-b-lg': !accordionSections.htmlHeaders }"
                >
                  <span class="font-medium text-gray-900">HTML Headers</span>
                  <svg
                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    :class="{ 'transform rotate-180': accordionSections.htmlHeaders }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-show="accordionSections.htmlHeaders" class="px-4 py-4 space-y-4 border-t border-gray-200">
                  <div>
                    <label for="htmlLang" class="block text-sm font-medium text-gray-700">
                      HTML Lang
                    </label>
                    <input
                      id="htmlLang"
                      v-model="form.htmlLang"
                      type="text"
                      placeholder="en"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                  </div>
                  <div>
                    <label for="htmlMetaTags" class="block text-sm font-medium text-gray-700">
                      HTML Meta Tags
                    </label>
                    <textarea
                      id="htmlMetaTags"
                      v-model="form.htmlMetaTags"
                      rows="4"
                      placeholder='<meta name="description" content="...">&#10;<meta name="keywords" content="...">&#10;<meta name="author" content="...">'
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm font-mono text-xs"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">Please insert valid HTML here. Only meta tags accepted.</p>
                  </div>
                </div>
              </div>

              <!-- Redirects -->
              <div class="border border-gray-200 rounded-lg">
                <button
                  type="button"
                  @click="accordionSections.redirects = !accordionSections.redirects"
                  class="w-full px-4 py-3 flex items-center justify-between text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset rounded-t-lg"
                  :class="{ 'rounded-b-lg': !accordionSections.redirects }"
                >
                  <span class="font-medium text-gray-900">Redirects</span>
                  <svg
                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    :class="{ 'transform rotate-180': accordionSections.redirects }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-show="accordionSections.redirects" class="px-4 py-4 space-y-4 border-t border-gray-200">
                  <div>
                    <label for="redirectUrl" class="block text-sm font-medium text-gray-700">
                      Redirect URL
                    </label>
                    <input
                      id="redirectUrl"
                      v-model="form.redirectUrl"
                      type="url"
                      placeholder="https://example.com/thankyou"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                  </div>
                  <div>
                    <label for="redirectAutomatically" class="block text-sm font-medium text-gray-700">
                      Redirect invoice to redirect url automatically after paid
                    </label>
                    <select
                      id="redirectAutomatically"
                      v-model="form.redirectAutomatically"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                      <option value="">Use Store Settings</option>
                      <option value="true">Yes</option>
                      <option value="false">No</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Notification URL Callbacks -->
              <div class="border border-gray-200 rounded-lg">
                <button
                  type="button"
                  @click="accordionSections.notifications = !accordionSections.notifications"
                  class="w-full px-4 py-3 flex items-center justify-between text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset rounded-t-lg"
                  :class="{ 'rounded-b-lg': !accordionSections.notifications }"
                >
                  <span class="font-medium text-gray-900">Notification URL Callbacks</span>
                  <svg
                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    :class="{ 'transform rotate-180': accordionSections.notifications }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-show="accordionSections.notifications" class="px-4 py-4 space-y-4 border-t border-gray-200">
                  <div>
                    <label for="notificationUrl" class="block text-sm font-medium text-gray-700">
                      Callback Notification URL
                    </label>
                    <input
                      id="notificationUrl"
                      v-model="form.notificationUrl"
                      type="url"
                      placeholder="https://example.com/callbacks"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                    <p class="mt-2 text-sm text-gray-600">
                      A POST callback will be sent to the specified notificationUrl (for on-chain transactions when there are sufficient confirmations):
                    </p>
                    <div class="mt-3 bg-gray-900 text-gray-100 p-4 rounded-md overflow-x-auto">
                      <pre class="text-xs font-mono"><code>{
  "id": "invoiceId",
  "url": "https://btcpay.example.com/invoices/{invoiceId}",
  "status": "paid",
  "price": "10.00",
  "currency": "EUR",
  ...
}</code></pre>
                    </div>
                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                      <p class="text-sm font-semibold text-red-800 mb-2">⚠️ Important Security Warning:</p>
                      <p class="text-xs text-red-700">
                        Never trust anything but id, ignore the other fields completely, an attacker can spoof those, they are present only for backward compatibility reason:
                      </p>
                      <ul class="mt-2 text-xs text-red-700 list-disc list-inside space-y-1">
                        <li>Send a GET request to <code class="bg-red-100 px-1 rounded">https://btcpay.example.com/invoices/{invoiceId}</code> with Content-Type: application/json; Authorization: Basic YourLegacyAPIkey</li>
                        <li>Legacy API key can be created with Access Tokens in Store settings</li>
                        <li>Verify that the order Id is from your backend, that the price is correct and that status is settled</li>
                        <li>You can then ship your order</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Embed Codes -->
              <div class="border border-gray-200 rounded-lg">
                <button
                  type="button"
                  @click="accordionSections.embed = !accordionSections.embed"
                  class="w-full px-4 py-3 flex items-center justify-between text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset rounded-t-lg"
                  :class="{ 'rounded-b-lg': !accordionSections.embed }"
                >
                  <span class="font-medium text-gray-900">Embed Codes</span>
                  <svg
                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    :class="{ 'transform rotate-180': accordionSections.embed }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-show="accordionSections.embed" class="px-4 py-4 space-y-6 border-t border-gray-200">
                  <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Embed a payment button linking to POS item</h4>
                    <p class="text-xs text-gray-600 mb-2">You can host point of sale buttons in an external website with the following code.</p>
                    <p class="text-xs text-gray-600 mb-3">For a specific item of your template</p>
                    <div class="bg-gray-900 text-gray-100 p-4 rounded-md overflow-x-auto">
                      <pre class="text-xs font-mono"><code>{{ embedFormCode }}</code></pre>
                    </div>
                  </div>
                  <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Embed Point of Sale via iframe</h4>
                    <p class="text-xs text-gray-600 mb-3">You can embed this POS via an iframe.</p>
                    <div class="bg-gray-900 text-gray-100 p-4 rounded-md overflow-x-auto">
                      <pre class="text-xs font-mono"><code>{{ embedIframeCode }}</code></pre>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error/Success Messages -->
            <div v-if="error" class="rounded-md bg-red-50 p-4">
              <div class="text-sm text-red-800">{{ error }}</div>
            </div>

            <div v-if="success" class="rounded-md bg-green-50 p-4">
              <div class="text-sm text-green-800">{{ success }}</div>
            </div>

            <!-- Submit and Delete Buttons -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
              <button
                type="button"
                @click="showDeleteModal = true"
                :disabled="saving || deleting"
                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
              >
                Delete App
              </button>
              
            </div>
          </form>
          </div>
          </div>
        </template>

        <!-- Delete Confirmation Modal -->
        <div
          v-if="showDeleteModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          @click.self="showDeleteModal = false"
        >
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 mb-4">
                Delete {{ app.name }}?
              </h3>
              <p class="text-sm text-gray-600 mb-4">
                This action cannot be undone. This will permanently delete the app and all its data.
              </p>
              <p class="text-sm font-medium text-gray-700 mb-2">
                Please type <span class="font-mono text-red-600">DELETE</span> to confirm:
              </p>
              <input
                v-model="deleteConfirmText"
                type="text"
                placeholder="Type DELETE"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
                :class="{ 'border-red-500': deleteConfirmText && deleteConfirmText !== 'DELETE' }"
              />
              <div v-if="deleteError" class="mt-2 text-sm text-red-600">
                {{ deleteError }}
              </div>
              <div class="flex justify-end space-x-3 mt-6">
                <button
                  type="button"
                  @click="showDeleteModal = false; deleteConfirmText = ''; deleteError = '';"
                  :disabled="deleting"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  @click="handleDelete"
                  :disabled="deleting || deleteConfirmText !== 'DELETE'"
                  class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                >
                  {{ deleting ? 'Deleting...' : 'Delete App' }}
                </button>
              </div>
            </div>
          </div>
        </div>

          <!-- Crowdfund App Template -->
          <template v-else-if="app.app_type === 'Crowdfund'">
            <CrowdfundForm ref="crowdfundFormRef" :app="app" :store="store" />
          </template>

          <template v-else-if="app">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
              <div class="bg-white shadow rounded-lg p-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ app.name }}</h1>
                <p class="text-sm text-gray-500">Type: {{ app.app_type }}</p>
                <p class="mt-4 text-gray-600">Settings for this app type are not yet implemented.</p>
              </div>
            </div>
          </template>
        </div>
      </div>

    <!-- Product Edit Drawer -->
    <ProductEditDrawer
      :is-open="showProductDrawer"
      :product="currentProduct"
      :currency="form.currency || store?.default_currency || 'EUR'"
      :store-id="storeId.value"
      @close="showProductDrawer = false"
      @save="handleProductSave"
    />
  </div>

  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">App not found</p>
  </div>
  
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import ProductEditDrawer from '../../components/stores/ProductEditDrawer.vue';
import CrowdfundForm from './CrowdfundForm.vue';
import { useStoresStore } from '../../store/stores';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import { currencies } from '../../data/currencies';

const route = useRoute();
const router = useRouter();
const appsStore = useAppsStore();
const storesStore = useStoresStore();

const storeId = computed(() => route.params.id as string);
const appId = computed(() => route.params.appId as string);
const loading = ref(false);
const saving = ref(false);
const store = ref<any>(null);
const app = ref<any>(null);
const error = ref('');
const success = ref('');
const showDeleteModal = ref(false);
const deleteConfirmText = ref('');
const deleteError = ref('');
const deleting = ref(false);

const allApps = computed(() => appsStore.apps);

// Computed property to determine if products editor should be shown
const shouldShowProductsEditor = computed(() => {
  // Always show for Static, Cart, and Print
  if (form.value.defaultView === 'Static' || form.value.defaultView === 'Cart' || form.value.defaultView === 'Print') {
    return true;
  }
  // For Light (Keypad), show only if "Display item selection for keypad" is enabled
  if (form.value.defaultView === 'Light' && form.value.showItems) {
    return true;
  }
  return false;
});

const form = ref({
  appName: '',
  title: '',
  description: '',
  defaultView: 'Static',
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

// Accordion state for Additional Options
const accordionSections = ref({
  htmlHeaders: false,
  redirects: false,
  notifications: false,
  embed: false,
});

// Computed property for BTCPay app URL (for embed codes only - not saved to BTCPay)
const btcpayAppUrl = computed(() => {
  if (!app.value) return '';
  const baseUrl = import.meta.env.VITE_BTCPAY_BASE_URL || 'https://pay.dvadsatjeden.org';
  // Try multiple ways to get BTCPay app ID
  let appId = app.value.btcpay_app_id || 
              (app.value.config && app.value.config.id) ||
              (app.value.config && app.value.config.appId);
  
  // If we have btcpay_app_url, extract ID from it
  if (!appId && app.value.btcpay_app_url) {
    const urlParts = app.value.btcpay_app_url.split('/');
    appId = urlParts[urlParts.length - 1] || urlParts[urlParts.length - 2];
  }
  
  if (!appId) {
    console.warn('BTCPay app ID not found for embed codes', {
      app: app.value,
      has_btcpay_app_id: !!app.value.btcpay_app_id,
      has_config: !!app.value.config,
      config_keys: app.value.config ? Object.keys(app.value.config) : [],
      has_btcpay_app_url: !!app.value.btcpay_app_url,
    });
    return '';
  }
  return `${baseUrl}/apps/${appId}/pos`;
});

// Computed properties for embed codes (dynamically generated, not saved to BTCPay)
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

// Products editor state
const productsViewMode = ref<'editor' | 'code'>('editor');
const products = ref<any[]>([]);
const productsJson = ref('[]');
const showProductDrawer = ref(false);
const editingProductIndex = ref<number | null>(null);

// Drag & drop state
const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

// Helper function to generate product ID from title
function generateProductId(title: string): string {
  if (!title) return '';
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// Add a new product
function addProduct() {
  editingProductIndex.value = null;
  showProductDrawer.value = true;
}

// Edit a product
function editProduct(index: number) {
  editingProductIndex.value = index;
  showProductDrawer.value = true;
}

// Handle product save from drawer
function handleProductSave(product: any) {
  if (editingProductIndex.value !== null) {
    // Update existing product
    products.value[editingProductIndex.value] = product;
  } else {
    // Add new product
    products.value.push(product);
  }
  editingProductIndex.value = null;
}

// Current product being edited (for drawer)
const currentProduct = computed(() => {
  if (editingProductIndex.value !== null && products.value[editingProductIndex.value]) {
    return products.value[editingProductIndex.value];
  }
  return null;
});

// Drag & drop handlers
function handleDragStart(event: DragEvent, index: number) {
  draggedIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', String(index));
  }
  // Add visual feedback
  if (event.target instanceof HTMLElement) {
    event.target.style.opacity = '0.5';
  }
}

function handleDragOver(event: DragEvent, index: number) {
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move';
  }
  dragOverIndex.value = index;
}

function handleDrop(event: DragEvent, dropIndex: number) {
  event.preventDefault();
  
  if (draggedIndex.value === null || draggedIndex.value === dropIndex) {
    dragOverIndex.value = null;
    return;
  }

  // Move item in array
  const draggedItem = products.value[draggedIndex.value];
  products.value.splice(draggedIndex.value, 1);
  products.value.splice(dropIndex, 0, draggedItem);

  dragOverIndex.value = null;
  draggedIndex.value = null;
}

function handleDragEnd(event: DragEvent) {
  draggedIndex.value = null;
  dragOverIndex.value = null;
  // Reset opacity
  if (event.target instanceof HTMLElement) {
    event.target.style.opacity = '1';
  }
}

// Remove a product
function removeProduct(index: number) {
  products.value.splice(index, 1);
}

// Parse products from JSON
function parseProductsJson() {
  try {
    const parsed = JSON.parse(productsJson.value);
    if (Array.isArray(parsed)) {
      products.value = parsed.map((p: any) => {
        // Handle inventory - only convert to number if it exists and is valid
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
      error.value = ''; // Clear error on successful parse
    }
  } catch (e) {
    console.error('Failed to parse products JSON:', e);
    error.value = 'Invalid JSON format for products';
  }
}

// Convert products to JSON
function productsToJson(): string {
  return JSON.stringify(
    products.value.map(p => {
      // Handle inventory:
      // - 0 = out of stock (keep as 0)
      // - null/undefined/empty = unlimited (keep as null)
      let inventory: number | null = null;
      if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
        const invNum = Number(p.inventory);
        if (!isNaN(invNum) && invNum >= 0) {
          inventory = invNum; // Keep 0 as 0 (out of stock)
        }
      }
      // If inventory is null/undefined/empty string, leave it as null (unlimited)
      
      return {
        id: p.id || generateProductId(p.title),
        title: p.title,
        priceType: p.priceType,
        price: p.priceType !== 'Free' && p.priceType !== 'Topup' ? String(p.price || 0) : null,
        taxRate: p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== '' ? String(p.taxRate) : null,
        image: p.image || null,
        description: p.description || null,
        categories: p.categories ? String(p.categories).split(',').map((c: string) => c.trim()).filter((c: string) => c) : null,
        inventory: inventory,
        buyButtonText: p.buyButtonText || null,
        disabled: p.disabled || false,
      };
    }),
    null,
    2
  );
}

// Watch for changes in products to update JSON
watch(products, () => {
  if (productsViewMode.value === 'editor') {
    productsJson.value = productsToJson();
  }
}, { deep: true });

// Watch for view mode changes
watch(productsViewMode, (newMode) => {
  if (newMode === 'editor') {
    // When switching to editor, update JSON first
    productsJson.value = productsToJson();
  } else if (newMode === 'code') {
    // When switching to code, parse current products to JSON
    productsJson.value = productsToJson();
  }
});

async function loadApp() {
  loading.value = true;
  error.value = '';
  try {
    const currentStoreId = storeId.value;
    const currentAppId = appId.value;
    
    // Load store and apps first
    if (!store.value || store.value.id !== currentStoreId) {
      store.value = await storesStore.fetchStore(currentStoreId);
    }
    await appsStore.fetchApps(currentStoreId);
    
    // Then load the specific app
    app.value = await appsStore.fetchApp(currentStoreId, currentAppId);
    
    // Populate form with app data
    if (app.value) {
      form.value.appName = app.value.name || '';
      
      // Load config from app.config (BTCPay API response)
      if (app.value.config) {
        const config = app.value.config;
        form.value.title = config.title || '';
        form.value.description = config.description || '';
        form.value.defaultView = config.defaultView || 'Light';
        // Use app currency if set, otherwise use store's default currency
        form.value.currency = config.currency || (store.value?.default_currency || 'EUR');
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

        // Load products from template or items
        // BTCPay API may return products in 'template' or 'items' field
        // 'items' seems to be used in GET responses, 'template' in POST/PUT requests
        let templateArray: any[] = [];
        const productsSource = config.items || config.template;
        
        if (productsSource) {
          if (Array.isArray(productsSource)) {
            // Already an array
            templateArray = productsSource;
          } else if (typeof productsSource === 'string') {
            // It's a JSON string, parse it
            try {
              const parsed = JSON.parse(productsSource);
              templateArray = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.template) ? parsed.template : (Array.isArray(parsed.items) ? parsed.items : [parsed]));
            } catch (e) {
              console.warn('Failed to parse products JSON string', e, productsSource);
              templateArray = [];
            }
          }
        }
        
        if (templateArray && templateArray.length > 0) {
          products.value = templateArray.map((p: any) => {
            // Handle inventory:
            // - 0 = out of stock (keep as 0)
            // - null/undefined/empty = unlimited (keep as null)
            let inventory: number | null = null;
            if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
              const invNum = Number(p.inventory);
              if (!isNaN(invNum) && invNum >= 0) {
                inventory = invNum; // Keep 0 as 0 (out of stock)
              }
            }
            // If inventory is null/undefined/empty string, leave it as null (unlimited)
            
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
          productsJson.value = productsToJson();
        } else {
          products.value = [];
          productsJson.value = '[]';
        }
      } else {
        // If no config, use store's default currency
        form.value.currency = store.value?.default_currency || 'EUR';
        products.value = [];
        productsJson.value = '[]';
      }
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load app';
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  saving.value = true;
  error.value = '';
  success.value = '';
  
  try {
    // If switching from code view, parse JSON first
    if (productsViewMode.value === 'code') {
      parseProductsJson();
    }

      // Prepare template array from products
      const template = products.value
        .filter(p => p.title) // Only include products with titles
        .map(p => {
          // Handle inventory:
          // - 0 = out of stock (should be kept as 0)
          // - null/undefined/empty = unlimited (should be null, not sent to BTCPay)
          let inventory: number | null = null;
          if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
            const invNum = Number(p.inventory);
            if (!isNaN(invNum) && invNum >= 0) {
              inventory = invNum; // Keep 0 as 0 (out of stock), other numbers as-is
            }
          }
          // If inventory is null/undefined/empty string, leave it as null (unlimited)
          
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

    // Build config object
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

    // Include description for Static/Cart views
    if (form.value.defaultView === 'Static' || form.value.defaultView === 'Cart') {
      config.description = form.value.description || null;
    }

    // Include template for views that show products editor
    if (shouldShowProductsEditor.value) {
      config.template = template;
    }

    // Update app via API
    await appsStore.updateApp(storeId.value, appId.value, {
      name: form.value.appName,
      config,
    });
    
    success.value = 'Settings saved successfully';
    
    // Reload app to get updated data from server
    // Wait a bit to ensure BTCPay has processed the update
    await new Promise(resolve => setTimeout(resolve, 500));
    await loadApp();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save settings';
  } finally {
    saving.value = false;
  }
}

function handleCreateApp() {
  // Handle create app - not needed here but required by StoreSidebar
}

function handleShowSettings() {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
}

function handleShowSection(section: string) {
  router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
}

async function handleDelete() {
  if (deleteConfirmText.value !== 'DELETE') {
    deleteError.value = 'Please type DELETE to confirm';
    return;
  }

  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(storeId.value, appId.value);
    
    // Redirect to store page after successful deletion
    router.push({ name: 'stores-show', params: { id: storeId.value } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  loadApp();
});

// Reload if route changes
watch([() => route.params.id, () => route.params.appId], ([newStoreId, newAppId], [oldStoreId, oldAppId]) => {
  if (newAppId && (newAppId !== oldAppId || newStoreId !== oldStoreId)) {
    // Reset app state when switching
    app.value = null;
    loadApp();
  }
}, { immediate: false });
</script>

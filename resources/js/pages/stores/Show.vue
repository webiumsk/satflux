<template>
  <div v-if="loading && !store" class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Loading store...</p>
  </div>

  <div v-else-if="store" class="flex h-screen bg-gray-100 overflow-hidden">
    <!-- Sidebar -->
    <StoreSidebar
      :store="store"
      :apps="allApps"
      @create-app="handleCreateApp"
      @show-settings="handleShowSettings"
      @show-section="handleShowSection"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <div class="flex-1 overflow-y-auto">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Settings View -->
        <div v-if="showSettings" class="max-w-4xl">
          <div v-if="settingsLoading && !settings" class="text-center py-12">
            <p class="text-gray-500">Loading settings...</p>
          </div>

          <div v-else-if="settings" class="bg-white shadow rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
              <h1 class="text-3xl font-bold text-gray-900">Store Settings</h1>
            </div>

            <div class="px-6 py-5 space-y-8">
              <!-- Editable Fields -->
              <div>
                <h2 class="text-lg font-medium text-gray-900 mb-4">Store Information</h2>
                <form @submit.prevent="handleSettingsSubmit" class="space-y-6">
                  <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Store Name</label>
                    <input
                      id="name"
                      v-model="settingsForm.name"
                      type="text"
                      required
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                  </div>

                  <div>
                    <label for="default_currency" class="block text-sm font-medium text-gray-700">Default Currency</label>
                    <input
                      id="default_currency"
                      v-model="settingsForm.default_currency"
                      type="text"
                      list="currency-selection-suggestion"
                      required
                      placeholder="Select or type currency (e.g., USD, BTC, EUR)"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                    <datalist id="currency-selection-suggestion">
                      <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                        {{ currency.code }} - {{ currency.name }}
                      </option>
                    </datalist>
                  </div>

                  <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
                    <select
                      id="timezone"
                      v-model="settingsForm.timezone"
                      required
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                      <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                  </div>

                  <div>
                    <label for="preferred_exchange" class="block text-sm font-medium text-gray-700">
                      Preferred Price Source
                    </label>
                    <select
                      id="preferred_exchange"
                      v-model="settingsForm.preferred_exchange"
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                      <option v-for="exchange in exchanges" :key="exchange.value" :value="exchange.value">
                        {{ exchange.label }}
                      </option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500">The recommended price source gets chosen based on the default currency.</p>
                  </div>

                  <div v-if="settingsError" class="rounded-md bg-red-50 p-4">
                    <div class="text-sm text-red-800">{{ settingsError }}</div>
                  </div>

                  <div v-if="settingsSuccess" class="rounded-md bg-green-50 p-4">
                    <div class="text-sm text-green-800">{{ settingsSuccess }}</div>
                  </div>

                  <div class="flex justify-end">
                    <button
                      type="submit"
                      :disabled="settingsSaving"
                      class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                      {{ settingsSaving ? 'Saving...' : 'Save Changes' }}
                    </button>
                  </div>
                </form>
              </div>

              <!-- Logo Management -->
              <div class="border-t border-gray-200 pt-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Store Logo</h2>
                <div class="space-y-4">
                  <div v-if="storeLogoUrl" class="flex items-center space-x-4">
                    <img :src="storeLogoUrl" alt="Store logo" class="h-20 w-20 object-contain border border-gray-300 rounded" />
                    <div>
                      <p class="text-sm text-gray-600">Current logo</p>
                      <button
                        type="button"
                        @click="handleDeleteLogo"
                        :disabled="deletingLogo"
                        class="mt-2 text-sm text-red-600 hover:text-red-700 disabled:opacity-50"
                      >
                        {{ deletingLogo ? 'Deleting...' : 'Delete Logo' }}
                      </button>
                    </div>
                  </div>
                  <div v-else>
                    <p class="text-sm text-gray-600 mb-2">No logo uploaded</p>
                  </div>
                  <div>
                    <label for="logo-upload" class="block text-sm font-medium text-gray-700 mb-2">
                      {{ storeLogoUrl ? 'Upload New Logo' : 'Upload Logo' }}
                    </label>
                    <input
                      id="logo-upload"
                      ref="logoInputRef"
                      type="file"
                      accept="image/*"
                      @change="handleLogoUpload"
                      class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p class="mt-1 text-sm text-gray-500">Upload an image file (PNG, JPG, etc.)</p>
                  </div>
                  <div v-if="logoError" class="rounded-md bg-red-50 p-3">
                    <div class="text-sm text-red-800">{{ logoError }}</div>
                  </div>
                  <div v-if="logoSuccess" class="rounded-md bg-green-50 p-3">
                    <div class="text-sm text-green-800">{{ logoSuccess }}</div>
                  </div>
                </div>
              </div>

              <!-- Delete Store -->
              <div class="border-t border-gray-200 pt-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Additional Settings</h2>
                <div class="space-y-4">
                  <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Delete Store</h3>
                    <p class="text-sm text-gray-600 mb-4">
                      This will permanently delete the store and all its data. This action cannot be undone.
                    </p>
                    <button
                      type="button"
                      @click="showDeleteStoreModal = true"
                      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    >
                      Delete Store
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Invoices View -->
        <div v-else-if="showInvoices" class="max-w-7xl">
          <div v-if="invoicesLoading && invoices.length === 0" class="text-center py-12">
            <p class="text-gray-500">Loading invoices...</p>
          </div>

          <div v-else class="bg-white shadow rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
              <h1 class="text-3xl font-bold text-gray-900">Invoices</h1>
            </div>

            <div class="px-6 py-5">
              <!-- Filters -->
              <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Status Filter -->
                <div>
                  <label for="invoice-status-filter" class="block text-sm font-medium text-gray-700 mb-2">
                    Status
                  </label>
                  <select
                    id="invoice-status-filter"
                    v-model="invoiceFilters.status"
                    @change="fetchInvoices"
                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  >
                    <option value="">All Statuses</option>
                    <option value="New">New</option>
                    <option value="Paid">Paid (Partially)</option>
                    <option value="Settled">Settled</option>
                    <option value="Invalid">Invalid</option>
                    <option value="Expired">Expired</option>
                  </select>
                </div>

                <!-- Date From -->
                <div>
                  <label for="invoice-date-from" class="block text-sm font-medium text-gray-700 mb-2">
                    From Date
                  </label>
                  <input
                    id="invoice-date-from"
                    v-model="invoiceFilters.date_from"
                    type="date"
                    @change="fetchInvoices"
                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>

                <!-- Date To -->
                <div>
                  <label for="invoice-date-to" class="block text-sm font-medium text-gray-700 mb-2">
                    To Date
                  </label>
                  <input
                    id="invoice-date-to"
                    v-model="invoiceFilters.date_to"
                    type="date"
                    @change="fetchInvoices"
                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>

              <!-- Filter Actions -->
              <div class="mb-4 flex items-center justify-between">
                <div v-if="hasInvoiceFilters">
                  <button
                    @click="clearInvoiceFilters"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                  >
                    Clear Filters
                  </button>
                </div>
                <button
                  @click="handleExportInvoices"
                  :disabled="exportingInvoices"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="!exportingInvoices" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ exportingInvoices ? 'Exporting...' : 'Export to CSV' }}
                </button>
              </div>

              <div v-if="invoicesError" class="rounded-md bg-red-50 p-4 mb-4">
                <div class="text-sm text-red-800">{{ invoicesError }}</div>
              </div>

              <div v-if="invoiceExportSuccess" class="rounded-md bg-green-50 p-4 mb-4">
                <div class="text-sm text-green-800">{{ invoiceExportSuccess }}</div>
              </div>

              <div v-if="invoiceExportError" class="rounded-md bg-red-50 p-4 mb-4">
                <div class="text-sm text-red-800">{{ invoiceExportError }}</div>
              </div>

              <div v-if="invoices.length === 0 && !invoicesLoading" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first invoice.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice ID</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ invoice.invoice_id || invoice.id }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(invoice.created_time || invoice.created_at) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                          :class="getStatusClass(invoice.status)">
                          {{ invoice.status }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatAmount(invoice.amount, invoice.currency) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Exports View -->
        <div v-else-if="showExports" class="max-w-7xl">
          <div v-if="exportsLoading && exports.length === 0" class="text-center py-12">
            <p class="text-gray-500">Loading exports...</p>
          </div>

          <div v-else class="bg-white shadow rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
              <h1 class="text-3xl font-bold text-gray-900">Exports</h1>
            </div>

            <div class="px-6 py-5">
              <div v-if="exportsError" class="rounded-md bg-red-50 p-4 mb-4">
                <div class="text-sm text-red-800">{{ exportsError }}</div>
              </div>

              <div v-if="exports.length === 0 && !exportsLoading" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No exports</h3>
                <p class="mt-1 text-sm text-gray-500">Exports will appear here when created.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Export ID</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Format</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="exportItem in exports" :key="exportItem.id" class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ exportItem.id }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ exportItem.created_at ? formatDate(exportItem.created_at) : '-' }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                          :class="getStatusClass(exportItem.status)">
                          {{ exportItem.status }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ exportItem.format || 'CSV' }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button
                          v-if="exportItem.status === 'finished'"
                          @click="handleDownloadExport(exportItem)"
                          :disabled="downloadingExportId === exportItem.id"
                          class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ downloadingExportId === exportItem.id ? 'Downloading...' : 'Download' }}
                        </button>
                        <button
                          v-else-if="exportItem.status === 'failed'"
                          @click="handleRetryExport(exportItem)"
                          :disabled="retryingExportId === exportItem.id"
                          class="text-orange-600 hover:text-orange-900 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ retryingExportId === exportItem.id ? 'Retrying...' : 'Retry' }}
                        </button>
                        <span v-else-if="exportItem.status === 'pending' || exportItem.status === 'running'" class="text-gray-400">
                          {{ exportItem.status === 'running' ? 'Processing...' : 'Waiting...' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Dashboard View -->
        <div v-else class="space-y-6">
          <!-- Store Info Card - Always visible -->
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between mb-2">
              <h1 class="text-3xl font-bold text-gray-900">{{ store.name }}</h1>
              <img
                v-if="store.logo_url"
                :src="store.logo_url"
                :alt="`${store.name} logo`"
                class="ml-4 h-16 w-16 object-contain rounded"
              />
            </div>
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-2">Wallet Type:</span>
                <span class="text-sm font-medium text-gray-900">
                  {{ store.wallet_type === 'blink' ? 'Blink' : store.wallet_type === 'aqua_boltz' ? 'Aqua (Boltz)' : 'Not configured' }}
                </span>
              </div>
              <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-2">Connection:</span>
                <span class="text-sm font-medium" :class="getWalletConnectionStatusClass(store)">
                  {{ getWalletConnectionStatusText(store) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Loading state for dashboard -->
          <div v-if="loading && !dashboard" class="text-center py-12">
            <p class="text-gray-500">Loading dashboard...</p>
          </div>

          <!-- Dashboard content -->
          <div v-else-if="dashboard">
            <!-- Store Status Banner -->
            <!-- Connected -->
            <div v-if="store.wallet_connection?.status === 'connected'" class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-green-800">
                    This store is ready to accept transactions, good job!
                  </p>
                </div>
              </div>
            </div>
            
            <!-- Needs Support -->
            <div v-else-if="store.wallet_connection?.status === 'needs_support'" class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-blue-800">
                    Your wallet connection is being configured by our support team. This may take a few hours.
                  </p>
                  <p class="text-sm text-blue-700 mt-1">
                    You'll be notified once it's ready.
                  </p>
                </div>
              </div>
            </div>
            
            <!-- Pending -->
            <div v-else-if="store.wallet_connection?.status === 'pending'" class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-yellow-800">
                    Wallet connection is pending configuration.
                  </p>
                  <p class="text-sm text-yellow-700 mt-1">
                    Please wait while we process your wallet connection request.
                  </p>
                </div>
              </div>
            </div>
            
            <!-- Not Configured -->
            <div v-else class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-red-800">
                    Wallet not configured
                  </p>
                  <p class="text-sm text-red-700 mt-1">
                    To start accepting payments, please configure your wallet connection in the 
                    <router-link :to="`/stores/${store.id}/wallet-connection`" class="underline font-medium">Wallet Connection</router-link> settings.
                  </p>
                </div>
              </div>
            </div>

            <!-- Dashboard Statistics -->
            <DashboardStats
              v-if="dashboard"
              :stats="{
                paid_invoices_last_7d: dashboard.paid_invoices_last_7d || 0,
                total_invoices: dashboard.total_invoices || 0
              }"
              @view-invoices="handleViewInvoices"
            />

            <!-- Recent Invoices -->
            <div v-if="dashboard.recent_invoices.length > 0" class="mb-6">
              <RecentInvoices
                :invoices="dashboard.recent_invoices"
                @view-all="handleViewAllInvoices"
                @view-invoice="handleViewInvoice"
              />
            </div>

            <!-- Empty State for Invoices -->
            <div v-else-if="!loading" class="mb-6 bg-white shadow rounded-lg p-8 text-center">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices</h3>
              <p class="mt-1 text-sm text-gray-500">Get started by creating your first invoice.</p>
            </div>

            <!-- Sales Chart and Top Items -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <SalesChart
                v-if="dashboard.sales"
                :store-name="store.name"
                :sales7d="dashboard.sales?.last_7_days || []"
                :sales30d="dashboard.sales?.last_30_days || []"
                :total-sales7d="dashboard.sales?.total_7d || 0"
                :total-sales30d="dashboard.sales?.total_30d || 0"
                :manage-url="`/stores/${store.id}/apps`"
              />
              <TopItems :items="dashboard.top_items || []" />
            </div>
          </div>
          
          <!-- Dashboard not loaded -->
          <div v-else-if="!loading" class="bg-white shadow rounded-lg p-8 text-center">
            <p class="text-gray-500 mb-2">Failed to load dashboard.</p>
            <p class="text-sm text-gray-400">Dashboard: {{ dashboard ? 'loaded' : 'not loaded' }}</p>
            <button 
              @click="storesStore.fetchDashboard(store.id)" 
              class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>

  <div v-else class="flex items-center justify-center h-full bg-gray-100">
    <p class="text-gray-500">Store not found</p>
  </div>

  <!-- Delete Store Confirmation Modal -->
  <div
    v-if="showDeleteStoreModal"
    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
    @click.self="showDeleteStoreModal = false"
  >
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          Delete {{ store?.name }}?
        </h3>
        <p class="text-sm text-gray-600 mb-4">
          This action cannot be undone. This will permanently delete the store and all its data, including invoices, apps, and settings.
        </p>
        <p class="text-sm font-medium text-gray-700 mb-2">
          Please type <span class="font-mono text-red-600">DELETE</span> to confirm:
        </p>
        <input
          v-model="deleteStoreConfirmText"
          type="text"
          placeholder="Type DELETE"
          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
          :class="{ 'border-red-500': deleteStoreConfirmText && deleteStoreConfirmText !== 'DELETE' }"
          @keyup.enter="handleDeleteStore"
        />
        <div v-if="deleteStoreError" class="mt-2 text-sm text-red-600">
          {{ deleteStoreError }}
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button
            type="button"
            @click="showDeleteStoreModal = false; deleteStoreConfirmText = ''; deleteStoreError = '';"
            :disabled="deletingStore"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleDeleteStore"
            :disabled="deletingStore || deleteStoreConfirmText !== 'DELETE'"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
          >
            {{ deletingStore ? 'Deleting...' : 'Delete Store' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import { useAppsStore } from '../../store/apps';
import StoreSidebar from '../../components/stores/StoreSidebar.vue';
import DashboardStats from '../../components/stores/DashboardStats.vue';
import RecentInvoices from '../../components/stores/RecentInvoices.vue';
import SalesChart from '../../components/stores/SalesChart.vue';
import TopItems from '../../components/stores/TopItems.vue';
import api from '../../services/api';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';

const route = useRoute();
const router = useRouter();
const storesStore = useStoresStore();
const appsStore = useAppsStore();

const loading = ref(false);
const store = ref<any>(null);
const showSettings = ref(false);
const showInvoices = ref(false);
const showExports = ref(false);

const settingsLoading = ref(false);
const settingsSaving = ref(false);
const settings = ref<any>(null);
const settingsError = ref('');
const settingsSuccess = ref('');

const invoicesLoading = ref(false);
const invoices = ref<any[]>([]);
const invoicesError = ref('');
const invoiceFilters = ref({
  status: '',
  date_from: '',
  date_to: '',
});

const exportsLoading = ref(false);
const exports = ref<any[]>([]);
const exportsError = ref('');
const exportingInvoices = ref(false);
const invoiceExportSuccess = ref('');
const invoiceExportError = ref('');
const downloadingExportId = ref<number | null>(null);
const retryingExportId = ref<number | null>(null);

// Logo management
const logoInputRef = ref<HTMLInputElement | null>(null);
const storeLogoUrl = ref<string | null>(null);
const uploadingLogo = ref(false);
const deletingLogo = ref(false);
const logoError = ref('');
const logoSuccess = ref('');

// Delete store modal
const showDeleteStoreModal = ref(false);
const deleteStoreConfirmText = ref('');
const deletingStore = ref(false);
const deleteStoreError = ref('');

const settingsForm = ref({
  name: '',
  default_currency: 'USD',
  timezone: 'UTC',
  preferred_exchange: '',
});

// Common timezones - same as in Create.vue
const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Phoenix',
  'America/Toronto',
  'America/Vancouver',
  'America/Sao_Paulo',
  'America/Argentina/Buenos_Aires',
  'America/Santiago',
  'America/Mexico_City',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Vienna',
  'Europe/Prague',
  'Europe/Warsaw',
  'Europe/Stockholm',
  'Europe/Copenhagen',
  'Europe/Helsinki',
  'Europe/Athens',
  'Europe/Istanbul',
  'Europe/Moscow',
  'Europe/Kiev',
  'Asia/Dubai',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Asia/Hong_Kong',
  'Asia/Singapore',
  'Asia/Seoul',
  'Asia/Bangkok',
  'Asia/Jakarta',
  'Asia/Manila',
  'Asia/Kolkata',
  'Asia/Karachi',
  'Asia/Dhaka',
  'Asia/Tehran',
  'Asia/Jerusalem',
  'Australia/Sydney',
  'Australia/Melbourne',
  'Australia/Brisbane',
  'Australia/Perth',
  'Pacific/Auckland',
  'Pacific/Honolulu',
];

const dashboard = computed(() => storesStore.dashboard);
const allApps = computed(() => appsStore.apps);

// Watch route params for store changes (when switching stores in dropdown)
watch(() => route.params.id, async (newStoreId, oldStoreId) => {
  if (newStoreId && newStoreId !== oldStoreId) {
    loading.value = true;
    try {
      store.value = await storesStore.fetchStore(newStoreId as string);
      
      // Load appropriate view based on query
      if (route.query.section === 'settings') {
        showSettings.value = true;
        await fetchSettings();
      } else if (route.query.section === 'invoices') {
        showInvoices.value = true;
        await fetchInvoices();
      } else if (route.query.section === 'exports') {
        showExports.value = true;
        await fetchExports();
      } else {
        showSettings.value = false;
        showInvoices.value = false;
        showExports.value = false;
        await storesStore.fetchDashboard(newStoreId as string);
        await appsStore.fetchApps(newStoreId as string);
      }
    } finally {
      loading.value = false;
    }
  }
}, { immediate: false });

// Watch route query for section changes
watch(() => route.query.section, async (newSection, oldSection) => {
  // Skip if section hasn't actually changed
  if (newSection === oldSection) return;
  
  const storeId = route.params.id as string;
  
  showSettings.value = newSection === 'settings';
  showInvoices.value = newSection === 'invoices';
  showExports.value = newSection === 'exports';
  
  if (showSettings.value && store.value) {
    await fetchSettings();
  } else if (showInvoices.value && store.value) {
    await fetchInvoices();
  } else if (showExports.value && store.value) {
    await fetchExports();
  } else if (store.value && !newSection) {
    // Load dashboard when switching back to default view (no section query)
    loading.value = true;
    try {
      await storesStore.fetchDashboard(storeId);
      await appsStore.fetchApps(storeId);
    } finally {
      loading.value = false;
    }
  }
});

// Auto-refresh exports status when exports view is visible
let exportsRefreshInterval: number | null = null;

watch(showExports, (isVisible) => {
  if (isVisible) {
    // Fetch exports immediately
    fetchExports();
    
    // Set up interval to refresh exports every 5 seconds if there are pending/running exports
    exportsRefreshInterval = window.setInterval(() => {
      const hasPendingOrRunning = exports.value.some((e: any) => 
        e.status === 'pending' || e.status === 'running'
      );
      
      if (hasPendingOrRunning) {
        fetchExports();
      } else {
        // Clear interval if no pending/running exports
        if (exportsRefreshInterval) {
          clearInterval(exportsRefreshInterval);
          exportsRefreshInterval = null;
        }
      }
    }, 5000);
  } else {
    // Clear interval when exports view is hidden
    if (exportsRefreshInterval) {
      clearInterval(exportsRefreshInterval);
      exportsRefreshInterval = null;
    }
  }
});

// Clean up interval on unmount
onUnmounted(() => {
  if (exportsRefreshInterval) {
    clearInterval(exportsRefreshInterval);
  }
});

onMounted(async () => {
  const storeId = route.params.id as string;
  loading.value = true;
  try {
    // Load store first
    store.value = await storesStore.fetchStore(storeId);
    
    // Check which view is requested
    const section = route.query.section;
    if (section === 'settings') {
      showSettings.value = true;
      await fetchSettings();
    } else if (section === 'invoices') {
      showInvoices.value = true;
      await fetchInvoices();
    } else if (section === 'exports') {
      showExports.value = true;
      await fetchExports();
    } else {
      // Load dashboard by default
      showSettings.value = false;
      showInvoices.value = false;
      showExports.value = false;
      await storesStore.fetchDashboard(storeId);
      await appsStore.fetchApps(storeId);
    }
  } finally {
    loading.value = false;
  }
});

async function fetchSettings() {
  if (!store.value) return;
  
  settingsLoading.value = true;
  try {
    const response = await api.get(`/stores/${store.value.id}/settings`);
    settings.value = response.data.data;
    settingsForm.value.name = settings.value.name;
    settingsForm.value.default_currency = settings.value.default_currency || 'USD';
    settingsForm.value.timezone = settings.value.timezone || 'UTC';
    settingsForm.value.preferred_exchange = settings.value.preferred_exchange || '';
    // Load logo URL if available
    storeLogoUrl.value = settings.value.logo_url || null;
  } catch (error) {
    console.error('Failed to fetch settings:', error);
  } finally {
    settingsLoading.value = false;
  }
}

async function handleLogoUpload(event: Event) {
  if (!store.value) return;
  
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploadingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(`/stores/${store.value.id}/logo`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    // Update logo URL from response
    if (response.data?.data?.imageUrl) {
      storeLogoUrl.value = response.data.data.imageUrl;
    } else if (response.data?.imageUrl) {
      storeLogoUrl.value = response.data.imageUrl;
    }

    logoSuccess.value = 'Logo uploaded successfully';
    
    // Clear file input
    if (logoInputRef.value) {
      logoInputRef.value.value = '';
    }

    // Clear success message after 3 seconds
    setTimeout(() => {
      logoSuccess.value = '';
    }, 3000);
  } catch (error: any) {
    logoError.value = error.response?.data?.message || 'Failed to upload logo';
    console.error('Failed to upload logo:', error);
  } finally {
    uploadingLogo.value = false;
  }
}

async function handleDeleteLogo() {
  if (!store.value) return;
  
  if (!confirm('Are you sure you want to delete the store logo?')) {
    return;
  }

  deletingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    await api.delete(`/stores/${store.value.id}/logo`);
    storeLogoUrl.value = null;
    logoSuccess.value = 'Logo deleted successfully';

    // Clear success message after 3 seconds
    setTimeout(() => {
      logoSuccess.value = '';
    }, 3000);
  } catch (error: any) {
    logoError.value = error.response?.data?.message || 'Failed to delete logo';
    console.error('Failed to delete logo:', error);
  } finally {
    deletingLogo.value = false;
  }
}

async function handleDeleteStore() {
  if (!store.value) return;
  
  if (deleteStoreConfirmText.value !== 'DELETE') {
    deleteStoreError.value = 'Please type DELETE to confirm';
    return;
  }

  deletingStore.value = true;
  deleteStoreError.value = '';

  try {
    await storesStore.deleteStore(store.value.id);
    
    // Close modal and redirect to dashboard after successful deletion
    showDeleteStoreModal.value = false;
    deleteStoreConfirmText.value = '';
    
    // Small delay to show success state before redirect
    setTimeout(() => {
      router.push({ name: 'home' });
    }, 300);
  } catch (error: any) {
    // Only show error if deletion actually failed
    deleteStoreError.value = error.response?.data?.message || 'Failed to delete store';
    console.error('Failed to delete store:', error);
    deletingStore.value = false;
  }
}

async function handleSettingsSubmit() {
  if (!store.value) return;
  
  settingsError.value = '';
  settingsSuccess.value = '';
  settingsSaving.value = true;

  try {
    await api.put(`/stores/${store.value.id}/settings`, settingsForm.value);
    settingsSuccess.value = 'Settings updated successfully';
    await fetchSettings();
    // Update store in store store
    store.value.name = settingsForm.value.name;
  } catch (err: any) {
    settingsError.value = err.response?.data?.message || 'Failed to update settings. Please try again.';
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      settingsError.value = errors.join(', ');
    }
  } finally {
    settingsSaving.value = false;
  }
}

async function fetchInvoices() {
  if (!store.value) return;
  
  invoicesLoading.value = true;
  invoicesError.value = '';
  try {
    // Build query parameters from filters
    const params: any = {};
    if (invoiceFilters.value.status) {
      params.status = invoiceFilters.value.status;
    }
    if (invoiceFilters.value.date_from) {
      params.date_from = invoiceFilters.value.date_from;
    }
    if (invoiceFilters.value.date_to) {
      params.date_to = invoiceFilters.value.date_to;
    }
    
    const response = await api.get(`/stores/${store.value.id}/invoices`, { params });
    invoices.value = response.data.data || [];
  } catch (error: any) {
    invoicesError.value = error.response?.data?.message || 'Failed to load invoices.';
    invoices.value = [];
  } finally {
    invoicesLoading.value = false;
  }
}

function clearInvoiceFilters() {
  invoiceFilters.value = {
    status: '',
    date_from: '',
    date_to: '',
  };
  fetchInvoices();
}

const hasInvoiceFilters = computed(() => {
  return invoiceFilters.value.status !== '' || 
         invoiceFilters.value.date_from !== '' || 
         invoiceFilters.value.date_to !== '';
});

async function handleExportInvoices() {
  if (!store.value) return;
  
  exportingInvoices.value = true;
  invoiceExportError.value = '';
  invoiceExportSuccess.value = '';
  
  try {
    const response = await api.post(`/stores/${store.value.id}/exports`, {
      format: 'standard',
      status: invoiceFilters.value.status || null,
      date_from: invoiceFilters.value.date_from || null,
      date_to: invoiceFilters.value.date_to || null,
    });
    
    invoiceExportSuccess.value = 'Export job has been queued. You will be notified when it\'s ready.';
    
    // Clear success message after 5 seconds
    setTimeout(() => {
      invoiceExportSuccess.value = '';
    }, 5000);
    
    // Optionally refresh exports list
    if (showExports.value) {
      await fetchExports();
    }
  } catch (error: any) {
    invoiceExportError.value = error.response?.data?.message || 'Failed to create export.';
    
    // Clear error message after 5 seconds
    setTimeout(() => {
      invoiceExportError.value = '';
    }, 5000);
  } finally {
    exportingInvoices.value = false;
  }
}

async function fetchExports() {
  if (!store.value) return;
  
  exportsLoading.value = true;
  exportsError.value = '';
  try {
    const response = await api.get(`/stores/${store.value.id}/exports`);
    exports.value = response.data.data || [];
  } catch (error: any) {
    exportsError.value = error.response?.data?.message || 'Failed to load exports.';
    exports.value = [];
  } finally {
    exportsLoading.value = false;
  }
}

async function handleDownloadExport(exportItem: any) {
  downloadingExportId.value = exportItem.id;
  
  try {
    const response = await api.get(`/exports/${exportItem.id}/download`);
    
    if (response.data.data?.download_url) {
      // Open download URL in new window/tab
      window.open(response.data.data.download_url, '_blank');
    } else {
      exportsError.value = 'Download URL not available.';
    }
  } catch (error: any) {
    if (error.response?.status === 202) {
      exportsError.value = 'Export is not ready yet. Please wait a moment and try again.';
    } else {
      exportsError.value = error.response?.data?.message || 'Failed to download export.';
    }
    
    // Clear error after 5 seconds
    setTimeout(() => {
      exportsError.value = '';
    }, 5000);
  } finally {
    downloadingExportId.value = null;
  }
}

async function handleRetryExport(exportItem: any) {
  retryingExportId.value = exportItem.id;
  
  try {
    await api.post(`/exports/${exportItem.id}/retry`);
    
    // Refresh exports list after retry
    await fetchExports();
    
    // Show success message
    exportsError.value = '';
    setTimeout(() => {
      // Refresh again after a short delay to see updated status
      setTimeout(() => fetchExports(), 2000);
    }, 1000);
  } catch (error: any) {
    exportsError.value = error.response?.data?.message || 'Failed to retry export.';
    
    // Clear error after 5 seconds
    setTimeout(() => {
      exportsError.value = '';
    }, 5000);
  } finally {
    retryingExportId.value = null;
  }
}

function handleCreateApp() {
  router.push({ name: 'stores-apps-create', params: { id: store.value.id } });
}

function handleShowSettings() {
  handleShowSection('settings');
}

function handleShowSection(section: string) {
  showSettings.value = section === 'settings';
  showInvoices.value = section === 'invoices';
  showExports.value = section === 'exports';
  
  router.push({ name: 'stores-show', params: { id: store.value.id }, query: { section } }).then(() => {
    if (store.value) {
      if (section === 'settings') {
        fetchSettings();
      } else if (section === 'invoices') {
        fetchInvoices();
      } else if (section === 'exports') {
        fetchExports();
      }
    }
  });
}

function handleViewInvoices(filters?: any) {
  router.push({ name: 'stores-show', params: { id: store.value.id }, query: { section: 'invoices', ...filters } });
}

function handleViewAllInvoices() {
  handleViewInvoices();
}

function handleViewInvoice(invoice: any) {
  // Navigate to invoice detail (future implementation)
  router.push({ name: 'stores-invoices-show', params: { id: store.value.id, invoiceId: invoice.id } });
}

function formatDate(dateString: string | number): string {
  if (!dateString) return '-';
  
  let date: Date;
  
  // If it's a number, it's likely a Unix timestamp
  if (typeof dateString === 'number') {
    // BTCPay API returns Unix timestamps in seconds, but JavaScript Date expects milliseconds
    // If the number is less than a reasonable timestamp in milliseconds (year 2000), assume it's in seconds
    date = dateString < 946684800000 ? new Date(dateString * 1000) : new Date(dateString);
  } else {
    // If it's a string, try to parse it
    // First check if it looks like a Unix timestamp string (only digits)
    const trimmed = dateString.trim();
    if (/^\d+$/.test(trimmed)) {
      const parsed = parseFloat(trimmed);
      if (!isNaN(parsed) && parsed < 946684800000) {
        // It's a string representation of a Unix timestamp in seconds
        date = new Date(parsed * 1000);
      } else {
        // It's a Unix timestamp in milliseconds
        date = new Date(parsed);
      }
    } else {
      // It's an ISO string or other date format (Laravel returns ISO strings like "2026-01-20T12:30:45.000000Z")
      date = new Date(dateString);
    }
  }
  
  // Check if date is valid
  if (isNaN(date.getTime())) return '-';
  
  // European format: DD.MM.YYYY
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
}

function formatAmount(amount: string | number, currency: string = 'USD'): string {
  if (!amount) return '-';
  try {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(numAmount)) return '-';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency || 'USD',
    }).format(numAmount);
  } catch (error) {
    return `${amount} ${currency || 'USD'}`;
  }
}

function getStatusClass(status: string): string {
  const statusLower = status?.toLowerCase() || '';
  if (statusLower === 'paid' || statusLower === 'settled' || statusLower === 'complete') {
    return 'bg-green-100 text-green-800';
  } else if (statusLower === 'pending' || statusLower === 'processing') {
    return 'bg-yellow-100 text-yellow-800';
  } else if (statusLower === 'expired' || statusLower === 'invalid' || statusLower === 'failed') {
    return 'bg-red-100 text-red-800';
  }
  return 'bg-gray-100 text-gray-800';
}

function getWalletConnectionStatusClass(store: any): string {
  if (!store.wallet_connection) {
    return 'text-red-600'; // Non-existent - red
  }
  
  const status = store.wallet_connection.status;
  switch (status) {
    case 'connected':
      return 'text-green-600 font-medium'; // Connected - green
    case 'needs_support':
      return 'text-blue-600 font-medium'; // Needs support - blue
    case 'pending':
    default:
      return 'text-gray-600'; // Pending/default - gray
  }
}

function getWalletConnectionStatusText(store: any): string {
  if (!store.wallet_connection) {
    return 'Not configured';
  }
  
  const status = store.wallet_connection.status;
  switch (status) {
    case 'connected':
      return 'Connected';
    case 'needs_support':
      return 'Needs support';
    case 'pending':
      return 'Pending';
    default:
      return 'Unknown status';
  }
}
</script>

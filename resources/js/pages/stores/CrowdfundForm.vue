<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Update Crowdfund</h1>
        </div>
        <div v-if="app.btcpay_app_url">
          <a
            :href="app.btcpay_app_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            Open Crowdfund
          </a>
        </div>
      </div>
    </div>

    <!-- Crowdfund Form -->
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- General Information -->
      <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <h2 class="text-xl font-semibold text-gray-900">General Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="appName" class="block text-sm font-medium text-gray-700">
              App Name <span class="text-red-500">*</span>
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
            <label for="displayTitle" class="block text-sm font-medium text-gray-700">
              Display Title <span class="text-red-500">*</span>
            </label>
            <input
              id="displayTitle"
              v-model="form.displayTitle"
              type="text"
              required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
          </div>
        </div>

        <div>
          <label for="tagline" class="block text-sm font-medium text-gray-700">
            Tagline
          </label>
          <input
            id="tagline"
            v-model="form.tagline"
            type="text"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Featured Image URL
          </label>
          <div class="flex gap-2">
            <input
              v-model="form.featuredImageUrl"
              type="text"
              placeholder="No file selected."
              class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
            <button
              type="button"
              @click="handleBrowseImage"
              class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              Browse...
            </button>
          </div>
        </div>

        <div class="flex items-center">
          <input
            id="makePublic"
            v-model="form.makePublic"
            type="checkbox"
            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
          />
          <label for="makePublic" class="ml-2 block text-sm text-gray-900">
            Make Crowdfund Public
          </label>
        </div>
        <p v-if="form.makePublic" class="text-sm text-gray-500 ml-6">
          The crowdfund will be visible to anyone.
        </p>

        <div>
          <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
            Description <span class="text-red-500">*</span>
          </label>
          <!-- Rich text editor will be added here -->
          <textarea
            id="description"
            v-model="form.description"
            rows="6"
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          ></textarea>
        </div>
      </div>

      <!-- Goal Section -->
      <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <h2 class="text-xl font-semibold text-gray-900">Goal</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="targetAmount" class="block text-sm font-medium text-gray-700">
              Target Amount
            </label>
            <input
              id="targetAmount"
              v-model="form.targetAmount"
              type="number"
              step="0.01"
              min="0"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
          </div>

          <div>
            <label for="currency" class="block text-sm font-medium text-gray-700">
              Currency
            </label>
            <select
              id="currency"
              v-model="form.currency"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="">Use store default ({{ store?.default_currency || 'EUR' }})</option>
              <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                {{ currency.code }} - {{ currency.name }}
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500">
              Uses the store's default currency ({{ store?.default_currency || 'EUR' }}) if empty.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="startDate" class="block text-sm font-medium text-gray-700">
              Start date
            </label>
            <div class="mt-1 relative">
              <input
                id="startDate"
                v-model="form.startDate"
                type="datetime-local"
                class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 pr-10 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
              <button
                v-if="form.startDate"
                type="button"
                @click="form.startDate = ''"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-orange-600 hover:text-orange-800"
                title="Clear date"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div>
            <label for="endDate" class="block text-sm font-medium text-gray-700">
              End date
            </label>
            <div class="mt-1 relative">
              <input
                id="endDate"
                v-model="form.endDate"
                type="datetime-local"
                :placeholder="form.endDate ? '' : 'No end date has been set'"
                class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 pr-10 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              />
              <button
                v-if="form.endDate"
                type="button"
                @click="form.endDate = ''"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-orange-600 hover:text-orange-800"
                title="Clear date"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Recurring Goal -->
        <div class="space-y-4 pt-4 border-t border-gray-200">
          <div class="flex items-center">
            <input
              id="recurringGoal"
              v-model="form.recurringGoal"
              type="checkbox"
              class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded"
            />
            <label for="recurringGoal" class="ml-2 block text-sm text-gray-900">
              Recurring Goal
            </label>
          </div>
          <p v-if="form.recurringGoal" class="text-sm text-gray-500 ml-6">
            Reset goal after a specific period of time, based on your crowdfund's start date.
          </p>

          <div v-if="form.recurringGoal" class="ml-6 grid grid-cols-2 gap-4">
            <div class="flex items-center gap-2">
              <label for="resetEveryAmount" class="block text-sm font-medium text-gray-700 whitespace-nowrap">
                Reset goal every
              </label>
              <input
                id="resetEveryAmount"
                v-model.number="form.resetEveryAmount"
                type="number"
                min="1"
                class="block w-20 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
              />
              <select
                v-model="form.resetEveryUnit"
                class="block border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
              >
                <option value="Hour">Hour</option>
                <option value="Day">Day</option>
                <option value="Week">Week</option>
                <option value="Month">Month</option>
                <option value="Year">Year</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Perks Section -->
      <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">Perks</h2>
          <div class="flex gap-2 border-b border-gray-200">
            <button
              type="button"
              @click="perksViewMode = 'editor'"
              :class="[
                'px-4 py-2 text-sm font-medium border-b-2',
                perksViewMode === 'editor'
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
            >
              Editor
            </button>
            <button
              type="button"
              @click="perksViewMode = 'code'"
              :class="[
                'px-4 py-2 text-sm font-medium border-b-2',
                perksViewMode === 'code'
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
            >
              Code
            </button>
          </div>
        </div>

        <!-- Editor View -->
        <div v-if="perksViewMode === 'editor'">
          <div class="space-y-4">
            <div
              v-for="(perk, index) in perks"
              :key="index"
              class="border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-indigo-500"
              @click="editPerk(index)"
            >
              <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center">
                  <img
                    v-if="perk.imageUrl"
                    :src="perk.imageUrl"
                    :alt="perk.title"
                    class="w-full h-full object-cover rounded"
                  />
                  <svg v-else class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <h3 class="font-medium text-gray-900">{{ perk.title || 'Custom' }}</h3>
                  <p class="text-sm text-gray-500">
                    <span v-if="perk.priceType === 'Minimum'">Min. {{ perk.price || 0 }} {{ form.currency || store?.default_currency || 'EUR' }}</span>
                    <span v-else>{{ perk.price || 0 }} {{ form.currency || store?.default_currency || 'EUR' }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="addPerk"
            class="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            + Add Item
          </button>
        </div>

        <!-- Code View -->
        <div v-else>
          <textarea
            v-model="perksJson"
            rows="10"
            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 font-mono text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            @blur="parsePerksJson"
          ></textarea>
        </div>
      </div>

      <!-- Additional Options (Accordion) -->
      <AdditionalOptions
        v-model:contributions="form.contributions"
        v-model:crowdfundBehavior="form.crowdfundBehavior"
        v-model:checkout="form.checkout"
        v-model:advanced="form.advanced"
      />

      <!-- Success/Error Messages -->
      <div v-if="success" class="rounded-md bg-green-50 p-4">
        <div class="text-sm text-green-800">{{ success }}</div>
      </div>
      <div v-if="error" class="rounded-md bg-red-50 p-4">
        <div class="text-sm text-red-800">{{ error }}</div>
      </div>

      <!-- Submit and Delete Buttons -->
      <div class="flex justify-between items-center">
        <button
          type="button"
          @click="showDeleteModal = true"
          :disabled="saving || deleting"
          class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
        >
          Delete App
        </button>
        
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
          {{ saving ? 'Saving...' : 'Update Crowdfund' }}
        </button>
      </div>
    </form>

    <!-- Perk Edit Drawer -->
    <PerkEditDrawer
      :is-open="showPerkDrawer"
      :perk="currentPerk"
      :currency="form.currency || store?.default_currency || 'EUR'"
      :store-id="store?.id"
      @close="showPerkDrawer = false"
      @save="handlePerkSave"
    />

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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAppsStore } from '../../store/apps';
import { currencies } from '../../data/currencies';
import PerkEditDrawer from '../../components/stores/PerkEditDrawer.vue';
import AdditionalOptions from '../../components/stores/AdditionalOptions.vue';

const props = defineProps<{
  app: any;
  store: any;
}>();

const form = ref({
  appName: '',
  displayTitle: '',
  tagline: '',
  featuredImageUrl: '',
  makePublic: true,
  description: '',
  targetAmount: '',
  currency: '',
  startDate: '',
  endDate: '',
  recurringGoal: false,
  resetEveryAmount: 1,
  resetEveryUnit: 'Day',
  contributions: {
    sortByPopularity: false,
    displayRanking: false,
    displayValue: false,
    noAdditionalAfterTarget: false,
  },
  crowdfundBehavior: {
    countAllInvoices: false,
  },
  checkout: {
    requestContributorData: false,
  },
  advanced: {
    htmlLanguage: '',
    htmlMetaTags: '',
    enableSounds: false,
    enableAnimations: false,
    enableDiscussion: false,
    callbackNotificationUrl: '',
  },
});

const perks = ref<any[]>([]);
const perksJson = ref('[]');
const perksViewMode = ref<'editor' | 'code'>('editor');
const showPerkDrawer = ref(false);
const editingPerkIndex = ref<number | null>(null);
const saving = ref(false);
const error = ref('');
const success = ref('');
const appsStore = useAppsStore();
const router = useRouter();

// Delete modal state
const showDeleteModal = ref(false);
const deleteConfirmText = ref('');
const deleteError = ref('');
const deleting = ref(false);

const currentPerk = computed(() => {
  if (editingPerkIndex.value !== null && perks.value[editingPerkIndex.value]) {
    return perks.value[editingPerkIndex.value];
  }
  return null;
});

function handleBrowseImage() {
  // TODO: Implement image upload
  console.log('Browse image');
}

function addPerk() {
  editingPerkIndex.value = null;
  showPerkDrawer.value = true;
}

function editPerk(index: number) {
  editingPerkIndex.value = index;
  showPerkDrawer.value = true;
}

function handlePerkSave(perk: any) {
  if (editingPerkIndex.value !== null) {
    perks.value[editingPerkIndex.value] = perk;
  } else {
    perks.value.push(perk);
  }
  editingPerkIndex.value = null;
  showPerkDrawer.value = false;
  updatePerksJson();
}

function removePerk(index: number) {
  if (confirm('Are you sure you want to remove this perk?')) {
    perks.value.splice(index, 1);
    updatePerksJson();
  }
}

function updatePerksJson() {
  perksJson.value = JSON.stringify(perks.value, null, 2);
}

function parsePerksJson() {
  try {
    const parsed = JSON.parse(perksJson.value);
    if (Array.isArray(parsed)) {
      perks.value = parsed;
    }
  } catch (e) {
    console.error('Invalid JSON in perks code view', e);
  }
}

async function handleSubmit() {
  saving.value = true;
  error.value = '';
  success.value = '';
  
  try {
    // If switching from code view, parse JSON first
    if (perksViewMode.value === 'code') {
      try {
        const parsed = JSON.parse(perksJson.value);
        perks.value = Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        error.value = 'Invalid JSON in perks code view';
        return;
      }
    }

    // Prepare perks array
    const perksArray = perks.value
      .filter(p => p.title) // Only include perks with titles
      .map(p => {
        // Handle inventory:
        let inventory: number | null = null;
        if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) {
            inventory = invNum;
          }
        }

        return {
          id: p.id || generatePerkId(p.title),
          title: p.title,
          disabled: p.disabled || false,
          description: p.description || null,
          categories: p.categories ? String(p.categories).split(',').map((c: string) => c.trim()).filter((c: string) => c) : null,
          image: p.image || null,
          priceType: p.priceType || 'Minimum',
          price: p.priceType !== 'Free' && p.priceType !== 'Topup' ? String(p.price || 1) : null,
          buyButtonText: p.buyButtonText || 'Podporiť',
          inventory: inventory,
          taxRate: p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== '' ? String(p.taxRate) : null,
        };
      });

    // Convert dates to UNIX timestamps (seconds)
    let startDateTimestamp: number | null = null;
    if (form.value.startDate) {
      const date = new Date(form.value.startDate);
      if (!isNaN(date.getTime())) {
        startDateTimestamp = Math.floor(date.getTime() / 1000);
      }
    }
    
    let endDateTimestamp: number | null = null;
    if (form.value.endDate) {
      const date = new Date(form.value.endDate);
      if (!isNaN(date.getTime())) {
        endDateTimestamp = Math.floor(date.getTime() / 1000);
      }
    }

    // Build config object
    const config: any = {
      appName: form.value.appName,
      displayTitle: form.value.displayTitle,
      tagline: form.value.tagline || null,
      featuredImageUrl: form.value.featuredImageUrl || null,
      makePublic: form.value.makePublic,
      description: form.value.description,
      targetAmount: form.value.targetAmount ? String(form.value.targetAmount) : null,
      currency: form.value.currency || null,
      startDate: startDateTimestamp,
      endDate: endDateTimestamp,
      perks: perksArray,
      contributions: form.value.contributions,
      crowdfundBehavior: form.value.crowdfundBehavior,
      checkout: form.value.checkout,
      advanced: form.value.advanced,
    };

    // Handle recurring goal settings
    if (form.value.recurringGoal) {
      // Ensure minimum value of 1 for resetEveryAmount
      const resetAmount = form.value.resetEveryAmount >= 1 ? form.value.resetEveryAmount : 1;
      
      // Map resetEveryUnit to BTCPay format (Day, Hour, Week, Month, Year)
      config.crowdfundBehavior = {
        ...config.crowdfundBehavior,
        resetEvery: form.value.resetEveryUnit,
        resetEveryAmount: resetAmount,
      };
      // Also set at root level for BTCPay API
      config.resetEvery = form.value.resetEveryUnit;
      config.resetEveryAmount = resetAmount;
      
      // Ensure startDate is set when recurring goal is enabled
      if (!startDateTimestamp) {
        startDateTimestamp = Math.floor(Date.now() / 1000);
        config.startDate = startDateTimestamp;
      }
    } else {
      // If not recurring, set resetEveryAmount to 0 and resetEvery to 'Never' (disable reset)
      config.resetEveryAmount = 0;
      config.resetEvery = 'Never';
      // Remove from crowdfundBehavior to avoid confusion
      if (config.crowdfundBehavior) {
        delete config.crowdfundBehavior.resetEvery;
        delete config.crowdfundBehavior.resetEveryAmount;
      }
    }

    // Save via API
    const appsStore = useAppsStore();
    await appsStore.updateApp(props.store.id, props.app.id, {
      name: form.value.appName,
      config: config,
    });

    success.value = 'Crowdfund updated successfully';
    setTimeout(() => {
      success.value = '';
    }, 3000);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save crowdfund';
  } finally {
    saving.value = false;
  }
}

function generatePerkId(title: string): string {
  if (!title) return '';
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

async function handleDelete() {
  if (deleteConfirmText.value !== 'DELETE') {
    deleteError.value = 'Please type DELETE to confirm';
    return;
  }

  deleting.value = true;
  deleteError.value = '';
  
  try {
    await appsStore.deleteApp(props.store.id, props.app.id);
    
    // Redirect to store page after successful deletion
    router.push({ name: 'stores-show', params: { id: props.store.id } });
  } catch (err: any) {
    deleteError.value = err.response?.data?.message || 'Failed to delete app';
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  // Load existing data
  if (props.app?.config) {
    const config = props.app.config;
    form.value.appName = config.appName || props.app.name || '';
    form.value.displayTitle = config.displayTitle || config.title || '';
    form.value.tagline = config.tagline || '';
    form.value.featuredImageUrl = config.featuredImageUrl || config.featuredImage || '';
    form.value.makePublic = config.makePublic !== undefined ? config.makePublic : true;
    form.value.description = config.description || '';
    form.value.targetAmount = config.targetAmount || config.goal || '';
    form.value.currency = config.currency || '';
    
    // Handle dates - convert from UNIX timestamp or ISO string to datetime-local format
    if (config.startDate) {
      let startDate: Date;
      if (typeof config.startDate === 'number') {
        // UNIX timestamp in seconds, convert to milliseconds
        startDate = new Date(config.startDate * 1000);
      } else {
        startDate = new Date(config.startDate);
      }
      if (!isNaN(startDate.getTime())) {
        form.value.startDate = startDate.toISOString().slice(0, 16);
      }
    }
    if (config.endDate) {
      let endDate: Date;
      if (typeof config.endDate === 'number') {
        // UNIX timestamp in seconds, convert to milliseconds
        endDate = new Date(config.endDate * 1000);
      } else {
        endDate = new Date(config.endDate);
      }
      if (!isNaN(endDate.getTime())) {
        form.value.endDate = endDate.toISOString().slice(0, 16);
      }
    }

    // Handle recurring goal settings
    if (config.resetEveryAmount && config.resetEveryAmount > 0 && config.resetEvery && config.resetEvery !== 'Never') {
      form.value.recurringGoal = true;
      form.value.resetEveryAmount = config.resetEveryAmount;
      form.value.resetEveryUnit = config.resetEvery;
    } else {
      form.value.recurringGoal = false;
      form.value.resetEveryAmount = 1;
      form.value.resetEveryUnit = 'Day';
    }
    
    if (config.contributions) {
      form.value.contributions = {
        sortByPopularity: config.contributions.sortByPopularity || false,
        displayRanking: config.contributions.displayRanking || false,
        displayValue: config.contributions.displayValue || false,
        noAdditionalAfterTarget: config.contributions.noAdditionalAfterTarget || false,
      };
    }
    if (config.crowdfundBehavior) {
      form.value.crowdfundBehavior = {
        countAllInvoices: config.crowdfundBehavior.countAllInvoices || false,
      };
    }
    if (config.checkout) {
      form.value.checkout = {
        requestContributorData: config.checkout.requestContributorData || false,
      };
    }
    if (config.advanced) {
      form.value.advanced = {
        htmlLanguage: config.advanced.htmlLanguage || config.htmlLang || '',
        htmlMetaTags: config.advanced.htmlMetaTags || config.htmlMetaTags || '',
        enableSounds: config.advanced.enableSounds !== undefined ? config.advanced.enableSounds : false,
        enableAnimations: config.advanced.enableAnimations !== undefined ? config.advanced.enableAnimations : false,
        enableDiscussion: config.advanced.enableDiscussion !== undefined ? config.advanced.enableDiscussion : false,
        callbackNotificationUrl: config.advanced.callbackNotificationUrl || config.notificationUrl || '',
      };
    }

    // Load perks - could be in 'perks', 'items', or 'template' field
    let perksArray: any[] = [];
    const perksSource = config.perks || config.items || config.template;
    
    if (perksSource) {
      if (Array.isArray(perksSource)) {
        perksArray = perksSource;
      } else if (typeof perksSource === 'string') {
        try {
          const parsed = JSON.parse(perksSource);
          perksArray = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          console.warn('Failed to parse perks JSON', e);
        }
      }
    }
    
    if (perksArray && perksArray.length > 0) {
      perks.value = perksArray.map((p: any) => {
        // Handle inventory
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
          priceType: p.priceType || 'Minimum',
          price: p.price ? parseFloat(String(p.price)) : 1,
          taxRate: p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== '' ? parseFloat(String(p.taxRate)) : null,
          image: p.image || '',
          description: p.description || '',
          categories: p.categories ? (Array.isArray(p.categories) ? p.categories.join(', ') : String(p.categories)) : null,
          inventory: inventory,
          buyButtonText: p.buyButtonText || 'Podporiť',
          disabled: p.disabled !== undefined ? p.disabled : false,
        };
      });
      updatePerksJson();
    }
  } else {
    // If no config, initialize with defaults
    form.value.appName = props.app.name || '';
    form.value.currency = props.store?.default_currency || 'EUR';
    perks.value = [];
    updatePerksJson();
  }
});
</script>


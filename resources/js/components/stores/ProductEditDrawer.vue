<template>
  <!-- Drawer overlay -->
  <Transition
    enter-active-class="transition-opacity ease-linear duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity ease-linear duration-300"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-40"
      @click="handleClose"
    ></div>
  </Transition>

  <!-- Drawer panel -->
  <Transition
    enter-active-class="transition ease-in-out duration-300 transform"
    enter-from-class="translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transition ease-in-out duration-300 transform"
    leave-from-class="translate-x-0"
    leave-to-class="translate-x-full"
  >
    <div
      v-if="isOpen"
      class="fixed inset-y-0 right-0 max-w-2xl w-full bg-gray-900 border-l border-gray-700 shadow-2xl z-50 flex flex-col"
    >
      <!-- Drawer header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700 bg-gray-800/50">
        <h2 class="text-xl font-bold text-white">
          {{ editingProduct ? 'Edit Item' : 'Add Item' }}
        </h2>
        <button
          type="button"
          @click="handleClose"
          class="text-gray-400 hover:text-white transition-colors focus:outline-none"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Drawer content -->
      <div class="flex-1 overflow-y-auto custom-scrollbar px-6 py-6 font-light">
        <div class="space-y-6">
          <!-- Title -->
          <div>
            <label for="product-title" class="block text-sm font-medium text-gray-300 mb-1">
              Title <span class="text-red-400">*</span>
            </label>
            <input
              id="product-title"
              v-model="localProduct.title"
              type="text"
              required
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              @blur="generateIdFromTitle"
            />
          </div>

          <!-- ID -->
          <div>
            <label for="product-id" class="block text-sm font-medium text-gray-300 mb-1">
              ID <span class="text-red-400">*</span>
            </label>
            <input
              id="product-id"
              v-model="localProduct.id"
              type="text"
              required
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="Auto-generated from title"
            />
            <p class="mt-1 text-xs text-gray-500">Leave blank to generate ID from title.</p>
          </div>

          <!-- Price Type and Price -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="product-price-type" class="block text-sm font-medium text-gray-300 mb-1">
                Price Type
              </label>
              <select
                id="product-price-type"
                v-model="localProduct.priceType"
                class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all appearance-none"
              >
                <option value="Fixed">Fixed</option>
                <option value="Minimum">Minimum</option>
                <option value="Topup">Any amount</option>
                <option value="Free">Free</option>
              </select>
            </div>

            <div v-if="localProduct.priceType !== 'Free' && localProduct.priceType !== 'Topup'">
              <label for="product-price" class="block text-sm font-medium text-gray-300 mb-1">
                Amount
              </label>
              <div class="flex rounded-xl shadow-sm">
                <input
                  id="product-price"
                  v-model.number="localProduct.price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="flex-1 min-w-0 block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-l-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
                <span class="inline-flex items-center px-4 rounded-r-xl border border-l-0 border-gray-600 bg-gray-700 text-gray-300 text-sm font-medium">
                  {{ currency || 'EUR' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Tax rate -->
          <div>
            <label for="product-tax-rate" class="block text-sm font-medium text-gray-300 mb-1">
              Tax rate
            </label>
            <div class="flex rounded-xl shadow-sm">
              <input
                id="product-tax-rate"
                v-model.number="localProduct.taxRate"
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="flex-1 min-w-0 block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-l-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                placeholder="0.00"
              />
              <span class="inline-flex items-center px-4 rounded-r-xl border border-l-0 border-gray-600 bg-gray-700 text-gray-300 text-sm font-medium">
                %
              </span>
            </div>
          </div>

          <!-- Image Upload/URL -->
          <div>
            <label for="product-image-url" class="block text-sm font-medium text-gray-300 mb-1">
              Image Url
            </label>
            <div class="space-y-3">
              <!-- Image preview -->
              <div v-if="imagePreview || localProduct.image" class="flex items-center gap-4 bg-gray-800 p-3 rounded-xl border border-gray-700">
                <div class="h-20 w-20 flex-shrink-0 bg-gray-700 rounded-lg overflow-hidden border border-gray-600">
                   <img
                    :src="imagePreview || localProduct.image"
                    alt="Product preview"
                    class="h-full w-full object-cover"
                    @error="imagePreview = null"
                  />
                </div>
                <button
                  type="button"
                  @click="clearImage"
                  class="text-sm text-red-400 hover:text-red-300 font-medium transition-colors"
                >
                  Remove image
                </button>
              </div>
              
              <!-- Upload button -->
              <div class="flex items-center gap-3">
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleFileSelect"
                />
                <button
                  type="button"
                  @click="fileInput?.click()"
                  class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 hover:text-white transition-all hover:scale-105 shadow-sm"
                >
                  Browse...
                </button>
                <div class="text-sm text-gray-400 truncate flex-1">
                    <span v-if="!selectedFile && !localProduct.image">No file selected</span>
                    <span v-else-if="selectedFile">{{ selectedFile.name }}</span>
                    <span v-else>Using URL</span>
                </div>
                
                 <!-- Upload button (if file selected) -->
                <button
                    v-if="selectedFile"
                    type="button"
                    @click="uploadImage"
                    :disabled="uploading"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 transition-all hover:scale-105"
                >
                    <svg v-if="uploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ uploading ? 'Uploading...' : 'Upload' }}
                </button>
              </div>
              
            
              <!-- URL input -->
              <input
                id="product-image-url"
                v-model="localProduct.image"
                type="text"
                class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                placeholder="https://example.com/image.jpg"
              />
            </div>
          </div>

          <!-- Description -->
          <div>
            <label for="product-description" class="block text-sm font-medium text-gray-300 mb-1">
              Description
            </label>
            <textarea
              id="product-description"
              v-model="localProduct.description"
              rows="4"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            ></textarea>
          </div>

          <!-- Categories -->
          <div>
            <label for="product-categories" class="block text-sm font-medium text-gray-300 mb-1">
              Categories
            </label>
            <input
              id="product-categories"
              v-model="localProduct.categories"
              type="text"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="Comma-separated categories"
            />
            <p class="mt-1 text-xs text-gray-500">Easily filter the different items using categories, used only in the product list with cart.</p>
          </div>

          <!-- Inventory -->
          <div>
            <label for="product-inventory" class="block text-sm font-medium text-gray-300 mb-1">
              Inventory
            </label>
            <input
              id="product-inventory"
              :value="localProduct.inventory === null || localProduct.inventory === undefined ? '' : localProduct.inventory"
              type="number"
              min="0"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="Leave blank for unlimited, 0 = out of stock"
              @input="handleInventoryInput"
            />
            <p class="mt-1 text-xs text-gray-500">Leave blank to not use this feature.</p>
          </div>

          <!-- Buy Button Text -->
          <div>
            <label for="product-buy-button-text" class="block text-sm font-medium text-gray-300 mb-1">
              Buy Button Text
            </label>
            <input
              id="product-buy-button-text"
              v-model="localProduct.buyButtonText"
              type="text"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="Buy now"
            />
          </div>

          <!-- Enable toggle -->
          <div class="flex items-center bg-gray-800 p-4 rounded-xl border border-gray-700">
            <input
              id="product-enabled"
              v-model="localProduct.disabled"
              type="checkbox"
              :true-value="false"
              :false-value="true"
              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
            />
            <label for="product-enabled" class="ml-3 block text-sm font-medium text-white select-none cursor-pointer">
              Enable Product
            </label>
          </div>
        </div>
      </div>

      <!-- Drawer footer -->
      <div class="flex items-center justify-end space-x-3 px-6 py-4 border-t border-gray-700 bg-gray-800/50">
        <button
          type="button"
          @click="handleClose"
          class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="handleSave"
          class="px-6 py-2 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all hover:scale-105"
        >
          Save
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import api from '../../services/api';

const props = defineProps<{
  isOpen: boolean;
  product: any | null;
  currency: string;
  storeId: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'save', product: any): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const uploading = ref(false);

const editingProduct = computed(() => props.product !== null);

const localProduct = ref<any>({
  id: '',
  title: '',
  priceType: 'Fixed',
  price: 0,
  taxRate: null,
  image: '',
  description: '',
  categories: null,
  inventory: null,
  buyButtonText: null,
  disabled: false,
});

// Watch for product changes
watch(() => props.product, (newProduct) => {
  if (newProduct) {
    localProduct.value = { ...newProduct };
    imagePreview.value = null;
    selectedFile.value = null;
  } else {
    // Reset to default values for new product
    localProduct.value = {
      id: '',
      title: '',
      priceType: 'Fixed',
      price: 0,
      taxRate: null,
      image: '',
      description: '',
      categories: null,
      inventory: null,
      buyButtonText: null,
      disabled: false,
    };
    imagePreview.value = null;
    selectedFile.value = null;
  }
}, { immediate: true });

// Generate ID from title when user leaves the title field
function generateIdFromTitle() {
  // Only generate ID if:
  // 1. ID is empty (not manually set)
  // 2. Title is not empty
  if (!localProduct.value.id && localProduct.value.title) {
    localProduct.value.id = localProduct.value.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    selectedFile.value = file;
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
}

async function uploadImage() {
  if (!selectedFile.value) return;
  
  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('image', selectedFile.value);
    
    const response = await api.post(`/stores/${props.storeId}/products/image`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    // Set the image URL from response
    localProduct.value.image = response.data.data.url || response.data.data.image_url;
    imagePreview.value = null;
    selectedFile.value = null;
    // Reset file input
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  } catch (error: any) {
    console.error('Failed to upload image:', error);
    alert(error.response?.data?.message || 'Failed to upload image');
  } finally {
    uploading.value = false;
  }
}

function clearImage() {
  localProduct.value.image = '';
  imagePreview.value = null;
  selectedFile.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
}

function handleInventoryInput(event: Event) {
  const target = event.target as HTMLInputElement;
  const value = target.value;
  
  // Handle inventory:
  // - Empty string = unlimited (null)
  // - 0 = out of stock (keep as 0)
  // - Number > 0 = stock count
  if (value === '' || value === null) {
    localProduct.value.inventory = null;
  } else {
    const num = Number(value);
    if (!isNaN(num) && num >= 0) {
      localProduct.value.inventory = num; // Keep 0 as 0 (out of stock)
    } else {
      localProduct.value.inventory = null;
    }
  }
}

function handleClose() {
  emit('close');
}

function handleSave() {
  if (!localProduct.value.title) {
    alert('Title is required');
    return;
  }
  
  // Ensure inventory is null (not empty string or undefined) if not set
  // BUT keep 0 as 0 (out of stock), don't convert it to null
  if (localProduct.value.inventory === '' || localProduct.value.inventory === undefined) {
    localProduct.value.inventory = null;
  }
  // If inventory is 0, keep it as 0 (out of stock)
  
  emit('save', { ...localProduct.value });
  emit('close');
}
</script>

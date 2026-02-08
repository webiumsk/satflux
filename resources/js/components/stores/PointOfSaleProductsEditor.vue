<template>
  <div class="mt-6 border-t border-gray-700/50 pt-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-medium text-white">Products</h3>
      <!-- Tabs for Editor/Code view -->
      <div class="flex bg-gray-700/50 rounded-lg p-1">
        <button
          type="button"
          @click="viewMode = 'editor'"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            viewMode === 'editor' 
              ? 'bg-gray-600 text-white shadow-sm' 
              : 'text-gray-400 hover:text-white hover:bg-gray-600/50'
          ]"
        >
          Editor
        </button>
        <button
          type="button"
          @click="viewMode = 'code'"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            viewMode === 'code' 
              ? 'bg-gray-600 text-white shadow-sm' 
              : 'text-gray-400 hover:text-white hover:bg-gray-600/50'
          ]"
        >
          Code
        </button>
      </div>
    </div>

    <!-- Editor View - Compact Table List -->
    <div v-if="viewMode === 'editor'">
      <div class="bg-gray-800 shadow-sm rounded-xl border border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700/50">
              <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-10"></th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Title</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Price</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Tax</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
              <tr
                v-for="(product, index) in products"
                :key="product.id || index"
                draggable="true"
                :class="[
                  'hover:bg-gray-700/30 transition-colors',
                  draggedIndex === index ? 'opacity-50' : '',
                  dragOverIndex === index ? 'bg-indigo-900/20' : ''
                ]"
                @dragstart="handleDragStart($event, index)"
                @dragover.prevent="handleDragOver($event, index)"
                @dragenter.prevent="dragOverIndex = index"
                @dragleave="dragOverIndex = null"
                @drop="handleDrop($event, index)"
                @dragend="handleDragEnd"
                @click="$emit('edit', index)"
              >
                <td class="px-4 py-3 whitespace-nowrap">
                  <div class="flex items-center cursor-move text-gray-500 hover:text-gray-300" @click.stop @mousedown.stop>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                    </svg>
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <div class="flex items-center">
                    <div v-if="product.image" class="flex-shrink-0 h-10 w-10 mr-3">
                      <img :src="product.image" :alt="product.title" class="h-10 w-10 rounded-lg object-cover bg-gray-700" @error="product.image = ''" />
                    </div>
                    <div v-else class="flex-shrink-0 h-10 w-10 mr-3 rounded-lg bg-gray-700 flex items-center justify-center text-gray-500">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <div>
                      <div class="text-sm font-medium text-white">{{ product.title || 'Untitled' }}</div>
                      <div v-if="product.id" class="text-xs text-gray-500 font-mono">{{ product.id }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-300">
                  <span v-if="product.priceType === 'Free'" class="text-green-400">Free</span>
                  <span v-else-if="product.priceType === 'Topup'" class="text-indigo-400">Any amount</span>
                  <span v-else-if="product.priceType === 'Minimum'" class="text-indigo-400">Min. {{ product.price || 0 }} {{ currency }}</span>
                  <span v-else>{{ product.price || 0 }} {{ currency }}</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-400">
                  {{ product.taxRate !== null && product.taxRate !== undefined ? product.taxRate + '%' : '-' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span
                    :class="product.disabled ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'bg-green-500/10 text-green-400 border border-green-500/20'"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                  >
                    {{ product.disabled ? 'Disabled' : 'Enabled' }}
                  </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium" @click.stop>
                  <div class="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      @click.stop="$emit('edit', index)"
                      class="text-indigo-400 hover:text-indigo-300 p-1.5 hover:bg-indigo-500/10 rounded-lg transition-colors"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </button>
                    <button
                      type="button"
                      @click.stop="removeProduct(index)"
                      class="text-red-400 hover:text-red-300 p-1.5 hover:bg-red-500/10 rounded-lg transition-colors"
                      title="Remove"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="products.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                  <div class="flex flex-col items-center">
                    <svg class="w-10 h-10 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <p>No products yet.</p>
                    <button @click="$emit('add')" class="mt-2 text-indigo-400 hover:text-indigo-300 font-medium">Add your first item</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4">
        <button
          type="button"
          @click="$emit('add')"
          class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-gray-800 hover:bg-gray-700 hover:text-white transition-all hover:scale-105"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Item
        </button>
      </div>
    </div>

    <!-- Code View -->
    <div v-if="viewMode === 'code'">
      <div class="relative">
          <label for="products-json" class="block text-sm font-medium text-gray-300 mb-2">
            Template JSON
          </label>
          <textarea
            id="products-json"
            v-model="productsJson"
            rows="15"
            class="font-mono text-xs md:text-sm block w-full border border-gray-600 rounded-xl shadow-sm py-3 px-4 bg-gray-800 text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            @blur="parseProductsJson"
          ></textarea>
          <div v-if="jsonError" class="absolute bottom-4 left-4 right-4 bg-red-500/90 text-white px-3 py-2 rounded-lg text-xs backdrop-blur-sm">
             {{ jsonError }}
          </div>
      </div>
      <p class="mt-2 text-xs text-gray-500">
        Edit the JSON template directly. Changes are parsed when you click outside the text area or switch back to Editor view.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';

const props = defineProps<{
  products: any[];
  currency: string;
}>();

const emit = defineEmits<{
  (e: 'update:products', products: any[]): void;
  (e: 'add'): void;
  (e: 'edit', index: number): void;
}>();

const viewMode = ref<'editor' | 'code'>('editor');
const productsJson = ref('[]');
const jsonError = ref('');

// Drag & drop state
const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

// Generate Product ID helper
function generateProductId(title: string): string {
  if (!title) return '';
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// Convert products to JSON string
function productsToJson(prods: any[]): string {
  return JSON.stringify(
    prods.map(p => {
      // Handle inventory logic similar to parent
      let inventory: number | null = null;
      if (p.inventory !== null && p.inventory !== undefined && p.inventory !== '') {
        const invNum = Number(p.inventory);
        if (!isNaN(invNum) && invNum >= 0) {
          inventory = invNum;
        }
      }
      
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

// Parse JSON to products array
function parseProductsJson() {
  jsonError.value = '';
  try {
    const parsed = JSON.parse(productsJson.value);
    if (!Array.isArray(parsed)) throw new Error('Root must be an array');
    
    const newProducts = parsed.map((p: any) => {
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
    
    emit('update:products', newProducts);
  } catch (e: any) {
    jsonError.value = e.message || 'Invalid JSON';
  }
}

// Watch for prop changes to update JSON
watch(() => props.products, (newProducts) => {
  if (viewMode.value === 'editor') {
    productsJson.value = productsToJson(newProducts);
  }
}, { deep: true, immediate: true });

// Watch view mode to sync data
watch(viewMode, (newMode) => {
  if (newMode === 'editor') {
    // Sync JSON -> Products (if valid)
    parseProductsJson();
  } else {
    // Sync Products -> JSON
    productsJson.value = productsToJson(props.products);
  }
});

function removeProduct(index: number) {
  const newProducts = [...props.products];
  newProducts.splice(index, 1);
  emit('update:products', newProducts);
}

// Drag Handlers
function handleDragStart(event: DragEvent, index: number) {
  draggedIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
  }
}

function handleDragOver(event: DragEvent, index: number) {
  dragOverIndex.value = index;
}

function handleDrop(event: DragEvent, dropIndex: number) {
  if (draggedIndex.value === null) return;
  
  const newProducts = [...props.products];
  const item = newProducts.splice(draggedIndex.value, 1)[0];
  newProducts.splice(dropIndex, 0, item);
  
  emit('update:products', newProducts);
  draggedIndex.value = null;
  dragOverIndex.value = null;
}

function handleDragEnd() {
  draggedIndex.value = null;
  dragOverIndex.value = null;
}

</script>

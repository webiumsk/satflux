<template>
  <div class="relative">
    <input
      :value="name"
      type="text"
      class="invoicing-sf-input-table w-full"
      :required="required"
      :disabled="disabled"
      autocomplete="off"
      @input="onNameInput"
      @focus="onFocus"
      @blur="onBlur"
    />
    <input
      v-if="showDescription"
      :value="description"
      type="text"
      class="invoicing-sf-input-table mt-1 text-gray-500 w-full"
      :placeholder="descriptionPlaceholder"
      :disabled="disabled"
      @input="onDescriptionInput"
    />
    <p v-if="stockHint" class="text-xs text-gray-500 mt-0.5">{{ stockHint }}</p>

    <div
      v-if="enabled && showSuggestions && suggestions.length"
      class="absolute z-40 left-0 right-0 mt-1 rounded-lg border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto"
    >
      <button
        v-for="item in suggestions"
        :key="item.id"
        type="button"
        class="w-full text-left px-3 py-2 hover:bg-indigo-50 border-b border-gray-100 last:border-0"
        @mousedown.prevent="pickItem(item)"
      >
        <span class="font-medium text-gray-900 block">{{ item.name }}</span>
        <span class="text-xs text-gray-500">
          <template v-if="item.sku">SKU: {{ item.sku }}</template>
          <template v-if="item.track_inventory">
            <template v-if="item.sku"> · </template>
            {{ t('invoicing.stock_on_hand_hint', { qty: item.quantity_on_hand, unit: item.unit }) }}
          </template>
        </span>
      </button>
    </div>
    <p v-if="enabled && loading" class="text-xs text-indigo-600 mt-0.5">{{ t('common.loading') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';

export type StockSuggestItem = {
  id: string;
  name: string;
  description?: string | null;
  sku?: string | null;
  unit: string;
  sale_unit_price?: number | string | null;
  quantity_on_hand?: number | string;
  track_inventory?: boolean;
};

const props = withDefaults(
  defineProps<{
    companyId: string;
    name: string;
    description: string;
    stockItemId?: string | null;
    quantityOnHand?: number | null;
    unit?: string;
    enabled?: boolean;
    disabled?: boolean;
    required?: boolean;
    showDescription?: boolean;
    descriptionPlaceholder?: string;
  }>(),
  {
    stockItemId: null,
    quantityOnHand: null,
    unit: '',
    enabled: true,
    disabled: false,
    required: false,
    showDescription: true,
    descriptionPlaceholder: '',
  }
);

const emit = defineEmits<{
  'update:name': [value: string];
  'update:description': [value: string];
  pick: [
    payload: {
      name: string;
      description: string;
      unit: string;
      unit_price: number;
      company_stock_item_id: string;
      quantity_on_hand: number | null;
    },
  ];
  clearStockLink: [];
}>();

const { t } = useI18n();

const suggestions = ref<StockSuggestItem[]>([]);
const showSuggestions = ref(false);
const loading = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let blurTimer: ReturnType<typeof setTimeout> | null = null;

const stockHint = computed(() => {
  if (!props.stockItemId || props.quantityOnHand == null || !props.unit) return '';
  return t('invoicing.stock_on_hand_hint', { qty: props.quantityOnHand, unit: props.unit });
});

function onNameInput(e: Event) {
  const value = (e.target as HTMLInputElement).value;
  emit('update:name', value);
  if (props.stockItemId) {
    emit('clearStockLink');
  }
  scheduleSearch(value);
}

function onDescriptionInput(e: Event) {
  emit('update:description', (e.target as HTMLInputElement).value);
}

function onFocus() {
  if (blurTimer) clearTimeout(blurTimer);
  if (props.name.trim().length >= 1) {
    scheduleSearch(props.name);
  }
}

function onBlur() {
  blurTimer = setTimeout(() => {
    showSuggestions.value = false;
  }, 150);
}

function scheduleSearch(q: string) {
  if (!props.enabled || props.disabled) return;
  if (searchTimer) clearTimeout(searchTimer);
  const trimmed = q.trim();
  if (trimmed.length < 1) {
    suggestions.value = [];
    showSuggestions.value = false;
    return;
  }
  searchTimer = setTimeout(() => void fetchSuggestions(trimmed), 250);
}

async function fetchSuggestions(q: string) {
  loading.value = true;
  try {
    const { data } = await api.get(`/invoicing/companies/${props.companyId}/stock-items/search`, {
      params: { q, limit: 10 },
    });
    suggestions.value = data.data ?? [];
    showSuggestions.value = suggestions.value.length > 0;
  } catch {
    suggestions.value = [];
    showSuggestions.value = false;
  } finally {
    loading.value = false;
  }
}

function pickItem(item: StockSuggestItem) {
  emit('pick', {
    name: item.name,
    description: item.description ?? '',
    unit: item.unit || 'ks',
    unit_price: item.sale_unit_price != null ? Number(item.sale_unit_price) : 0,
    company_stock_item_id: item.id,
    quantity_on_hand: item.track_inventory ? Number(item.quantity_on_hand ?? 0) : null,
  });
  showSuggestions.value = false;
  suggestions.value = [];
}

watch(
  () => props.enabled,
  (on) => {
    if (!on) {
      suggestions.value = [];
      showSuggestions.value = false;
    }
  }
);
</script>

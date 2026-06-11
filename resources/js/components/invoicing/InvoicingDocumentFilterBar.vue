<template>
  <div
    class="invoicing-filter-bar bg-white border-b border-gray-200"
    :class="layout === 'bar' ? 'sticky top-0 z-20 hidden md:block' : ''"
  >
    <div
      :class="[
        containerClass,
        layout === 'bar'
          ? 'flex flex-wrap items-center gap-2 py-3'
          : 'invoicing-mobile-filter-stack py-1',
      ]"
    >
      <div
        :class="
          layout === 'bar'
            ? 'flex flex-wrap items-center gap-2 flex-1 min-w-0'
            : 'invoicing-mobile-filter-stack'
        "
      >
        <button
          v-for="f in statusFilters"
          :key="f.id"
          type="button"
          class="invoicing-filter"
          :class="activeFilter === f.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
          @click="$emit('filter-change', f.id)"
        >
          {{ f.label }}
        </button>

        <div ref="periodDropdownRef" class="relative">
          <button
            type="button"
            class="invoicing-filter invoicing-filter--idle inline-flex items-center gap-1.5"
            :class="{ 'invoicing-filter--active': issuePeriod.preset !== 'all' }"
            @click.stop="togglePeriodMenu"
          >
            {{ periodLabel }}
            <span class="text-[10px] opacity-70">▾</span>
          </button>
          <button
            v-if="issuePeriod.preset !== 'all'"
            type="button"
            class="ml-0.5 text-gray-400 hover:text-gray-700 text-sm leading-none px-1"
            :title="t('invoicing.period_clear')"
            @click.stop="clearPeriod"
          >
            ×
          </button>

          <div
            v-if="showPeriodMenu"
            class="invoicing-period-panel absolute left-0 top-full mt-1 z-40"
            @click.stop
          >
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 p-3 border-b border-gray-100">
              <button
                v-for="p in periodPresetsLeft"
                :key="p.id"
                type="button"
                class="invoicing-period-option"
                :class="{ 'invoicing-period-option--active': issuePeriod.preset === p.id }"
                @click="selectPeriod(p.id)"
              >
                {{ p.label }}
              </button>
              <button
                v-for="p in periodPresetsRight"
                :key="p.id"
                type="button"
                class="invoicing-period-option"
                :class="{ 'invoicing-period-option--active': issuePeriod.preset === p.id }"
                @click="selectPeriod(p.id)"
              >
                {{ p.label }}
              </button>
            </div>
            <div class="p-3 flex flex-wrap items-end gap-2">
              <div>
                <label class="text-xs text-gray-500 block mb-0.5">{{ t('invoicing.period_from') }}</label>
                <input
                  v-model="issuePeriod.customFrom"
                  type="date"
                  class="invoicing-sf-input text-sm py-1 w-[9.5rem]"
                  @change="onCustomRangeChange"
                />
              </div>
              <div>
                <label class="text-xs text-gray-500 block mb-0.5">{{ t('invoicing.period_to') }}</label>
                <input
                  v-model="issuePeriod.customTo"
                  type="date"
                  class="invoicing-sf-input text-sm py-1 w-[9.5rem]"
                  @change="onCustomRangeChange"
                />
              </div>
              <button
                type="button"
                class="invoicing-btn-secondary text-sm py-1.5 px-2"
                :title="t('invoicing.period_apply_range')"
                @click="applyCustomRange"
              >
                ›
              </button>
            </div>
          </div>
        </div>

        <button
          v-if="showAdvancedButton && layout === 'bar'"
          type="button"
          class="invoicing-filter invoicing-filter--idle"
          :class="{ 'invoicing-filter--active': showAdvanced || hasActiveAdvanced }"
          @click="toggleAdvanced"
        >
          + {{ t('invoicing.filter_more') }}
        </button>
      </div>

      <slot v-if="layout === 'bar'" name="actions" />
    </div>

    <!-- Drawer layout: advanced fields always visible -->
    <div v-if="layout === 'drawer' && showAdvancedButton" class="space-y-4 pt-2">
      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_status') }}</label>
          <select v-model="advancedDraft.status" class="invoicing-sf-input text-sm w-full">
            <option value="all">{{ t('invoicing.adv_all') }}</option>
            <option value="draft">{{ t('invoicing.status_draft') }}</option>
            <option value="issued">{{ t('invoicing.status_issued') }}</option>
            <option value="paid">{{ t('invoicing.status_paid') }}</option>
            <option value="cancelled">{{ t('invoicing.status_cancelled') }}</option>
          </select>
        </div>
        <div v-if="!isQuoteList">
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_paid') }}</label>
          <select v-model="advancedDraft.paid" class="invoicing-sf-input text-sm w-full">
            <option value="all">{{ t('invoicing.adv_all') }}</option>
            <option value="yes">{{ t('invoicing.adv_yes') }}</option>
            <option value="no">{{ t('invoicing.adv_no') }}</option>
          </select>
        </div>
        <div v-if="!isQuoteList">
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due') }}</label>
          <select v-model="advancedDraft.due" class="invoicing-sf-input text-sm w-full">
            <option value="all">{{ t('invoicing.adv_all') }}</option>
            <option value="overdue">{{ t('invoicing.filter_overdue') }}</option>
            <option value="custom">{{ t('invoicing.adv_custom_range') }}</option>
          </select>
        </div>
        <div v-if="!isQuoteList && advancedDraft.due === 'custom'">
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due_range') }}</label>
          <div class="flex flex-col gap-2">
            <input v-model="advancedDraft.dueFrom" type="date" class="invoicing-sf-input text-sm w-full" />
            <input v-model="advancedDraft.dueTo" type="date" class="invoicing-sf-input text-sm w-full" />
          </div>
        </div>
        <div>
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_amount') }}</label>
          <div class="flex gap-2">
            <input
              v-model="advancedDraft.amountMin"
              type="number"
              min="0"
              step="0.01"
              class="invoicing-sf-input text-sm flex-1"
              :placeholder="t('invoicing.adv_amount_from')"
            />
            <input
              v-model="advancedDraft.amountMax"
              type="number"
              min="0"
              step="0.01"
              class="invoicing-sf-input text-sm flex-1"
              :placeholder="t('invoicing.adv_amount_to')"
            />
          </div>
        </div>
        <div>
          <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_search') }}</label>
          <input
            v-model="advancedDraft.search"
            type="search"
            class="invoicing-sf-input text-sm w-full"
            :placeholder="t('invoicing.adv_search_ph')"
          />
        </div>
      </div>
    </div>

    <div
      v-if="showAdvanced && showAdvancedButton && layout === 'bar'"
      class="border-t border-gray-100 bg-gray-50/80"
      @click.stop
    >
      <div :class="[containerClass, 'py-4 relative']">
        <button
          type="button"
          class="absolute top-2 right-4 text-gray-400 hover:text-gray-700 text-lg leading-none"
          :title="t('common.close')"
          @click="closeAdvanced"
        >
          ×
        </button>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pr-8">
          <div>
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_status') }}</label>
            <select v-model="advancedDraft.status" class="invoicing-sf-input text-sm w-full">
              <option value="all">{{ t('invoicing.adv_all') }}</option>
              <option value="draft">{{ t('invoicing.status_draft') }}</option>
              <option value="issued">{{ t('invoicing.status_issued') }}</option>
              <option value="paid">{{ t('invoicing.status_paid') }}</option>
              <option value="cancelled">{{ t('invoicing.status_cancelled') }}</option>
            </select>
          </div>
          <div>
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_issued') }}</label>
            <div class="invoicing-sf-input text-sm w-full bg-gray-100 text-gray-600 cursor-default">
              {{ periodLabel }}
            </div>
          </div>
          <div v-if="!isQuoteList">
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_paid') }}</label>
            <select v-model="advancedDraft.paid" class="invoicing-sf-input text-sm w-full">
              <option value="all">{{ t('invoicing.adv_all') }}</option>
              <option value="yes">{{ t('invoicing.adv_yes') }}</option>
              <option value="no">{{ t('invoicing.adv_no') }}</option>
            </select>
          </div>
          <div v-if="!isQuoteList">
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due') }}</label>
            <select v-model="advancedDraft.due" class="invoicing-sf-input text-sm w-full">
              <option value="all">{{ t('invoicing.adv_all') }}</option>
              <option value="overdue">{{ t('invoicing.filter_overdue') }}</option>
              <option value="custom">{{ t('invoicing.adv_custom_range') }}</option>
            </select>
          </div>
          <div v-if="!isQuoteList && advancedDraft.due === 'custom'" class="sm:col-span-2">
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due_range') }}</label>
            <div class="flex flex-wrap gap-2">
              <input v-model="advancedDraft.dueFrom" type="date" class="invoicing-sf-input text-sm flex-1 min-w-[8rem]" />
              <input v-model="advancedDraft.dueTo" type="date" class="invoicing-sf-input text-sm flex-1 min-w-[8rem]" />
            </div>
          </div>
          <div>
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_amount') }}</label>
            <div class="flex gap-2">
              <input
                v-model="advancedDraft.amountMin"
                type="number"
                min="0"
                step="0.01"
                class="invoicing-sf-input text-sm flex-1"
                :placeholder="t('invoicing.adv_amount_from')"
              />
              <input
                v-model="advancedDraft.amountMax"
                type="number"
                min="0"
                step="0.01"
                class="invoicing-sf-input text-sm flex-1"
                :placeholder="t('invoicing.adv_amount_to')"
              />
            </div>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="invoicing-sf-label text-xs">{{ t('invoicing.adv_search') }}</label>
            <input
              v-model="advancedDraft.search"
              type="search"
              class="invoicing-sf-input text-sm w-full"
              :placeholder="t('invoicing.adv_search_ph')"
            />
          </div>
        </div>

        <div class="flex justify-end mt-4">
          <button type="button" class="invoicing-btn-primary min-w-[8rem]" @click="submitAdvanced">
            {{ t('invoicing.adv_apply') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  initCustomRangeFromPreset,
  resolveIssuePeriodRange,
  toIsoDate,
  type IssuePeriodPreset,
  type IssuePeriodState,
} from '../../composables/useInvoicingIssuePeriod';
import type { DocumentAdvancedFilters } from '../../composables/useInvoicingDocumentListFilters';

const props = withDefaults(
  defineProps<{
    containerClass: string;
    statusFilters: { id: string; label: string }[];
    activeFilter: string;
    issuePeriod: IssuePeriodState;
    advancedDraft: DocumentAdvancedFilters;
    hasActiveAdvanced: boolean;
    isQuoteList?: boolean;
    showAdvancedButton?: boolean;
    layout?: 'bar' | 'drawer';
  }>(),
  { isQuoteList: false, showAdvancedButton: true, layout: 'bar' }
);

const emit = defineEmits<{
  'filter-change': [id: string];
  'period-change': [];
  'advanced-apply': [];
  'advanced-open': [];
}>();

const { t } = useI18n();

const showPeriodMenu = ref(false);
const showAdvanced = ref(false);
const periodDropdownRef = ref<HTMLElement | null>(null);

const periodPresetsLeft = computed(() => [
  { id: 'today' as IssuePeriodPreset, label: t('invoicing.period_today') },
  { id: 'this_month' as IssuePeriodPreset, label: t('invoicing.period_this_month') },
  { id: 'this_quarter' as IssuePeriodPreset, label: t('invoicing.period_this_quarter') },
  { id: 'this_year' as IssuePeriodPreset, label: t('invoicing.period_this_year') },
]);

const periodPresetsRight = computed(() => [
  { id: 'yesterday' as IssuePeriodPreset, label: t('invoicing.period_yesterday') },
  { id: 'last_month' as IssuePeriodPreset, label: t('invoicing.period_last_month') },
  { id: 'last_quarter' as IssuePeriodPreset, label: t('invoicing.period_last_quarter') },
  { id: 'last_year' as IssuePeriodPreset, label: t('invoicing.period_last_year') },
  { id: 'all' as IssuePeriodPreset, label: t('invoicing.period_all') },
]);

const periodLabel = computed(() => {
  const key = `invoicing.period_${props.issuePeriod.preset}`;
  const translated = t(key);
  if (translated !== key) {
    if (props.issuePeriod.preset === 'custom' && props.issuePeriod.customFrom && props.issuePeriod.customTo) {
      return `${formatDisplayDate(props.issuePeriod.customFrom)} – ${formatDisplayDate(props.issuePeriod.customTo)}`;
    }
    return translated;
  }
  return t('invoicing.period_all');
});

function formatDisplayDate(iso: string) {
  const d = new Date(`${iso}T12:00:00`);
  return d.toLocaleDateString();
}

function togglePeriodMenu() {
  showPeriodMenu.value = !showPeriodMenu.value;
}

function selectPeriod(id: IssuePeriodPreset) {
  props.issuePeriod.preset = id;
  if (id !== 'custom' && id !== 'all') {
    const range = resolveIssuePeriodRange(props.issuePeriod);
    props.issuePeriod.customFrom = range.from ?? '';
    props.issuePeriod.customTo = range.to ?? '';
  }
  if (id === 'all') {
    props.issuePeriod.customFrom = '';
    props.issuePeriod.customTo = '';
  }
  showPeriodMenu.value = false;
  emit('period-change');
}

function clearPeriod() {
  props.issuePeriod.preset = 'all';
  props.issuePeriod.customFrom = '';
  props.issuePeriod.customTo = '';
  emit('period-change');
}

function onCustomRangeChange() {
  props.issuePeriod.preset = 'custom';
}

function applyCustomRange() {
  const updated = initCustomRangeFromPreset(props.issuePeriod);
  props.issuePeriod.preset = updated.preset;
  props.issuePeriod.customFrom = updated.customFrom;
  props.issuePeriod.customTo = updated.customTo;
  if (!props.issuePeriod.customFrom) {
    props.issuePeriod.customFrom = toIsoDate(new Date());
  }
  if (!props.issuePeriod.customTo) {
    props.issuePeriod.customTo = props.issuePeriod.customFrom;
  }
  showPeriodMenu.value = false;
  emit('period-change');
}

function toggleAdvanced() {
  const opening = !showAdvanced.value;
  showAdvanced.value = opening;
  if (opening) {
    emit('advanced-open');
  }
}

function closeAdvanced() {
  showAdvanced.value = false;
}

function submitAdvanced() {
  showAdvanced.value = false;
  emit('advanced-apply');
}

function onDocumentClick(event: MouseEvent) {
  if (!showPeriodMenu.value) {
    return;
  }
  const root = periodDropdownRef.value;
  const target = event.target;
  if (root && target instanceof Node && root.contains(target)) {
    return;
  }
  showPeriodMenu.value = false;
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  const range = resolveIssuePeriodRange(props.issuePeriod);
  if (range.from && !props.issuePeriod.customFrom) {
    props.issuePeriod.customFrom = range.from;
  }
  if (range.to && !props.issuePeriod.customTo) {
    props.issuePeriod.customTo = range.to;
  }
});

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick);
});

defineExpose({ closeAdvanced });
</script>

<style scoped>
.invoicing-period-panel {
  @apply bg-white border border-gray-200 rounded-lg shadow-lg min-w-[22rem];
}

.invoicing-period-option {
  @apply text-left text-sm text-gray-700 py-1 px-1 rounded hover:bg-indigo-50 hover:text-indigo-800 w-full;
}

.invoicing-period-option--active {
  @apply text-indigo-800 font-medium bg-indigo-50;
}
</style>

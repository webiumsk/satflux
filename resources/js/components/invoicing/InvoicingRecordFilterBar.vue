<template>
  <div :class="layout === 'bar' ? 'hidden md:block' : ''" @keydown="onKeydown">
    <div class="invoicing-filter-bar bg-white" :class="layout === 'bar' ? 'border-b border-gray-200' : ''">
      <div :class="layout === 'bar' ? [containerClass, 'flex flex-wrap items-center gap-2 py-3'] : 'invoicing-mobile-filter-stack'">
        <div :class="layout === 'bar' ? 'flex flex-wrap items-center gap-2 flex-1 min-w-0' : 'invoicing-mobile-filter-stack'">
          <button
            v-for="filter in statusFilters" :key="filter.id" type="button"
            class="invoicing-filter"
            :class="activeFilter === filter.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
            :aria-pressed="activeFilter === filter.id"
            @click="$emit('filter-change', filter.id)"
          >{{ filter.label }}</button>

          <div ref="periodRoot" class="relative min-w-0">
            <button
              ref="periodButton" type="button" class="invoicing-filter inline-flex items-center gap-1.5"
              :class="issuePeriod.preset !== 'all' ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
              :aria-label="`${periodFieldLabel}: ${periodLabel}`" :aria-expanded="periodOpen" :aria-controls="`${id}-period`"
              @click="periodOpen = !periodOpen"
            >{{ periodLabel }} <span aria-hidden="true" class="text-[10px]">▾</span></button>
            <div v-if="periodOpen" :id="`${id}-period`" class="record-period-panel" :class="{ 'record-period-panel--drawer': layout === 'drawer' }">
              <p class="text-xs text-gray-600 mb-2">{{ periodFieldLabel }}</p>
              <div class="grid grid-cols-2 gap-1">
                <button
                  v-for="preset in presets" :key="preset" type="button"
                  class="text-left text-sm px-2 py-1.5 rounded hover:bg-indigo-50"
                  :class="{ 'bg-indigo-50 text-indigo-800 font-medium': periodDraft.preset === preset }"
                  :aria-pressed="periodDraft.preset === preset" @click="selectPeriod(preset)"
                >{{ t(`invoicing.period_${preset}`) }}</button>
              </div>
              <div class="grid grid-cols-2 gap-2 border-t border-gray-100 mt-3 pt-3">
                <div class="min-w-0">
                  <label :for="`${id}-from`" class="invoicing-sf-label text-xs">{{ t('invoicing.period_from') }}</label>
                  <input :id="`${id}-from`" v-model="periodDraft.customFrom" type="date" class="invoicing-sf-input text-sm w-full min-w-0" @input="periodDraft.preset = 'custom'" />
                </div>
                <div class="min-w-0">
                  <label :for="`${id}-to`" class="invoicing-sf-label text-xs">{{ t('invoicing.period_to') }}</label>
                  <input :id="`${id}-to`" v-model="periodDraft.customTo" type="date" class="invoicing-sf-input text-sm w-full min-w-0" @input="periodDraft.preset = 'custom'" />
                </div>
              </div>
              <button type="button" class="invoicing-btn-secondary text-sm w-full mt-3" @click="commitPeriod">
                {{ t('invoicing.period_apply_range') }}
              </button>
            </div>
          </div>

          <button
            v-if="layout === 'bar'" ref="advancedButton" type="button" class="invoicing-filter"
            :class="advancedOpen || hasActiveAdvanced ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
            :aria-expanded="advancedOpen" :aria-controls="`${id}-advanced`" @click="toggleAdvanced"
          >+ {{ t('invoicing.filter_more') }}</button>
        </div>
        <slot v-if="layout === 'bar'" name="actions" />
      </div>
    </div>

    <form
      v-if="layout === 'drawer' || advancedOpen" :id="`${id}-advanced`"
      :class="layout === 'bar' ? 'border-b border-gray-200 bg-gray-50/80' : 'pt-3'"
      novalidate @submit.prevent="applyAdvanced"
    >
      <div :class="layout === 'bar' ? [containerClass, 'py-4'] : ''">
        <div class="grid gap-4" :class="layout === 'bar' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1'">
          <div v-if="kind === 'bank'">
            <label :for="`${id}-direction`" class="invoicing-sf-label text-xs">{{ t('invoicing.bank_filter_direction') }}</label>
            <select :id="`${id}-direction`" v-model="advancedDraft.direction" class="invoicing-sf-input text-sm w-full">
              <option value="all">{{ t('invoicing.bank_filter_direction_all') }}</option>
              <option value="credit">{{ t('invoicing.bank_filter_direction_credit') }}</option>
              <option value="debit">{{ t('invoicing.bank_filter_direction_debit') }}</option>
            </select>
          </div>
          <div v-if="kind === 'expense'">
            <label :for="`${id}-due`" class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due') }}</label>
            <select :id="`${id}-due`" v-model="advancedDraft.due" class="invoicing-sf-input text-sm w-full">
              <option value="all">{{ t('invoicing.adv_all') }}</option>
              <option value="overdue">{{ t('invoicing.filter_overdue') }}</option>
              <option value="custom">{{ t('invoicing.adv_custom_range') }}</option>
            </select>
          </div>
          <fieldset v-if="kind === 'expense' && advancedDraft.due === 'custom'" class="min-w-0">
            <legend class="invoicing-sf-label text-xs">{{ t('invoicing.adv_due_range') }}</legend>
            <div class="grid grid-cols-2 gap-2">
              <div class="min-w-0">
                <label :for="`${id}-due-from`" class="sr-only">{{ t('invoicing.period_from') }}</label>
                <input :id="`${id}-due-from`" v-model="advancedDraft.dueFrom" type="date" class="invoicing-sf-input text-sm w-full min-w-0" />
              </div>
              <div class="min-w-0">
                <label :for="`${id}-due-to`" class="sr-only">{{ t('invoicing.period_to') }}</label>
                <input :id="`${id}-due-to`" v-model="advancedDraft.dueTo" type="date" class="invoicing-sf-input text-sm w-full min-w-0" />
              </div>
            </div>
          </fieldset>
          <fieldset class="min-w-0">
            <legend class="invoicing-sf-label text-xs">{{ t(kind === 'bank' ? 'invoicing.bank_filter_amount' : 'invoicing.adv_amount') }}</legend>
            <div class="grid grid-cols-2 gap-2">
              <div class="min-w-0">
                <label :for="`${id}-amount-min`" class="sr-only">{{ t('invoicing.adv_amount_from') }}</label>
                <input :id="`${id}-amount-min`" :value="advancedDraft.amountMin" type="number" min="0" step="0.01" class="invoicing-sf-input text-sm w-full min-w-0" :placeholder="t('invoicing.adv_amount_from')" @input="setAmount('amountMin', $event)" />
              </div>
              <div class="min-w-0">
                <label :for="`${id}-amount-max`" class="sr-only">{{ t('invoicing.adv_amount_to') }}</label>
                <input :id="`${id}-amount-max`" :value="advancedDraft.amountMax" type="number" min="0" step="0.01" class="invoicing-sf-input text-sm w-full min-w-0" :placeholder="t('invoicing.adv_amount_to')" @input="setAmount('amountMax', $event)" />
              </div>
            </div>
          </fieldset>
          <div :class="layout === 'bar' ? 'sm:col-span-2 lg:col-span-3' : ''">
            <label :for="`${id}-search`" class="invoicing-sf-label text-xs">{{ t('invoicing.adv_search') }}</label>
            <input :id="`${id}-search`" v-model="advancedDraft.search" type="search" maxlength="200" class="invoicing-sf-input text-sm w-full" :placeholder="t(kind === 'expense' ? 'invoicing.expense_filter_search_placeholder' : 'invoicing.bank_filter_search_placeholder')" />
          </div>
        </div>
        <button v-if="layout === 'drawer'" type="submit" class="sr-only" tabindex="-1" aria-hidden="true">
          {{ t('invoicing.adv_apply') }}
        </button>
        <div v-if="layout === 'bar'" class="flex flex-wrap justify-end gap-2 mt-4">
          <button type="button" class="invoicing-btn-secondary" @click="closeAdvanced">{{ t('common.close') }}</button>
          <button type="submit" class="invoicing-btn-primary min-w-[8rem]">{{ t('invoicing.adv_apply') }}</button>
        </div>
      </div>
    </form>
    <p v-if="error" role="alert" class="text-sm text-red-700 py-2" :class="layout === 'bar' ? containerClass : ''">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { resolveIssuePeriodRange, type IssuePeriodPreset, type IssuePeriodState } from '../../composables/useInvoicingIssuePeriod';

const props = withDefaults(defineProps<{
  kind: 'expense' | 'bank';
  containerClass?: string;
  statusFilters: { id: string; label: string }[];
  activeFilter: string;
  issuePeriod: IssuePeriodState;
  advancedDraft: {
    search: string; amountMin: string; amountMax: string;
    due?: string; dueFrom?: string; dueTo?: string; direction?: string;
  };
  hasActiveAdvanced: boolean;
  layout?: 'bar' | 'drawer';
}>(), { containerClass: '', layout: 'bar' });
const emit = defineEmits<{
  'filter-change': [id: string];
  'period-change': [period: IssuePeriodState];
  'advanced-open': [];
  'advanced-apply': [period: IssuePeriodState];
}>();
const { t } = useI18n();
const id = `record-filter-${useId()}`;
const periodDraft = reactive<IssuePeriodState>({ ...props.issuePeriod });
const periodOpen = ref(false);
const advancedOpen = ref(false);
const error = ref('');
const invalidAmounts = reactive({ amountMin: false, amountMax: false });
const periodRoot = ref<HTMLElement | null>(null);
const periodButton = ref<HTMLButtonElement | null>(null);
const advancedButton = ref<HTMLButtonElement | null>(null);
const presets: IssuePeriodPreset[] = ['today', 'yesterday', 'this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'all'];
watch(() => props.issuePeriod, value => Object.assign(periodDraft, value), { deep: true });
const periodFieldLabel = computed(() => t(props.kind === 'expense' ? 'invoicing.filter_period_issue' : 'invoicing.filter_period_booked'));
const periodLabel = computed(() => {
  const value = props.layout === 'drawer' ? periodDraft : props.issuePeriod;
  if (value.preset === 'custom') return [value.customFrom, value.customTo].filter(Boolean).join(' - ');
  return t(`invoicing.period_${value.preset}`);
});

function validDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith('0000-')) return false;
  const date = new Date(`${value}T12:00:00Z`);
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}
function validRange(from: string, to: string): boolean {
  return (!from || validDate(from)) && (!to || validDate(to)) && (!from || !to || from <= to);
}
function cents(value: string): number | null {
  if (!/^\d+(?:\.\d{1,2})?$/.test(value)) return null;
  const [whole, fraction = ''] = value.split('.');
  const result = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  return Number.isSafeInteger(result) ? result : null;
}
function validPeriod(period: IssuePeriodState = periodDraft): boolean {
  const { from, to } = resolveIssuePeriodRange(period);
  return validRange(from ?? '', to ?? '') && (period.preset !== 'custom' || Boolean(from || to));
}
function invalid(): false { error.value = t('invoicing.filter_invalid_range'); return false; }
function setAmount(field: 'amountMin' | 'amountMax', event: Event) {
  // Vue's numeric v-model would change the shared draft field from string to number.
  if (event.target instanceof HTMLInputElement) {
    invalidAmounts[field] = event.target.validity.badInput;
    props.advancedDraft[field] = event.target.value;
  }
}
function selectPeriod(preset: IssuePeriodPreset) {
  periodDraft.preset = preset;
  const range = resolveIssuePeriodRange(periodDraft);
  periodDraft.customFrom = range.from ?? '';
  periodDraft.customTo = range.to ?? '';
  if (props.layout === 'bar') commitPeriod();
}
function commitPeriod(): boolean {
  if (!validPeriod()) return invalid();
  error.value = '';
  periodOpen.value = false;
  if (props.layout === 'bar') emit('period-change', { ...periodDraft });
  return true;
}
function toggleAdvanced() {
  advancedOpen.value = !advancedOpen.value;
  if (advancedOpen.value) emit('advanced-open');
}
function closeAdvanced() { advancedOpen.value = false; }
function resetDraft() {
  Object.assign(periodDraft, props.issuePeriod);
  invalidAmounts.amountMin = false;
  invalidAmounts.amountMax = false;
  error.value = '';
  periodOpen.value = false;
  advancedOpen.value = false;
}
function applyAdvanced(): boolean {
  const d = props.advancedDraft;
  const period = props.layout === 'drawer' ? periodDraft : props.issuePeriod;
  if (d.search.length > 200 || d.search.includes('\0') || /[\uD800-\uDFFF]/u.test(d.search)) {
    error.value = t('invoicing.filter_invalid_search');
    return false;
  }
  const minText = String(d.amountMin).trim(), maxText = String(d.amountMax).trim();
  const min = minText ? cents(minText) : undefined, max = maxText ? cents(maxText) : undefined;
  if (invalidAmounts.amountMin || invalidAmounts.amountMax || !validPeriod(period) || min === null || max === null || (min !== undefined && max !== undefined && min > max)
    || (props.kind === 'expense' && d.due === 'custom' && (!validRange(d.dueFrom ?? '', d.dueTo ?? '') || !(d.dueFrom || d.dueTo)))) return invalid();
  error.value = '';
  periodOpen.value = false;
  advancedOpen.value = false;
  emit('advanced-apply', { ...period });
  return true;
}
function onKeydown(event: KeyboardEvent) {
  if (event.key !== 'Escape' || (!periodOpen.value && !advancedOpen.value)) return;
  event.preventDefault(); event.stopPropagation();
  if (periodOpen.value) { periodOpen.value = false; periodButton.value?.focus(); }
  else { advancedOpen.value = false; advancedButton.value?.focus(); }
}
function onOutsideClick(event: MouseEvent) {
  if (event.target instanceof Node && !periodRoot.value?.contains(event.target)) periodOpen.value = false;
}
onMounted(() => document.addEventListener('click', onOutsideClick));
onUnmounted(() => document.removeEventListener('click', onOutsideClick));
defineExpose({ applyAdvanced, closeAdvanced, resetDraft });
</script>

<style scoped>
.record-period-panel {
  @apply absolute left-0 top-full mt-1 z-40 bg-white border border-gray-200 rounded-lg shadow-lg p-3;
  width: min(22rem, calc(100vw - 2rem));
}
.record-period-panel--drawer {
  position: static;
  width: 100%;
  box-shadow: none;
}
</style>

<template>
  <div class="expense-table-wrap border-b border-gray-200 overflow-x-auto hidden md:block">
    <table class="expense-table w-full min-w-[1024px] text-sm text-left">
      <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide border-b border-gray-200">
        <tr>
          <th class="w-12 px-2 py-3 relative text-center">
            <button
              type="button"
              class="expense-select-trigger text-base leading-none px-1.5 py-1 rounded"
              :class="selectionCount > 0 ? 'expense-select-trigger--active' : 'text-gray-500 hover:text-gray-800'"
              :title="t('invoicing.select_menu')"
              @click.stop="$emit('toggle-header-menu')"
            >
              ☰
            </button>
            <div
              v-if="showHeaderMenu"
              class="invoicing-dropdown min-w-[240px] text-xs normal-case tracking-normal font-normal left-0"
              @click.stop
            >
              <template v-if="selectionCount > 0">
                <button type="button" class="invoicing-dropdown-item text-gray-600" @click="$emit('clear-selection')">
                  − {{ t('invoicing.bulk_clear') }}
                </button>
                <div class="border-t border-gray-100 my-1"></div>
                <button type="button" class="invoicing-dropdown-item" @click="$emit('run-bulk', 'mark_paid')">
                  € {{ t('invoicing.expense_bulk_mark_paid') }}
                </button>
                <button type="button" class="invoicing-dropdown-item" @click="$emit('run-bulk', 'export_xlsx')">
                  {{ t('invoicing.bulk_export_xlsx') }}
                </button>
                <button type="button" class="invoicing-dropdown-item" @click="$emit('run-bulk', 'attachments_zip')">
                  ↓ {{ t('invoicing.expense_bulk_attachments_zip') }}
                </button>
                <div class="border-t border-gray-100 my-1"></div>
                <button
                  type="button"
                  class="invoicing-dropdown-item text-red-600"
                  @click="$emit('run-bulk', 'cancel')"
                >
                  {{ t('invoicing.expense_bulk_delete') }}
                </button>
              </template>
              <template v-else>
                <button type="button" class="invoicing-dropdown-item" @click="$emit('select-page')">
                  ✓ {{ t('invoicing.select_page', { count: rows.length }) }}
                </button>
                <button type="button" class="invoicing-dropdown-item" @click="$emit('select-all')">
                  ✓✓ {{ t('invoicing.select_all_filtered', { count: totalCount }) }}
                </button>
              </template>
            </div>
          </th>
          <th class="w-4 px-0 py-3" aria-hidden="true"></th>
          <th class="px-4 py-3 min-w-[180px]">{{ t('invoicing.expense_col_name_desc') }}</th>
          <th class="px-4 py-3 min-w-[100px]">{{ t('invoicing.expense_col_category') }}</th>
          <th class="px-4 py-3 min-w-[140px]">{{ t('invoicing.expense_col_supplier') }}</th>
          <th class="px-4 py-3 min-w-[110px]">{{ t('invoicing.expense_col_vs_doc') }}</th>
          <th class="px-4 py-3 text-right min-w-[96px]">{{ t('invoicing.expense_col_total') }}</th>
          <th class="px-4 py-3 text-right min-w-[130px]">{{ t('invoicing.expense_col_dates_block') }}</th>
          <th class="px-4 py-3 text-right min-w-[140px]">{{ t('invoicing.expense_col_due') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          class="expense-row border-t border-gray-100 group transition-colors"
          :class="isSelected(row.id) ? 'bg-indigo-50' : 'bg-white hover:bg-indigo-50/60 hover:border-b border-gray-200'"
        >
          <td class="px-2 py-3 align-middle" @click.stop>
            <input
              type="checkbox"
              class="rounded border-gray-300"
              :checked="isSelected(row.id)"
              @change="$emit('toggle-row', row.id)"
            />
          </td>

          <td class="relative w-4 p-0 align-stretch cursor-pointer" @click="$emit('open', row.id)">
            <span
              class="status-corner"
              :class="expenseStatusCornerClass(row)"
              :title="statusTitle(row)"
            ></span>
          </td>

          <td class="px-4 py-3 align-middle cursor-pointer" @click="$emit('open', row.id)">
            <div class="flex items-start gap-2 min-w-0">
              <span
                v-if="row.has_attachment"
                class="expense-attach-icon shrink-0 mt-0.5"
                :title="t('invoicing.expense_list_has_attachment')"
              >
                <IconPaperclip icon-class="w-[15px] h-[15px] -rotate-45" :stroke-width="1.75" />
              </span>
              <div class="min-w-0">
                <span class="font-semibold text-indigo-600 group-hover:text-indigo-800 group-hover:underline">
                  {{ displayTitle(row) }}
                </span>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                  {{ row.internal_number }}
                </p>
              </div>
            </div>
          </td>

          <td class="px-4 py-3 text-gray-600 align-middle cursor-pointer" @click="$emit('open', row.id)">
            {{ metaFor(row).category || '—' }}
          </td>

          <td class="px-4 py-3 align-middle cursor-pointer" @click="$emit('open', row.id)">
            <span class="text-indigo-600 group-hover:underline">{{ metaFor(row).supplier || '—' }}</span>
          </td>

          <td class="px-4 py-3 text-gray-700 align-middle text-xs leading-relaxed cursor-pointer" @click="$emit('open', row.id)">
            <div>{{ row.variable_symbol || '—' }}</div>
            <div class="text-gray-500">{{ row.external_number || '—' }}</div>
          </td>

          <td
            class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap align-middle cursor-pointer"
            @click="$emit('open', row.id)"
          >
            {{ formatMoney(row.total, row.currency) }}
          </td>

          <td
            class="px-4 py-3 text-right text-xs text-gray-600 leading-relaxed align-middle whitespace-nowrap cursor-pointer"
            @click="$emit('open', row.id)"
          >
            <div>{{ formatDate(row.issue_date) }}</div>
            <div>{{ formatDate(row.delivery_date || row.issue_date) }}</div>
            <div :class="row.status === 'paid' ? 'text-emerald-700 font-medium' : 'text-gray-400 italic'">
              {{ paidLine(row) }}
            </div>
          </td>

          <td class="px-4 py-3 text-right align-middle whitespace-nowrap relative">
            <div
              class="cursor-pointer"
              :class="row.is_overdue ? 'text-red-600 font-medium' : 'text-gray-700'"
              @click="$emit('open', row.id)"
            >
              {{ row.due_date ? formatDate(row.due_date) : '—' }}
            </div>
            <div
              v-if="showOverdueDays(row)"
              class="text-xs text-red-500 mt-0.5 cursor-pointer"
              @click="$emit('open', row.id)"
            >
              {{ t('invoicing.expense_overdue_days', { count: overdueDays(row.due_date) }) }}
            </div>

            <div
              v-if="row.status !== 'cancelled'"
              class="row-actions-bar flex items-center justify-end gap-0.5 min-h-[52px] pr-2 pl-3 rounded-lg transition-opacity"
              :class="
                actionId === row.id
                  ? 'opacity-100'
                  : 'opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto'
              "
            >
              <button
                v-if="row.status === 'recorded'"
                type="button"
                class="row-action-btn"
                :title="t('invoicing.action_mark_paid')"
                :disabled="actionId === row.id"
                @click.stop="$emit('mark-paid', row.id)"
              >
                <span class="text-sm font-bold">€</span>
              </button>
              <button
                v-if="row.has_attachment"
                type="button"
                class="row-action-btn"
                :title="t('invoicing.expense_attachment_view')"
                :disabled="actionId === row.id"
                @click.stop="$emit('open-attachment', row.id)"
              >
                <IconPaperclip />
              </button>
              <button
                type="button"
                class="row-action-btn"
                :title="t('invoicing.expense_action_duplicate')"
                :disabled="actionId === row.id"
                @click.stop="$emit('duplicate', row.id)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                  />
                </svg>
              </button>
              <RouterLink
                :to="editTo(row.id)"
                class="row-action-btn"
                :title="t('common.edit')"
                @click.stop
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                  />
                </svg>
              </RouterLink>
              <button
                type="button"
                class="row-action-btn row-action-btn--danger"
                :title="t('invoicing.expense_action_cancel')"
                :disabled="actionId === row.id"
                @click.stop="$emit('cancel', row.id)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="md:hidden divide-y divide-gray-100">
    <InvoicingMobileCard
      v-for="row in rows"
      :key="row.id"
      selectable
      :selected="isSelected(row.id)"
      @open="$emit('open', row.id)"
      @toggle-select="$emit('toggle-row', row.id)"
    >
      <div class="relative pl-3">
        <span class="status-corner absolute left-0 top-0" :class="expenseStatusCornerClass(row)" />
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-semibold text-gray-900 truncate">{{ displayTitle(row) }}</p>
            <p class="text-sm text-gray-600 truncate">{{ metaFor(row).supplier || '—' }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ formatDate(row.issue_date) }}</p>
          </div>
          <p class="text-sm font-semibold shrink-0">{{ formatMoney(row.total, row.currency) }}</p>
        </div>
      </div>
      <template #actions>
        <InvoicingRowActionsMenu>
          <button
            v-if="row.status === 'recorded'"
            type="button"
            class="invoicing-dropdown-item"
            @click="$emit('mark-paid', row.id)"
          >
            {{ t('invoicing.action_mark_paid') }}
          </button>
          <button
            v-if="row.has_attachment && row.status !== 'cancelled'"
            type="button"
            class="invoicing-dropdown-item"
            @click="$emit('open-attachment', row.id)"
          >
            {{ t('invoicing.expense_attachment_view') }}
          </button>
          <template v-if="row.status !== 'cancelled'">
            <RouterLink :to="editTo(row.id)" class="invoicing-dropdown-item block">{{ t('common.edit') }}</RouterLink>
            <button type="button" class="invoicing-dropdown-item" @click="$emit('duplicate', row.id)">
              {{ t('invoicing.expense_action_duplicate') }}
            </button>
            <button type="button" class="invoicing-dropdown-item text-red-600" @click="$emit('cancel', row.id)">
              {{ t('invoicing.expense_action_cancel') }}
            </button>
          </template>
        </InvoicingRowActionsMenu>
      </template>
    </InvoicingMobileCard>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import IconPaperclip from './icons/IconPaperclip.vue';
import InvoicingMobileCard from './InvoicingMobileCard.vue';
import InvoicingRowActionsMenu from './InvoicingRowActionsMenu.vue';
import {
  expenseOverdueDays,
  expenseStatusCornerClass,
  parseExpenseRowMeta,
  type ExpenseListRow,
} from '../../composables/useExpenseRowMeta';

const props = defineProps<{
  rows: ExpenseListRow[];
  companyId: string;
  selectedIds: string[];
  selectAllMode: boolean;
  selectionCount: number;
  totalCount: number;
  showHeaderMenu: boolean;
  actionId: string | null;
}>();

defineEmits<{
  open: [id: string];
  'toggle-row': [id: string];
  'toggle-header-menu': [];
  'select-page': [];
  'select-all': [];
  'clear-selection': [];
  'run-bulk': [action: string];
  'mark-paid': [id: string];
  duplicate: [id: string];
  cancel: [id: string];
  'open-attachment': [id: string];
}>();

const { t } = useI18n();

const metaCache = new Map<string, ReturnType<typeof parseExpenseRowMeta>>();

function isSelected(id: string) {
  if (props.selectAllMode) return true;

  return props.selectedIds.includes(id);
}

function editTo(expenseId: string) {
  return { name: 'invoicing-expense-edit', params: { companyId: props.companyId, expenseId } };
}

function metaFor(row: ExpenseListRow) {
  if (!metaCache.has(row.id)) {
    metaCache.set(row.id, parseExpenseRowMeta(row));
  }

  return metaCache.get(row.id)!;
}

function displayTitle(row: ExpenseListRow) {
  const title = row.title?.trim();
  if (!title) return row.internal_number;
  if (title.includes(' - ')) {
    return title.split(' - ')[0].trim();
  }

  return title;
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;

  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' }).format(n || 0);
}

function formatDate(iso: string | null | undefined) {
  if (!iso) return '—';
  const d = iso.slice(0, 10);
  const [y, m, day] = d.split('-');

  return `${day}.${m}.${y}`;
}

function paidLine(row: ExpenseListRow) {
  if (row.status === 'paid') {
    const date = row.paid_at ? formatDate(row.paid_at) : '';

    return date ? `(${date})` : `(${t('invoicing.adv_paid')})`;
  }

  if (row.status === 'cancelled') {
    return `(${t('invoicing.expense_status_cancelled')})`;
  }

  return `(${t('invoicing.expense_not_paid_yet')})`;
}

function overdueDays(dueDate: string | null | undefined) {
  return expenseOverdueDays(dueDate);
}

function showOverdueDays(row: ExpenseListRow) {
  return row.status === 'recorded' && overdueDays(row.due_date) !== null;
}

function statusTitle(row: ExpenseListRow) {
  if (row.status === 'paid') return t('invoicing.adv_paid');
  if (row.is_overdue) return t('invoicing.expense_filter_overdue');

  return t('invoicing.expense_filter_unpaid');
}
</script>

<style scoped>
.expense-select-trigger--active {
  @apply bg-amber-500 text-white hover:bg-amber-600;
}

.expense-attach-icon {
  @apply text-gray-400 inline-flex items-center justify-center;
}

.expense-row:hover .expense-attach-icon {
  @apply text-indigo-500;
}

.expense-table tbody td:last-child {
  overflow: visible;
}

.row-actions-bar {
  @apply bg-white shadow-md border-l border-r border-b border-gray-200;
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
}

.row-action-btn {
  @apply p-2 text-black/90 hover:text-white hover:bg-indigo-500/80 rounded transition-colors inline-flex items-center justify-center;
}

.row-action-btn--danger {
  @apply hover:bg-red-500/80;
}

.status-corner {
  position: absolute;
  top: 0;
  left: 0;
  width: 14px;
  height: 14px;
  clip-path: polygon(0 0, 100% 0, 0 100%);
}

.status-corner--paid {
  background-color: #22c55e;
}

.status-corner--waiting {
  background-color: #eab308;
}

.status-corner--overdue {
  background-color: #ef4444;
}

.status-corner--cancelled {
  background-color: #9ca3af;
  opacity: 0.7;
}
</style>

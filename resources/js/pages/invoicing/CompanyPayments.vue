<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader
        :show-filter-bar="activeTab === 'transactions' && hasBankAccount"
        :show-mobile-filters="activeTab === 'transactions' && hasBankAccount"
        :mobile-filter-active-count="matchFilter !== 'all' ? 1 : 0"
        @mobile-filter-apply="load"
        @mobile-filter-clear="
          matchFilter = 'all';
          load();
        "
      >
        <template #filters>
          <select
            v-model="matchFilter"
            class="invoicing-sf-input max-w-[180px]"
            @change="load"
          >
            <option value="all">{{ t("invoicing.bank_filter_all") }}</option>
            <option value="unmatched">
              {{ t("invoicing.bank_filter_unmatched") }}
            </option>
            <option value="matched">
              {{ t("invoicing.bank_filter_matched") }}
            </option>
            <option value="ignored">
              {{ t("invoicing.bank_filter_ignored") }}
            </option>
          </select>
        </template>
        <template #actions>
          <button
            type="button"
            class="invoicing-btn-secondary"
            @click="activeTab = 'import'"
          >
            {{ t("invoicing.bank_tab_import") }}
          </button>
        </template>
        <template #mobile-filters>
          <select
            v-model="matchFilter"
            class="invoicing-sf-input w-full"
            @change="load"
          >
            <option value="all">{{ t("invoicing.bank_filter_all") }}</option>
            <option value="unmatched">
              {{ t("invoicing.bank_filter_unmatched") }}
            </option>
            <option value="matched">
              {{ t("invoicing.bank_filter_matched") }}
            </option>
            <option value="ignored">
              {{ t("invoicing.bank_filter_ignored") }}
            </option>
          </select>
        </template>
        <template #mobile-actions>
          <button
            type="button"
            class="invoicing-btn-secondary w-full"
            @click="activeTab = 'import'"
          >
            {{ t("invoicing.bank_tab_import") }}
          </button>
        </template>
      </InvoicingAppHeader>
    </template>

    <div v-if="!summaryLoaded" class="invoicing-muted py-8">
      {{ t("common.loading") }}
    </div>

    <div v-else-if="!hasBankAccount" class="invoicing-card-pad max-w-xl">
      <h1 class="invoicing-title mb-2">
        {{ t("invoicing.main_nav_payments") }}
      </h1>
      <p class="text-sm text-gray-600 mb-4">
        {{ t("invoicing.bank_no_bank_account") }}
      </p>
      <RouterLink
        :to="{ name: 'invoicing-company', params: { companyId: companyId } }"
        class="invoicing-btn-primary inline-flex"
      >
        {{ t("invoicing.bank_setup_bank_account") }}
      </RouterLink>
    </div>

    <template v-else>
      <h1 class="invoicing-title mb-4">
        {{ t("invoicing.main_nav_payments") }}
      </h1>

      <div
        class="flex gap-2 mb-4 border-b border-gray-200 overflow-x-auto flex-nowrap md:flex-wrap"
      >
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium"
          :class="
            activeTab === 'transactions'
              ? 'border-b-2 border-indigo-600 text-indigo-700'
              : 'text-gray-600'
          "
          @click="activeTab = 'transactions'"
        >
          {{ t("invoicing.bank_tab_transactions") }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium"
          :class="
            activeTab === 'import'
              ? 'border-b-2 border-indigo-600 text-indigo-700'
              : 'text-gray-600'
          "
          @click="activeTab = 'import'"
        >
          {{ t("invoicing.bank_tab_import") }}
        </button>
      </div>

      <div
        v-if="activeTab === 'import'"
        class="invoicing-card-pad space-y-4 max-w-xl"
      >
        <p class="text-sm text-gray-600">
          {{ t("invoicing.bank_import_help") }}
        </p>
        <form class="space-y-3" @submit.prevent="upload">
          <input
            type="file"
            accept=".csv,.txt,.xml"
            class="block w-full text-sm"
            @change="onFile"
          />
          <select v-model="importFormat" class="invoicing-sf-input w-full">
            <option value="">{{ t("invoicing.bank_format_auto") }}</option>
            <option value="csv">CSV</option>
            <option value="wise">Wise CSV</option>
            <option value="camt053">CAMT.053 XML</option>
          </select>
          <button
            type="submit"
            class="invoicing-btn-primary"
            :disabled="!importFile || importing"
          >
            {{
              importing
                ? t("common.loading")
                : t("invoicing.bank_import_submit")
            }}
          </button>
        </form>
        <div v-if="hasBankAccount" class="text-sm border-t pt-4">
          <p class="font-medium text-gray-800">
            {{ t("invoicing.bank_inbound_title") }}
          </p>
          <p v-if="bank.localFirst" class="text-gray-600 text-xs mt-1">
            {{ t("invoicing.bank_inbound_local_first_hint") }}
          </p>
          <p v-if="inboundBridgeMissing" class="text-amber-700 text-xs mt-2">
            {{ t("invoicing.bank_inbound_bridge_missing") }}
          </p>
          <template v-else-if="inboundEmail">
            <p class="text-gray-600 mt-1">
              {{
                t("invoicing.bank_inbound_help", {
                  max: inboundMaxLength || 50,
                })
              }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <code
                class="flex-1 min-w-0 p-2 bg-gray-100 rounded text-xs break-all"
                >{{ inboundEmail }}</code
              >
              <button
                type="button"
                class="invoicing-btn-secondary text-xs shrink-0"
                @click="copyInboundEmail"
              >
                <span aria-live="polite" aria-atomic="true">
                  {{
                    inboundCopied
                      ? t("invoicing.bank_inbound_copied")
                      : t("invoicing.bank_inbound_copy")
                  }}
                </span>
              </button>
            </div>
            <p v-if="inboundMaxLength" class="text-gray-500 text-xs mt-1">
              {{
                t("invoicing.bank_inbound_length", {
                  current: inboundLength,
                  max: inboundMaxLength,
                })
              }}
            </p>
            <p v-if="!inboundEnabled" class="text-amber-700 text-xs mt-1">
              {{ t("invoicing.bank_inbound_disabled") }}
            </p>
          </template>
        </div>
        <div v-if="hasBankAccount" class="text-sm border-t pt-4 space-y-3">
          <p class="font-medium text-gray-800">
            {{ t("invoicing.bank_wise_title") }}
          </p>
          <p class="text-gray-600 text-xs">
            {{ t("invoicing.bank_wise_help") }}
          </p>
          <p v-if="bank.localFirst" class="text-gray-600 text-xs">
            {{ t("invoicing.bank_wise_local_first_hint") }}
          </p>
          <p v-if="wiseBridgeMissing" class="text-amber-700 text-xs">
            {{ t("invoicing.bank_wise_bridge_missing") }}
          </p>
          <template v-else>
            <p v-if="wiseStatus?.connected" class="text-green-700 text-xs">
              {{ t("invoicing.bank_wise_connected") }}
              <span v-if="wiseStatus?.last_sync_at">
                ·
                {{
                  t("invoicing.bank_wise_last_sync", {
                    at: formatWiseSyncAt(wiseStatus.last_sync_at),
                  })
                }}
              </span>
            </p>
            <form
              v-if="!wiseStatus?.connected"
              class="space-y-2"
              @submit.prevent="connectWise"
            >
              <label class="block text-xs text-gray-600" for="wise-api-token">
                {{ t("invoicing.bank_wise_token_label") }}
              </label>
              <input
                id="wise-api-token"
                v-model="wiseToken"
                type="password"
                autocomplete="off"
                class="invoicing-sf-input w-full"
                :placeholder="t('invoicing.bank_wise_token_placeholder')"
              />
              <p class="text-gray-500 text-xs">
                {{ t("invoicing.bank_wise_token_hint") }}
              </p>
              <button
                type="submit"
                class="invoicing-btn-secondary text-xs"
                :disabled="!wiseToken.trim() || wiseConnecting"
              >
                {{
                  wiseConnecting
                    ? t("common.loading")
                    : t("invoicing.bank_wise_connect")
                }}
              </button>
            </form>
            <div v-else class="flex flex-wrap gap-2">
              <button
                type="button"
                class="invoicing-btn-primary text-xs"
                :disabled="wiseSyncing"
                @click="syncWise"
              >
                {{
                  wiseSyncing
                    ? t("common.loading")
                    : t("invoicing.bank_wise_sync")
                }}
              </button>
              <button
                type="button"
                class="invoicing-btn-secondary text-xs"
                :disabled="wiseConnecting"
                @click="showWiseReconnect = true"
              >
                {{ t("invoicing.bank_wise_reconnect") }}
              </button>
            </div>
            <form
              v-if="showWiseReconnect"
              class="space-y-2 border-t pt-3"
              @submit.prevent="connectWise"
            >
              <input
                v-model="wiseToken"
                type="password"
                autocomplete="off"
                class="invoicing-sf-input w-full"
                :placeholder="t('invoicing.bank_wise_token_placeholder')"
              />
              <button
                type="submit"
                class="invoicing-btn-secondary text-xs"
                :disabled="!wiseToken.trim() || wiseConnecting"
              >
                {{
                  wiseConnecting
                    ? t("common.loading")
                    : t("invoicing.bank_wise_connect")
                }}
              </button>
            </form>
            <p v-if="wiseSyncResult" class="text-green-700 text-xs">
              {{ t("invoicing.bank_import_result", wiseSyncResult) }}
            </p>
          </template>
        </div>
        <ul v-if="batches.length" class="text-sm space-y-2 border-t pt-4">
          <li
            v-for="b in batches"
            :key="b.id"
            class="flex justify-between gap-2"
          >
            <span
              >{{ b.filename || b.source }} · {{ b.imported_count }}/{{
                b.row_count
              }}</span
            >
            <button
              v-if="b.imported_count > 0"
              type="button"
              class="text-indigo-600 hover:underline"
              @click="autoMatchBatch(b.id)"
            >
              {{ t("invoicing.bank_auto_match_batch") }}
            </button>
          </li>
        </ul>
        <p v-if="importResult" class="text-sm text-green-700">
          {{ t("invoicing.bank_import_result", importResult) }}
        </p>
      </div>

      <div v-else>
        <div v-if="loading" class="invoicing-muted py-8">
          {{ t("common.loading") }}
        </div>
        <template v-else>
          <div
            v-if="transactions.length === 0"
            class="invoicing-card-pad text-center text-gray-600 mb-6"
          >
            {{ t("invoicing.bank_no_transactions") }}
          </div>
          <div v-else class="invoicing-card overflow-hidden mb-6">
            <div class="hidden md:block overflow-x-auto">
              <table class="w-full text-sm bank-movements-table">
                <thead
                  class="bg-gray-50 border-b text-gray-600 text-xs uppercase tracking-wide"
                >
                  <tr>
                    <th class="w-3 p-0"></th>
                    <th class="text-left p-3">
                      {{ t("invoicing.bank_col_amount_date") }}
                    </th>
                    <th class="text-left p-3">
                      {{ t("invoicing.bank_col_vs") }}
                    </th>
                    <th class="text-left p-3">
                      {{ t("invoicing.bank_col_account") }}
                    </th>
                    <th class="text-left p-3">
                      {{ t("invoicing.bank_col_counterparty") }}
                    </th>
                    <th class="text-left p-3">
                      {{ t("invoicing.bank_col_document") }}
                    </th>
                    <th class="p-3 text-right">
                      {{ t("invoicing.bank_col_actions") }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="border-b hover:bg-gray-50 bank-movement-row"
                    :class="
                      tx.direction === 'credit'
                        ? 'bank-movement-row--credit'
                        : 'bank-movement-row--debit'
                    "
                  >
                    <td class="relative w-3 p-0 align-stretch">
                      <span
                        class="status-corner"
                        :class="pairingCornerClass(tx)"
                      ></span>
                    </td>
                    <td class="p-3 whitespace-nowrap">
                      <div
                        class="font-semibold tabular-nums"
                        :class="
                          tx.direction === 'credit'
                            ? 'text-emerald-700'
                            : 'text-red-600'
                        "
                      >
                        {{ formatSignedAmount(tx) }}
                      </div>
                      <div class="text-xs text-gray-500 mt-0.5">
                        {{ formatDate(tx.booked_at) }}
                      </div>
                    </td>
                    <td class="p-3">{{ tx.variable_symbol || "-" }}</td>
                    <td class="p-3 whitespace-nowrap text-gray-600">
                      {{ bankAccountLabel || "-" }}
                    </td>
                    <td class="p-3 max-w-[240px]">
                      <div class="truncate">{{ counterpartyDisplay(tx) }}</div>
                      <div
                        v-if="counterpartySubtext(tx)"
                        class="text-xs text-gray-500 truncate mt-0.5"
                      >
                        {{ counterpartySubtext(tx) }}
                      </div>
                    </td>
                    <td class="p-3">
                      <RouterLink
                        v-if="linkedDocument(tx)"
                        :to="documentShowTo(linkedDocument(tx)!)"
                        class="text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1"
                      >
                        {{ linkedDocument(tx)!.number }}
                        <span aria-hidden="true">↗</span>
                      </RouterLink>
                      <RouterLink
                        v-else-if="linkedExpense(tx)"
                        :to="{
                          name: 'invoicing-expense-show',
                          params: {
                            companyId,
                            expenseId: linkedExpense(tx)!.id,
                          },
                        }"
                        class="text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1"
                      >
                        {{ linkedExpense(tx)!.internal_number }}
                        <span aria-hidden="true">↗</span>
                      </RouterLink>
                      <span
                        v-else-if="tx.match_status === 'ignored'"
                        class="text-gray-500"
                      >
                        {{ t("invoicing.bank_status_ignored") }}
                      </span>
                      <span v-else class="text-gray-400">-</span>
                    </td>
                    <td class="p-3 text-right space-x-2 whitespace-nowrap">
                      <template v-if="tx.match_status === 'unmatched'">
                        <button
                          type="button"
                          class="bank-action-link"
                          @click="ignoreTx(tx)"
                        >
                          {{ t("invoicing.bank_ignore") }}
                        </button>
                        <button
                          v-if="tx.direction === 'debit'"
                          type="button"
                          class="bank-action-link"
                          @click="openCreateExpense(tx)"
                        >
                          {{ t("invoicing.bank_create_expense") }}
                        </button>
                        <button
                          v-if="tx.direction === 'credit'"
                          type="button"
                          class="bank-action-link bank-action-link--primary"
                          @click="openMatch(tx)"
                        >
                          {{ t("invoicing.bank_match") }}
                        </button>
                      </template>
                      <button
                        v-else-if="tx.match_status === 'matched'"
                        type="button"
                        class="bank-action-link"
                        @click="unmatchTx(tx)"
                      >
                        {{ t("invoicing.bank_unmatch") }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="md:hidden divide-y divide-gray-100">
              <InvoicingMobileCard v-for="tx in transactions" :key="tx.id">
                <div class="relative pl-3">
                  <span
                    class="status-corner absolute left-0 top-0"
                    :class="pairingCornerClass(tx)"
                  />
                  <div class="flex justify-between gap-2">
                    <div class="min-w-0">
                      <p
                        class="font-semibold tabular-nums"
                        :class="
                          tx.direction === 'credit'
                            ? 'text-emerald-700'
                            : 'text-red-600'
                        "
                      >
                        {{ formatSignedAmount(tx) }}
                      </p>
                      <p class="text-xs text-gray-500">
                        {{ formatDate(tx.booked_at) }}
                      </p>
                      <p class="text-sm text-gray-700 truncate mt-1">
                        {{ counterpartyDisplay(tx) }}
                      </p>
                    </div>
                  </div>
                </div>
                <template #actions>
                  <InvoicingRowActionsMenu>
                    <button
                      v-if="tx.match_status === 'unmatched'"
                      type="button"
                      class="invoicing-dropdown-item"
                      @click="ignoreTx(tx)"
                    >
                      {{ t("invoicing.bank_ignore") }}
                    </button>
                    <button
                      v-if="
                        tx.match_status === 'unmatched' &&
                        tx.direction === 'credit'
                      "
                      type="button"
                      class="invoicing-dropdown-item"
                      @click="openMatch(tx)"
                    >
                      {{ t("invoicing.bank_match") }}
                    </button>
                    <button
                      v-if="
                        tx.match_status === 'unmatched' &&
                        tx.direction === 'debit'
                      "
                      type="button"
                      class="invoicing-dropdown-item"
                      @click="openCreateExpense(tx)"
                    >
                      {{ t("invoicing.bank_create_expense") }}
                    </button>
                    <button
                      v-if="tx.match_status === 'matched'"
                      type="button"
                      class="invoicing-dropdown-item"
                      @click="unmatchTx(tx)"
                    >
                      {{ t("invoicing.bank_unmatch") }}
                    </button>
                  </InvoicingRowActionsMenu>
                </template>
              </InvoicingMobileCard>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="invoicing-card-pad">
              <h3
                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3"
              >
                {{ t("invoicing.legend_title") }}
              </h3>
              <ul class="space-y-2 text-sm text-gray-700">
                <li
                  v-for="item in legendItems"
                  :key="item.kind"
                  class="flex items-center gap-3"
                >
                  <span
                    class="status-corner shrink-0 static"
                    :class="`status-corner--${item.kind}`"
                  ></span>
                  <span>{{ item.label }}</span>
                </li>
              </ul>
            </div>

            <div class="invoicing-card-pad">
              <h3
                v-if="bankAccountLabel && summarySections.length <= 1"
                class="text-sm font-semibold text-gray-800 mb-3"
              >
                {{ bankAccountLabel }}
              </h3>
              <div
                v-for="(section, index) in summarySections"
                :key="section.currency"
                :class="{ 'mt-4 pt-4 border-t': index > 0 }"
              >
                <h4
                  v-if="summarySections.length > 1"
                  class="text-sm font-semibold text-gray-800 mb-2"
                >
                  {{
                    bankAccountLabel
                      ? `${bankAccountLabel} · ${section.currency}`
                      : section.currency
                  }}
                </h4>
                <dl class="space-y-2 text-sm">
                  <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">
                      {{ t("invoicing.bank_summary_credit") }}
                    </dt>
                    <dd
                      class="text-emerald-700 font-medium tabular-nums text-right"
                    >
                      {{ section.credit_count }}
                      ({{
                        formatSummaryAmount(
                          section.credit_total,
                          section.currency,
                        )
                      }})
                    </dd>
                  </div>
                  <div class="flex justify-between gap-4">
                    <dt class="text-gray-600">
                      {{ t("invoicing.bank_summary_debit") }}
                    </dt>
                    <dd
                      class="text-red-600 font-medium tabular-nums text-right"
                    >
                      {{ section.debit_count }}
                      ({{
                        formatSummaryAmount(
                          section.debit_total,
                          section.currency,
                          true,
                        )
                      }})
                    </dd>
                  </div>
                  <div class="flex justify-between gap-4 border-t pt-2">
                    <dt class="text-gray-800 font-medium">
                      {{ t("invoicing.bank_summary_balance") }}
                    </dt>
                    <dd
                      class="font-semibold tabular-nums text-right"
                      :class="
                        parseFloat(section.balance) >= 0
                          ? 'text-emerald-700'
                          : 'text-red-600'
                      "
                    >
                      {{
                        formatSummaryAmount(
                          section.balance,
                          section.currency,
                          parseFloat(section.balance) < 0,
                        )
                      }}
                    </dd>
                  </div>
                  <div
                    v-if="accountBalanceFor(section.currency)"
                    class="flex justify-between gap-4 border-t pt-2"
                  >
                    <dt class="text-gray-800 font-medium">
                      {{ t("invoicing.bank_summary_account_balance") }}
                    </dt>
                    <dd
                      class="font-semibold tabular-nums text-right text-gray-900"
                    >
                      {{
                        formatSummaryAmount(
                          accountBalanceFor(section.currency)!.amount,
                          section.currency,
                        )
                      }}
                      <span class="block text-xs font-normal text-gray-500">
                        {{
                          formatDate(accountBalanceFor(section.currency)!.as_of)
                        }}
                      </span>
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>
        </template>
      </div>
    </template>

    <BankCreateExpenseModal
      :open="!!expenseModalTx"
      :currency="expenseModalTx?.currency || 'EUR'"
      :initial="expenseDraft"
      :saving="creatingExpense"
      :error="expenseError"
      @close="closeCreateExpense"
      @submit="submitCreateExpense"
    />

    <div
      v-if="matchModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="matchModal = null"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-4 space-y-3">
        <h3 class="font-semibold">{{ t("invoicing.bank_match_title") }}</h3>
        <p class="text-sm text-gray-600">
          VS {{ matchModal.variable_symbol || "-" }} ·
          {{ formatSignedAmount(matchModal) }}
        </p>
        <div v-if="suggestionsLoading" class="text-sm">
          {{ t("common.loading") }}
        </div>
        <ul v-else class="max-h-60 overflow-y-auto space-y-2 text-sm">
          <li
            v-for="s in suggestions"
            :key="s.document.id"
            class="flex justify-between gap-2 border rounded p-2"
          >
            <span>
              {{ s.document.number }} ·
              {{ formatMoney(s.document.total, s.document.currency) }}
              <span
                v-if="s.reason !== 'variable_symbol'"
                class="text-amber-600 text-xs"
                >({{ s.reason }})</span
              >
            </span>
            <button
              type="button"
              class="text-indigo-600 shrink-0"
              @click="confirmMatch(s.document.id)"
            >
              {{ t("invoicing.bank_match_confirm") }}
            </button>
          </li>
        </ul>
        <p
          v-if="!suggestionsLoading && suggestions.length === 0"
          class="text-sm text-gray-500"
        >
          {{ t("invoicing.bank_no_suggestions") }}
        </p>
        <button
          type="button"
          class="invoicing-btn-secondary w-full"
          @click="matchModal = null"
        >
          {{ t("common.cancel") }}
        </button>
      </div>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingMobileCard from "../../components/invoicing/InvoicingMobileCard.vue";
import InvoicingRowActionsMenu from "../../components/invoicing/InvoicingRowActionsMenu.vue";
import BankCreateExpenseModal, {
  type BankExpenseDraft,
} from "../../components/invoicing/BankCreateExpenseModal.vue";
import { useInvoicingLayout } from "../../composables/useInvoicingLayout";
import { useInvoicingCompanySummary } from "../../composables/useInvoicingCompanySummary";
import { useInvoicingBankPayments } from "../../composables/useInvoicingBankPayments";
import { invoicingDocumentRoutesForType } from "../../composables/useInvoicingDocumentRoutes";
import { useFlashStore } from "../../store/flash";
import { invoicingApi } from "../../services/api";
import { ensureBridgeCompanyIdForLocalCompany } from "../../evolu/bridgeCompanyEnsure";

type LinkedDocument = { id: string; number?: string; type?: string };
type LinkedExpense = { id: string; internal_number: string };

type BankTx = {
  id: string;
  booked_at: string;
  amount: string;
  currency: string;
  direction: string;
  variable_symbol?: string | null;
  counterparty_name?: string | null;
  reference?: string | null;
  match_status: string;
  match?: {
    document?: LinkedDocument | null;
  };
  expense?: LinkedExpense | null;
};

const { t, locale } = useI18n();
const route = useRoute();
const { rememberCompany } = useInvoicingLayout();
const { hasBankAccount, summaryLoaded, bankAccountLabel, defaultCurrency } =
  useInvoicingCompanySummary();
const flashStore = useFlashStore();

const companyId = computed(() => route.params.companyId as string);
const bank = useInvoicingBankPayments(companyId, defaultCurrency);

const loading = bank.loading;
const transactions = bank.transactions;
const summary = bank.summary;
const batches = bank.batches;
const matchFilter = ref("all");
const wiseStatus = computed(() => bank.wiseStatus.value);
const wiseBridgeMissing = computed(() => bank.wiseBridgeMissing.value);
const activeTab = ref<"transactions" | "import">("transactions");
const importFile = ref<File | null>(null);
const importFormat = ref("");
const importing = ref(false);
const importResult = ref<{ imported: number; auto_matched: number } | null>(
  null,
);
const wiseToken = ref("");
const wiseConnecting = ref(false);
const wiseSyncing = ref(false);
const showWiseReconnect = ref(false);
const wiseSyncResult = ref<{ imported: number; auto_matched: number } | null>(
  null,
);
const inboundEmail = ref("");
const inboundEnabled = ref(false);
const inboundLength = ref(0);
const inboundMaxLength = ref(0);
const inboundCopied = ref(false);
const inboundBridgeChecked = ref(false);
const inboundBridgeCompanyId = ref<string | null>(null);
const inboundBridgeMissing = computed(
  () =>
    bank.localFirst &&
    inboundBridgeChecked.value &&
    !inboundBridgeCompanyId.value,
);
const matchModal = ref<BankTx | null>(null);
const suggestions = ref<
  {
    document: { id: string; number?: string; total: string; currency: string };
    reason: string;
  }[]
>([]);
const suggestionsLoading = ref(false);
const expenseModalTx = ref<BankTx | null>(null);
const creatingExpense = ref(false);
const expenseError = ref("");
const expenseDraft = ref<BankExpenseDraft>({
  title: "",
  total: 0,
  variable_symbol: "",
  supplier: "",
  category: "",
  internal_note: "",
  issue_date: "",
});

type BankSummarySection = {
  currency: string;
  credit_count: number;
  credit_total: string;
  debit_count: number;
  debit_total: string;
  balance: string;
};

type AccountBalance = {
  amount: string;
  currency: string;
  as_of: string;
};

const summarySections = computed((): BankSummarySection[] => {
  if (summary.value.by_currency.length > 0) {
    return summary.value.by_currency;
  }

  return [
    {
      currency: summary.value.currency || defaultCurrency.value,
      credit_count: summary.value.credit_count,
      credit_total: summary.value.credit_total || "0.00",
      debit_count: summary.value.debit_count,
      debit_total: summary.value.debit_total || "0.00",
      balance: summary.value.balance || "0.00",
    },
  ];
});

function accountBalanceFor(currency: string): AccountBalance | null {
  const balance = summary.value.account_balance;
  if (!balance || balance.currency !== currency) {
    return null;
  }

  return balance;
}

const legendItems = computed(() => [
  { kind: "matched", label: t("invoicing.bank_legend_with_document") },
  { kind: "unmatched", label: t("invoicing.bank_legend_without_document") },
  { kind: "ignored", label: t("invoicing.bank_legend_do_not_pair") },
  { kind: "duplicate", label: t("invoicing.bank_legend_duplicate") },
]);

onMounted(async () => {
  rememberCompany(companyId.value);
  await Promise.all([load(), loadBatches(), loadInbound(), bank.fetchWiseStatus()]);
});

watch(companyId, () => {
  load();
  loadBatches();
  loadInbound();
  void bank.fetchWiseStatus();
});

watch(activeTab, (tab) => {
  if (tab === "transactions") load();
});

function linkedDocument(tx: BankTx): LinkedDocument | null {
  return tx.match?.document?.id ? tx.match.document : null;
}

function linkedExpense(tx: BankTx): LinkedExpense | null {
  return tx.expense?.id ? tx.expense : null;
}

function documentShowTo(doc: LinkedDocument) {
  const routes = invoicingDocumentRoutesForType(doc.type || "invoice");
  return {
    name: routes.show,
    params: { companyId: companyId.value, documentId: doc.id },
  };
}

function pairingCornerClass(tx: BankTx): string {
  if (tx.match_status === "ignored") {
    return "status-corner--ignored";
  }
  if (
    tx.match_status === "matched" ||
    linkedDocument(tx) ||
    linkedExpense(tx)
  ) {
    return "status-corner--matched";
  }

  return "status-corner--unmatched";
}

function counterpartyDisplay(tx: BankTx): string {
  const name = tx.counterparty_name?.trim();
  if (name) return name;

  const reference = tx.reference?.trim();
  if (reference)
    return reference.replace(/\s*\(ID=[^)]+\)\s*/gi, "").trim() || reference;

  return "-";
}

function counterpartySubtext(tx: BankTx): string {
  const name = tx.counterparty_name?.trim();
  const reference = tx.reference?.trim();
  if (!name || !reference || reference === name) return "";

  return reference;
}

async function load() {
  if (!hasBankAccount.value) return;
  await bank.load(matchFilter.value);
}

async function loadBatches() {
  if (!hasBankAccount.value) return;
  await bank.loadBatches();
}

async function resolveInboundCompanyId(): Promise<string | null> {
  if (!bank.localFirst) {
    return companyId.value || null;
  }
  // Per-company bridge resolved by THIS company's legal identity (created on
  // demand) - never a first-row fallback, which handed every local company
  // the same bridge and therefore the same inbound address.
  const result = await ensureBridgeCompanyIdForLocalCompany(companyId.value);
  if (!result.ok) {
    // Transient failure - do not claim the bridge is missing; leaving the
    // check unfinished lets the next loadInbound() run retry.
    inboundBridgeChecked.value = false;
    return null;
  }
  inboundBridgeCompanyId.value = result.bridgeCompanyId;
  inboundBridgeChecked.value = true;
  return result.bridgeCompanyId;
}

async function loadInbound() {
  if (!hasBankAccount.value) return;
  const cid = await resolveInboundCompanyId();
  if (!cid) {
    inboundEmail.value = "";
    inboundLength.value = 0;
    inboundMaxLength.value = 0;
    return;
  }
  try {
    const inbound = await invoicingApi.bankTransactions.inboundEmail<{
      address?: string;
      enabled?: boolean;
      length?: number;
      max_length?: number;
    }>(cid);
    inboundEmail.value = inbound?.address || "";
    inboundEnabled.value = !!inbound?.enabled;
    inboundLength.value = inbound?.length ?? inboundEmail.value.length;
    inboundMaxLength.value = inbound?.max_length ?? 50;
    inboundCopied.value = false;
  } catch {
    inboundEmail.value = "";
    inboundLength.value = 0;
    inboundMaxLength.value = 0;
  }
}

async function copyInboundEmail() {
  if (!inboundEmail.value) return;
  try {
    await navigator.clipboard.writeText(inboundEmail.value);
    inboundCopied.value = true;
    window.setTimeout(() => {
      inboundCopied.value = false;
    }, 2000);
  } catch (err) {
    console.error("Failed to copy b-mail address:", err);
    flashStore.error(t("invoicing.bank_inbound_copy_failed"));
  }
}

function formatWiseSyncAt(iso: string): string {
  try {
    return new Date(iso).toLocaleString(locale.value);
  } catch {
    return iso;
  }
}

async function connectWise() {
  const token = wiseToken.value.trim();
  if (!token) return;
  wiseConnecting.value = true;
  try {
    await bank.connectWise(token);
    wiseToken.value = "";
    showWiseReconnect.value = false;
    flashStore.success(t("invoicing.bank_wise_connect_success"));
  } catch (err: unknown) {
    const message =
      (err as { response?: { data?: { message?: string } } })?.response?.data
        ?.message || t("invoicing.bank_wise_connect_failed");
    flashStore.error(message);
  } finally {
    wiseConnecting.value = false;
  }
}

async function syncWise() {
  wiseSyncing.value = true;
  wiseSyncResult.value = null;
  try {
    const result = await bank.syncWise();
    wiseSyncResult.value = {
      imported: result.imported,
      auto_matched: result.auto_matched,
    };
    await load();
    await loadBatches();
  } catch (err: unknown) {
    const message =
      (err as { response?: { data?: { message?: string } } })?.response?.data
        ?.message || t("invoicing.bank_wise_sync_failed");
    flashStore.error(message);
  } finally {
    wiseSyncing.value = false;
  }
}

function onFile(e: Event) {
  const input = e.target as HTMLInputElement;
  importFile.value = input.files?.[0] ?? null;
}

watch(hasBankAccount, (value) => {
  if (value) {
    load();
    loadBatches();
    loadInbound();
    void bank.fetchWiseStatus();
  }
});

async function upload() {
  if (!importFile.value) return;
  importing.value = true;
  importResult.value = null;
  try {
    const result = await bank.importFile(
      importFile.value,
      importFormat.value || undefined,
    );
    importResult.value = result;
    importFile.value = null;
    activeTab.value = "transactions";
    await load();
  } catch (e: unknown) {
    const err = e as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    alert(err?.response?.data?.message || err?.message || t("common.error"));
  } finally {
    importing.value = false;
  }
}

async function autoMatchBatch(batchId: string) {
  await bank.autoMatchBatch(batchId);
  await load();
}

async function openMatch(tx: BankTx) {
  matchModal.value = tx;
  suggestionsLoading.value = true;
  suggestions.value = [];
  try {
    suggestions.value = await bank.fetchSuggestions(tx.id);
  } finally {
    suggestionsLoading.value = false;
  }
}

async function confirmMatch(documentId: string) {
  if (!matchModal.value) return;
  await bank.matchTransaction(matchModal.value.id, documentId);
  matchModal.value = null;
  await load();
}

async function ignoreTx(tx: BankTx) {
  await bank.ignoreTransaction(tx.id);
  await load();
}

async function unmatchTx(tx: BankTx) {
  await bank.unmatchTransaction(tx.id);
  await load();
}

function openCreateExpense(tx: BankTx) {
  const amount = Math.abs(parseFloat(tx.amount));
  const booked =
    tx.booked_at?.slice(0, 10) || new Date().toISOString().slice(0, 10);
  const supplier = tx.counterparty_name?.trim() || "";
  expenseDraft.value = {
    title: supplier || tx.reference?.trim() || "",
    total: Number.isFinite(amount) ? amount : 0,
    variable_symbol: tx.variable_symbol || "",
    supplier,
    category: "",
    internal_note: tx.reference?.trim() || "",
    issue_date: booked,
  };
  expenseError.value = "";
  expenseModalTx.value = tx;
}

function closeCreateExpense() {
  expenseModalTx.value = null;
  expenseError.value = "";
}

async function submitCreateExpense(draft: BankExpenseDraft) {
  if (!expenseModalTx.value) return;
  creatingExpense.value = true;
  expenseError.value = "";
  try {
    await bank.createExpenseFromTransaction(expenseModalTx.value.id, draft);
    closeCreateExpense();
    await load();
  } catch (e: unknown) {
    const err = e as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    expenseError.value =
      err?.response?.data?.message || err?.message || t("common.error");
  } finally {
    creatingExpense.value = false;
  }
}

function formatDate(iso: string) {
  try {
    return new Date(iso).toLocaleDateString(locale.value, {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return iso;
  }
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === "string" ? parseFloat(amount) : amount;
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

function formatSignedAmount(tx: BankTx) {
  const n = Math.abs(parseFloat(tx.amount));
  const formatted = n.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  const sign = tx.direction === "credit" ? "+" : "-";
  return `${sign}${formatted} ${tx.currency}`;
}

function formatSummaryAmount(
  amount: string,
  currency: string,
  forceNegative = false,
) {
  const n = Math.abs(parseFloat(amount));
  const formatted = n.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  const prefix = forceNegative ? "-" : "";

  return `${prefix}${formatted} ${currency}`;
}
</script>

<style scoped>
.status-corner {
  position: absolute;
  top: 0;
  left: 0;
  width: 14px;
  height: 14px;
  clip-path: polygon(0 0, 100% 0, 0 100%);
}

.status-corner.static {
  position: static;
  display: inline-block;
  vertical-align: middle;
}

.status-corner--matched {
  background-color: #22c55e;
}

.status-corner--unmatched {
  background-color: #ef4444;
}

.status-corner--ignored {
  background-color: #6b7280;
}

.status-corner--duplicate {
  background-color: #eab308;
}

.bank-action-link {
  @apply text-sm text-gray-600 hover:text-indigo-700 hover:underline;
}

.bank-action-link--primary {
  @apply text-indigo-600 font-medium;
}
</style>

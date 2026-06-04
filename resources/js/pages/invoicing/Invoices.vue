<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName" :show-filter-bar="false" />
    </template>

    <template #subheader>
      <InvoicingDocumentFilterBar
        :container-class="INVOICING_CONTAINER_CLASS"
        :status-filters="filters"
        :active-filter="activeFilter"
        :issue-period="issuePeriod"
        :advanced-draft="advancedDraft"
        :has-active-advanced="hasActiveAdvanced()"
        :is-quote-list="isQuoteList"
        @filter-change="setFilter"
        @period-change="onPeriodChange"
        @advanced-open="resetAdvancedDraft"
        @advanced-apply="onAdvancedApply"
      >
        <template #actions>
          <button
            v-if="activeDocumentNav?.mvpEnabled && isCreditNoteList"
            type="button"
            class="invoicing-btn-primary shrink-0"
            @click="openCreditNoteStart"
          >
            + {{ newDocumentLabel() }}
          </button>
          <RouterLink
            v-else-if="activeDocumentNav?.mvpEnabled"
            :to="newDocumentLink"
            class="invoicing-btn-primary shrink-0"
          >
            + {{ newDocumentLabel() }}
          </RouterLink>
        </template>
      </InvoicingDocumentFilterBar>
    </template>

    <div v-if="selectionCount > 0" class="invoicing-bulk-bar">
      <span class="text-sm text-indigo-800 font-medium">
        {{ t('invoicing.bulk_selected', { count: selectionCount }) }}
      </span>
      <div class="relative">
        <button type="button" class="invoicing-btn-secondary text-sm py-1.5" @click="showBulkMenu = !showBulkMenu">
          {{ t('invoicing.bulk_actions') }} ▾
        </button>
        <div v-if="showBulkMenu" class="invoicing-dropdown">
          <button
            v-if="!isQuoteList"
            type="button"
            class="invoicing-dropdown-item"
            @click="runBulk('mark_paid')"
          >
            {{ t('invoicing.bulk_mark_paid') }}
          </button>
          <div class="border-t border-gray-700 my-1"></div>
          <button type="button" class="invoicing-dropdown-item" @click="runBulk('pdf_zip')">
            {{ t('invoicing.bulk_pdf_separate') }}
          </button>
          <button type="button" class="invoicing-dropdown-item" @click="runBulk('pdf_merge')">
            {{ t('invoicing.bulk_pdf_merge') }}
          </button>
          <button type="button" class="invoicing-dropdown-item" @click="runBulk('export_xlsx')">
            {{ t('invoicing.bulk_export_xlsx') }}
          </button>
          <div class="border-t border-gray-200 my-1"></div>
          <button type="button" class="invoicing-dropdown-item text-red-600" @click="runBulk('delete')">
            {{ t('invoicing.bulk_delete') }}
          </button>
          <button type="button" class="invoicing-dropdown-item text-amber-700" @click="runBulk('cancel')">
            {{ t('invoicing.bulk_cancel') }}
          </button>
          <div class="border-t border-gray-200 my-1"></div>
          <button type="button" class="invoicing-dropdown-item text-gray-500" @click="clearSelection">
            {{ t('invoicing.bulk_clear') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="documents.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ emptyMessage }}
    </div>

    <div v-else class="my-8 space-y-0 invoicing-card overflow-hidden">
      <div class="border-b border-gray-200 overflow-x-auto invoice-table-wrap">
        <table class="invoice-table w-full min-w-[900px] text-sm text-left">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide border-b border-gray-200">
            <tr>
              <th class="w-12 px-2 py-3 relative">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="allPageSelected"
                  @change="toggleSelectPage"
                />
                <button
                  type="button"
                  class="ml-0.5 text-gray-500 hover:text-white text-[10px] align-middle"
                  :title="t('invoicing.select_menu')"
                  @click.stop="showSelectMenu = !showSelectMenu"
                >
                  ☰
                </button>
                <div v-if="showSelectMenu" class="invoicing-dropdown min-w-[240px] text-xs normal-case tracking-normal font-normal">
                  <button type="button" class="invoicing-dropdown-item" @click="selectPage">
                    {{ t('invoicing.select_page', { count: documents.length }) }}
                  </button>
                  <button type="button" class="invoicing-dropdown-item" @click="selectAllFiltered">
                    {{ t('invoicing.select_all_filtered', { count: Math.min(totalCount, 100) }) }}
                  </button>
                  <button
                    v-if="selectionCount > 0"
                    type="button"
                    class="invoicing-dropdown-item text-gray-500"
                    @click="clearSelection"
                  >
                    {{ t('invoicing.bulk_clear') }}
                  </button>
                </div>
              </th>
              <th class="w-4 px-0 py-3" aria-hidden="true"></th>
              <th class="px-4 py-3 min-w-[140px]">{{ t('invoicing.col_number') }}</th>
              <th class="w-10 px-1 py-3 text-center" :title="t('invoicing.col_email_status')">@</th>
              <th class="px-4 py-3 min-w-[160px]">{{ t('invoicing.col_title') }}</th>
              <th class="px-4 py-3 min-w-[180px]">{{ t('invoicing.col_client') }}</th>
              <th v-if="showLinkedSourceColumn" class="px-4 py-3 min-w-[120px]">
                {{ isCreditNoteList ? t('invoicing.col_credited_invoice') : t('invoicing.col_linked_invoice') }}
              </th>
              <th class="px-4 py-3 text-right min-w-[100px]">{{ t('invoicing.col_total') }}</th>
              <th class="px-4 py-3 text-right min-w-[110px]">
                {{ isQuoteList ? t('invoicing.col_created') : t('invoicing.col_dates') }}
              </th>
              <th v-if="isQuoteList" class="px-4 py-3 text-right min-w-[110px]">{{ t('invoicing.col_valid_until') }}</th>
              
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="d in documents"
              :key="d.id"
              class="invoice-row border-t border-gray-100 group"
              :class="[
                rowSelected(d.id) ? 'bg-indigo-50' : 'bg-white hover:bg-indigo-50 hover:border-b border-gray-200',
              ]"
            >
              <td class="px-2 py-3 align-middle">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="rowSelected(d.id)"
                  @change="toggleRow(d.id)"
                />
              </td>
              <td class="relative w-4 p-0 align-stretch">
                <span
                  class="status-corner"
                  :class="statusCornerClass(d)"
                  :title="statusLabel(statusKind(d))"
                ></span>
              </td>
              <td class="px-4 py-3 align-middle">
                <RouterLink
                  :to="documentShowTo(d)"
                  class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                  {{ documentListNumber(d) }}
                </RouterLink>
                <p v-if="!isQuoteList && d.final_invoice?.id" class="text-xs text-emerald-700 mt-0.5">
                  {{ t('invoicing.final_invoice_short', { number: d.final_invoice.number || t('invoicing.draft_label') }) }}
                </p>
                <p v-if="d.variable_symbol" class="text-xs text-gray-500 mt-0.5">
                  VS: {{ d.variable_symbol }}
                </p>
              </td>
              <td class="px-1 py-3 text-center align-middle">
                <button
                  v-if="canSendEmail(d)"
                  type="button"
                  class="invoice-email-indicator"
                  :class="d.email_sent_at ? 'invoice-email-indicator--sent' : 'invoice-email-indicator--unsent'"
                  :title="emailIndicatorTitle(d)"
                  @click.stop="openSendEmail(d)"
                >
                  @
                </button>
                <span
                  v-else
                  class="invoice-email-indicator invoice-email-indicator--disabled"
                  :title="t('invoicing.email_indicator_disabled')"
                >
                  @
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600 align-middle">
                {{ invoiceTitle(d) }}
              </td>
              <td class="px-4 py-3 align-middle">
                <RouterLink
                  v-if="d.company_contact_id && d.contact?.name"
                  :to="contactShowTo(d.company_contact_id)"
                  class="text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                  {{ d.contact.name }}
                </RouterLink>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td v-if="showLinkedSourceColumn" class="px-4 py-3 align-middle">
                <RouterLink
                  v-if="isQuoteList && d.final_invoice?.id"
                  :to="documentShowTo({ id: d.final_invoice.id, type: 'invoice' })"
                  class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm"
                >
                  {{ d.final_invoice.number || t('invoicing.draft_label') }}
                </RouterLink>
                <RouterLink
                  v-else-if="isCreditNoteList && d.source_document?.id"
                  :to="documentShowTo({ id: d.source_document.id, type: d.source_document.type || 'invoice' })"
                  class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm"
                >
                  {{ d.source_document.number || t('invoicing.draft_label') }}
                </RouterLink>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap align-middle">
                {{ formatMoney(d.total, d.currency) }}
              </td>
              <td class="px-4 py-3 text-right text-gray-600 text-xs whitespace-nowrap align-middle relative">
                <span>{{ d.issue_date ? formatDate(d.issue_date) : '—' }}</span>
                <template v-if="!isQuoteList">
                  <span class="text-gray-600 mx-1">/</span>
                  <span :class="isOverdue(d) ? 'text-red-400 font-medium' : ''">
                    {{ d.due_date ? formatDate(d.due_date) : '—' }}
                  </span>
                </template>
                <div
                  v-if="!isQuoteList"
                  class="row-actions-bar flex items-center justify-end gap-0.5 min-h-[52px] pr-2 pl-3 rounded-lg transition-opacity"
                  :class="
                    actionId === d.id
                      ? 'opacity-100'
                      : 'opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto'
                  "
                >
                  <button
                    v-if="canIssue(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_issue')"
                    :disabled="actionId === d.id"
                    @click="issueDoc(d)"
                  >
                    <span class="text-sm font-bold">✓</span>
                  </button>
                  <button
                    v-if="canCreateFinalInvoice(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_create_final_invoice')"
                    :disabled="actionId === d.id"
                    @click="createFinalInvoice(d)"
                  >
                    <span class="text-sm font-bold">Fa</span>
                  </button>
                  <button
                    v-if="canMarkPaid(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_mark_paid')"
                    :disabled="actionId === d.id"
                    @click="markPaid(d)"
                  >
                    <span class="text-sm font-bold">€</span>
                  </button>
                  <button
                    v-if="d.status !== 'draft'"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_pdf')"
                    :disabled="actionId === d.id"
                    @click="downloadPdf(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_duplicate')"
                    :disabled="actionId === d.id"
                    @click="duplicateDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                  </button>
                  <RouterLink
                    v-if="d.status === 'draft' || d.status === 'issued'"
                    :to="documentEditTo(d)"
                    class="row-action-btn"
                    :title="t('common.edit')"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </RouterLink>
                  <button
                    v-if="d.status === 'draft'"
                    type="button"
                    class="row-action-btn row-action-btn--danger"
                    :title="t('invoicing.action_delete')"
                    :disabled="actionId === d.id"
                    @click="deleteDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                  <button
                    v-if="d.status === 'issued'"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_cancel')"
                    :disabled="actionId === d.id"
                    @click="cancelDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </td>
              <td
                v-if="isQuoteList"
                class="px-4 py-3 text-right text-gray-600 text-xs whitespace-nowrap align-middle relative"
              >
                <span :class="quoteValidUntilClass(d)">
                  {{ d.due_date ? formatDate(d.due_date) : '—' }}
                </span>
                <div
                  class="row-actions-bar flex items-center justify-end gap-0.5 min-h-[52px] pr-2 pl-3 rounded-lg transition-opacity"
                  :class="
                    actionId === d.id
                      ? 'opacity-100'
                      : 'opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto'
                  "
                >
                  <button
                    v-if="canIssue(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_issue')"
                    :disabled="actionId === d.id"
                    @click="issueDoc(d)"
                  >
                    <span class="text-sm font-bold">✓</span>
                  </button>
                  <button
                    v-if="canApproveQuote(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_approve_quote')"
                    :disabled="actionId === d.id"
                    @click="approveQuote(d)"
                  >
                    <span class="text-sm font-bold text-emerald-700">✓</span>
                  </button>
                  <button
                    v-if="canRejectQuote(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_reject_quote')"
                    :disabled="actionId === d.id"
                    @click="rejectQuote(d)"
                  >
                    <span class="text-sm font-bold">×</span>
                  </button>
                  <button
                    v-if="canCreateInvoiceFromQuote(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_create_invoice_from_quote')"
                    :disabled="actionId === d.id"
                    @click="createInvoiceFromQuote(d)"
                  >
                    <span class="text-sm font-bold">Fa</span>
                  </button>
                  <button
                    v-if="canCreateFinalInvoice(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_create_final_invoice')"
                    :disabled="actionId === d.id"
                    @click="createFinalInvoice(d)"
                  >
                    <span class="text-sm font-bold">Fa</span>
                  </button>
                  <button
                    v-if="canMarkPaid(d)"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_mark_paid')"
                    :disabled="actionId === d.id"
                    @click="markPaid(d)"
                  >
                    <span class="text-sm font-bold">€</span>
                  </button>
                  <button
                    v-if="d.status !== 'draft'"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_pdf')"
                    :disabled="actionId === d.id"
                    @click="downloadPdf(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_duplicate')"
                    :disabled="actionId === d.id"
                    @click="duplicateDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                  </button>
                  <RouterLink
                    v-if="d.status === 'draft' || d.status === 'issued'"
                    :to="documentEditTo(d)"
                    class="row-action-btn"
                    :title="t('common.edit')"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </RouterLink>
                  <button
                    v-if="d.status === 'draft'"
                    type="button"
                    class="row-action-btn row-action-btn--danger"
                    :title="t('invoicing.action_delete')"
                    :disabled="actionId === d.id"
                    @click="deleteDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                  <button
                    v-if="d.status === 'issued'"
                    type="button"
                    class="row-action-btn"
                    :title="t('invoicing.action_cancel')"
                    :disabled="actionId === d.id"
                    @click="cancelDoc(d)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-4 py-3 border-t border-gray-200 bg-gray-50">
        <div class="flex items-center gap-2 text-sm">
          <button
            type="button"
            class="px-2 py-1 rounded border border-gray-300 text-gray-600 bg-white disabled:opacity-40"
            :disabled="currentPage <= 1"
            @click="goPage(currentPage - 1)"
          >
            ‹
          </button>
          <button
            v-for="p in visiblePages"
            :key="p"
            type="button"
            class="min-w-[2rem] px-2 py-1 rounded text-sm"
            :class="
              p === currentPage
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 hover:bg-gray-200'
            "
            @click="goPage(p)"
          >
            {{ p }}
          </button>
          <button
            type="button"
            class="px-2 py-1 rounded border border-gray-300 text-gray-600 bg-white disabled:opacity-40"
            :disabled="currentPage >= lastPage"
            @click="goPage(currentPage + 1)"
          >
            ›
          </button>
          <select
            v-model.number="perPage"
            class="ml-2 px-2 py-1 rounded border border-gray-300 bg-white text-gray-700 text-sm"
            @change="goPage(1)"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div
          class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 text-right min-w-[200px] shadow-sm"
        >
          <div>
            <span class="text-gray-500">{{ t('invoicing.summary_page') }}:</span>
            {{ formatMoney(pageTotal, 'EUR') }}
            <span class="text-gray-500">({{ documents.length }} / {{ totalCount }})</span>
          </div>
          <div v-if="!isQuoteList && pageUnpaidTotal > 0" class="text-amber-700">
            <span class="text-gray-500">{{ t('invoicing.summary_unpaid') }}:</span>
            {{ formatMoney(pageUnpaidTotal, 'EUR') }}
          </div>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="invoicing-card-pad">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
            {{ t('invoicing.legend_title') }}
          </h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li v-for="item in legendItems" :key="item.kind" class="flex items-center gap-3">
              <span
                v-if="item.kind === 'email_sent'"
                class="invoice-email-indicator invoice-email-indicator--sent shrink-0 static cursor-default"
              >@</span>
              <span v-else class="status-corner shrink-0 static" :class="`status-corner--${item.kind}`"></span>
              <span>{{ item.label }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <p v-if="success" class="mt-4 text-sm text-emerald-700">{{ success }}</p>
    <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

    <SendDocumentEmailModal
      :open="sendEmailOpen"
      :company-id="companyId"
      :document-id="sendEmailDocumentId"
      @close="closeSendEmail"
      @sent="onEmailSent"
    />

    <CreditNoteStartModal
      :open="creditNoteStartOpen"
      @close="creditNoteStartOpen = false"
      @continue-without="goNewCreditNoteBlank"
      @add-invoice="openCreditNotePick"
    />
    <CreditNotePickInvoiceModal
      :open="creditNotePickOpen"
      :company-id="companyId"
      @close="creditNotePickOpen = false"
      @selected="onCreditNoteCreatedFromInvoice"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingDocumentFilterBar from '../../components/invoicing/InvoicingDocumentFilterBar.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingDocumentListFilters } from '../../composables/useInvoicingDocumentListFilters';
import CreditNotePickInvoiceModal from '../../components/invoicing/CreditNotePickInvoiceModal.vue';
import CreditNoteStartModal from '../../components/invoicing/CreditNoteStartModal.vue';
import SendDocumentEmailModal from '../../components/invoicing/SendDocumentEmailModal.vue';
import api, { businessDocumentPdfPath, getWebBlob } from '../../services/api';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { useBtcpayPaymentPoll } from '../../composables/useBtcpayPaymentPoll';
import { invoicingDocumentRoutesForType } from '../../composables/useInvoicingDocumentRoutes';

type StatusKind =
  | 'paid'
  | 'waiting'
  | 'overdue'
  | 'draft'
  | 'cancelled'
  | 'approved'
  | 'pending'
  | 'rejected'
  | 'expired';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const {
  companyId,
  activeDocumentNav,
  rememberCompany,
  newDocumentRouteName,
  newDocumentLabel,
  INVOICING_CONTAINER_CLASS,
} = useInvoicingLayout();

const {
  issuePeriod,
  advancedDraft,
  resetAdvancedDraft,
  applyAdvancedDraft,
  hasActiveAdvanced,
  appendListQueryParams,
  resetOnRouteChange,
} = useInvoicingDocumentListFilters();

const isQuoteList = computed(() => activeDocumentNav.value.kind === 'quote');
const isCreditNoteList = computed(() => activeDocumentNav.value.kind === 'credit_note');
const showLinkedSourceColumn = computed(() => isQuoteList.value || isCreditNoteList.value);

const creditNoteStartOpen = ref(false);
const creditNotePickOpen = ref(false);

const newDocumentLink = computed(() => ({
  name: newDocumentRouteName(activeDocumentNav.value.kind),
  params: { companyId: companyId.value },
  query: activeDocumentNav.value.apiType ? { type: activeDocumentNav.value.apiType } : {},
}));

const documents = ref<any[]>([]);
const listAwaitingBtcpay = computed(() =>
  documents.value.some((d) => d.status === 'issued' && d.payment_btc_enabled)
);
const listPollStatus = computed(() => (listAwaitingBtcpay.value ? 'issued' : 'paid'));
const loading = ref(false);
const error = ref('');
const success = ref('');
const actionId = ref<string | null>(null);
const companyName = ref('');
const activeFilter = ref('all');
const totalCount = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(25);

const selectedIds = ref<Set<string>>(new Set());
const selectAllMode = ref(false);
const showSelectMenu = ref(false);
const showBulkMenu = ref(false);
const sendEmailOpen = ref(false);
const sendEmailDocumentId = ref('');

const emptyMessage = computed(() => {
  const kind = activeDocumentNav.value.kind;
  if (kind === 'proforma') return t('invoicing.no_proformas');
  if (kind === 'quote') return t('invoicing.no_quotes');
  if (kind === 'credit_note') return t('invoicing.no_credit_notes');
  if (kind === 'drafts') return t('invoicing.no_drafts');
  return t('invoicing.no_invoices');
});

const filters = computed(() => {
  if (isQuoteList.value) {
    return [
      { id: 'all', label: t('invoicing.filter_all') },
      { id: 'approved', label: t('invoicing.filter_quote_approved') },
      { id: 'pending', label: t('invoicing.filter_quote_pending') },
      { id: 'rejected', label: t('invoicing.filter_quote_rejected') },
      { id: 'expired', label: t('invoicing.filter_quote_expired') },
    ];
  }
  return [
    { id: 'all', label: t('invoicing.filter_all') },
    { id: 'paid', label: t('invoicing.filter_paid') },
    { id: 'unpaid', label: t('invoicing.filter_unpaid') },
    { id: 'overdue', label: t('invoicing.filter_overdue') },
  ];
});

const legendItems = computed(() => {
  if (isQuoteList.value) {
    return [
      { kind: 'approved', label: t('invoicing.legend_quote_approved') },
      { kind: 'pending', label: t('invoicing.legend_quote_pending') },
      { kind: 'expired', label: t('invoicing.legend_quote_expired') },
      { kind: 'rejected', label: t('invoicing.legend_quote_rejected') },
      { kind: 'draft', label: t('invoicing.legend_draft') },
      { kind: 'email_sent', label: t('invoicing.legend_email_sent') },
    ];
  }
  return [
    { kind: 'paid', label: t('invoicing.legend_paid') },
    { kind: 'waiting', label: t('invoicing.legend_waiting') },
    { kind: 'overdue', label: t('invoicing.legend_overdue') },
    { kind: 'draft', label: t('invoicing.legend_draft') },
    { kind: 'cancelled', label: t('invoicing.legend_cancelled') },
    { kind: 'email_sent', label: t('invoicing.legend_email_sent') },
  ];
});

const selectionCount = computed(() =>
  selectAllMode.value ? Math.min(totalCount.value, 100) : selectedIds.value.size
);

const allPageSelected = computed(
  () => documents.value.length > 0 && documents.value.every((d) => rowSelected(d.id))
);

const pageTotal = computed(() =>
  documents.value.reduce((sum, d) => sum + Number(d.total || 0), 0)
);

const pageUnpaidTotal = computed(() =>
  documents.value
    .filter((d) => d.status === 'issued')
    .reduce((sum, d) => sum + Number(d.total || 0), 0)
);

const visiblePages = computed(() => {
  const pages: number[] = [];
  const start = Math.max(1, currentPage.value - 2);
  const end = Math.min(lastPage.value, currentPage.value + 2);
  for (let p = start; p <= end; p++) pages.push(p);
  return pages;
});

function resolvedQuoteStatus(d: {
  quote_status?: string | null;
  resolved_quote_status?: string | null;
}) {
  return d.resolved_quote_status ?? d.quote_status ?? null;
}

function statusKind(d: {
  status: string;
  type?: string;
  due_date?: string;
  quote_status?: string | null;
  resolved_quote_status?: string | null;
}): StatusKind {
  if (d.type === 'quote') {
    if (d.status === 'draft') return 'draft';
    if (d.status === 'cancelled') return 'cancelled';
    const rs = resolvedQuoteStatus(d);
    if (rs === 'approved') return 'approved';
    if (rs === 'rejected') return 'rejected';
    if (rs === 'expired') return 'expired';
    if (rs === 'pending') return 'pending';
    return 'waiting';
  }
  if (d.status === 'paid') return 'paid';
  if (d.status === 'cancelled') return 'cancelled';
  if (d.status === 'draft') return 'draft';
  if (d.status === 'issued' && isOverdue(d)) return 'overdue';
  return 'waiting';
}

function statusCornerClass(d: { status: string; due_date?: string }) {
  return `status-corner--${statusKind(d)}`;
}

function invoiceTitle(d: { number?: string; title?: string; type?: string; status: string }) {
  if (d.title) return d.title;
  const prefixKey =
    d.type === 'proforma'
      ? 'invoicing.proforma_title_prefix'
      : d.type === 'quote'
        ? 'invoicing.quote_title_prefix'
        : d.type === 'credit_note'
          ? 'invoicing.credit_note_title_prefix'
          : 'invoicing.invoice_title_prefix';
  const num = documentListNumber(d);
  if (num !== t('invoicing.draft_label')) return `${t(prefixKey)} ${num}`;
  return t('invoicing.draft_label');
}

function documentShowTo(d: { id: string; type?: string }) {
  const routes = invoicingDocumentRoutesForType(d.type);
  return { name: routes.show, params: { companyId: companyId.value, documentId: d.id } };
}

function documentEditTo(d: { id: string; type?: string }) {
  const routes = invoicingDocumentRoutesForType(d.type);
  return { name: routes.edit, params: { companyId: companyId.value, documentId: d.id } };
}

function canCreateFinalInvoice(d: {
  type?: string;
  status: string;
  final_invoice?: { id?: string } | null;
}) {
  return d.type === 'proforma' && d.status === 'paid' && !d.final_invoice?.id;
}

function canApproveQuote(d: {
  type?: string;
  status: string;
  quote_status?: string | null;
  resolved_quote_status?: string | null;
}) {
  return d.type === 'quote' && d.status === 'issued' && resolvedQuoteStatus(d) === 'pending';
}

function canRejectQuote(d: {
  type?: string;
  status: string;
  quote_status?: string | null;
  resolved_quote_status?: string | null;
}) {
  return d.type === 'quote' && d.status === 'issued' && resolvedQuoteStatus(d) === 'pending';
}

function canCreateInvoiceFromQuote(d: {
  type?: string;
  status: string;
  quote_status?: string | null;
  resolved_quote_status?: string | null;
  final_invoice?: { id?: string } | null;
}) {
  return (
    d.type === 'quote'
    && d.status === 'issued'
    && resolvedQuoteStatus(d) === 'approved'
    && !d.final_invoice?.id
  );
}

async function approveQuote(d: { id: string }) {
  actionId.value = d.id;
  error.value = '';
  try {
    await api.post(`/invoicing/companies/${companyId.value}/documents/${d.id}/approve-quote`);
    success.value = t('invoicing.quote_approved_success');
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function rejectQuote(d: { id: string }) {
  actionId.value = d.id;
  error.value = '';
  try {
    await api.post(`/invoicing/companies/${companyId.value}/documents/${d.id}/reject-quote`);
    success.value = t('invoicing.quote_rejected_success');
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function createInvoiceFromQuote(d: { id: string }) {
  actionId.value = d.id;
  error.value = '';
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/documents/${d.id}/create-invoice-from-quote`
    );
    router.push({
      name: 'invoicing-invoice-edit',
      params: { companyId: companyId.value, documentId: res.data.data.id },
    });
  } catch (e: any) {
    error.value =
      e?.response?.data?.message
      || e?.response?.data?.errors?.quote_status?.[0]
      || t('common.error');
  } finally {
    actionId.value = null;
  }
}

function quoteValidUntilClass(d: {
  quote_status?: string | null;
  resolved_quote_status?: string | null;
}) {
  return resolvedQuoteStatus(d) === 'expired' ? 'text-gray-500 font-medium' : '';
}

async function createFinalInvoice(d: { id: string }) {
  actionId.value = d.id;
  error.value = '';
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/documents/${d.id}/create-final-invoice`
    );
    router.push({
      name: 'invoicing-invoice-edit',
      params: { companyId: companyId.value, documentId: res.data.data.id },
    });
  } catch (e: any) {
    error.value = e?.response?.data?.message
      || e?.response?.data?.errors?.status?.[0]
      || e?.response?.data?.errors?.source_document_id?.[0]
      || t('common.error');
  } finally {
    actionId.value = null;
  }
}

function contactShowTo(contactId: string) {
  return {
    name: 'invoicing-contact-show',
    params: { companyId: companyId.value, contactId },
  };
}

function canSendEmail(d: { status: string }) {
  return d.status !== 'draft' && d.status !== 'cancelled';
}

function emailIndicatorTitle(d: { email_sent_at?: string | null }) {
  if (d.email_sent_at) {
    return t('invoicing.email_indicator_sent');
  }
  return t('invoicing.email_indicator_send');
}

function openSendEmail(d: { id: string }) {
  sendEmailDocumentId.value = d.id;
  sendEmailOpen.value = true;
}

function closeSendEmail() {
  sendEmailOpen.value = false;
  sendEmailDocumentId.value = '';
}

function onEmailSent(payload?: { email_sent_at?: string }) {
  const id = sendEmailDocumentId.value;
  const row = documents.value.find((doc) => doc.id === id);
  if (row) {
    row.email_sent_at = payload?.email_sent_at ?? new Date().toISOString();
  }
  success.value = t('invoicing.send_email_success');
  closeSendEmail();
}

function rowSelected(id: string) {
  if (selectAllMode.value) return true;
  return selectedIds.value.has(id);
}

function toggleRow(id: string) {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function toggleSelectPage() {
  if (allPageSelected.value) {
    documents.value.forEach((d) => selectedIds.value.delete(d.id));
    selectedIds.value = new Set(selectedIds.value);
    selectAllMode.value = false;
  } else {
    selectPage();
  }
}

function selectPage() {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  documents.value.forEach((d) => next.add(d.id));
  selectedIds.value = next;
  showSelectMenu.value = false;
}

function selectAllFiltered() {
  selectAllMode.value = true;
  selectedIds.value = new Set();
  showSelectMenu.value = false;
}

function clearSelection() {
  selectAllMode.value = false;
  selectedIds.value = new Set();
  showBulkMenu.value = false;
  showSelectMenu.value = false;
}

function listQueryParams(): Record<string, unknown> {
  const nav = activeDocumentNav.value;
  const params: Record<string, unknown> = {
    page: currentPage.value,
    per_page: perPage.value,
  };
  if (nav.kind === 'drafts') {
    params.status = 'draft';
  } else if (nav.apiType) {
    params.type = nav.apiType;
  }
  return appendListQueryParams(params, activeFilter.value);
}

function bulkPayload(action: string) {
  const nav = activeDocumentNav.value;
  const base: Record<string, unknown> = {
    action,
    ...(nav.kind === 'drafts' ? { status: 'draft' } : { type: nav.apiType ?? 'invoice' }),
  };
  appendListQueryParams(base, activeFilter.value);
  if (selectAllMode.value) {
    base.select_all = true;
  } else {
    base.document_ids = Array.from(selectedIds.value);
  }
  return base;
}

const fileActions = new Set(['pdf_zip', 'pdf_merge', 'export_xlsx']);

async function runBulk(action: string) {
  if (selectionCount.value === 0) return;
  showBulkMenu.value = false;

  if (action === 'delete' && !window.confirm(t('invoicing.confirm_bulk_delete'))) return;
  if (action === 'cancel' && !window.confirm(t('invoicing.confirm_bulk_cancel'))) return;

  error.value = '';
  success.value = '';
  loading.value = true;

  try {
    const isFile = fileActions.has(action);
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/documents/bulk`,
      bulkPayload(action),
      isFile ? { responseType: 'blob' } : {}
    );

    if (isFile) {
      const blob = res.data as Blob;
      const names: Record<string, string> = {
        pdf_zip: 'invoices.zip',
        pdf_merge: 'invoices-merged.pdf',
        export_xlsx: 'invoices.xlsx',
      };
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = names[action] || 'export';
      a.click();
      URL.revokeObjectURL(url);
    } else {
      const data = res.data.data;
      success.value = t('invoicing.bulk_result', {
        processed: data.processed ?? 0,
        skipped: data.skipped ?? 0,
      });
      await load();
      clearSelection();
    }
  } catch (e: any) {
    if (e?.response?.data instanceof Blob) {
      const text = await e.response.data.text();
      try {
        const json = JSON.parse(text);
        error.value = json.message || t('common.error');
      } catch {
        error.value = t('common.error');
      }
    } else {
      error.value = e?.response?.data?.message || t('common.error');
    }
  } finally {
    loading.value = false;
  }
}

function formatMoney(amount: string | number, currency: string) {
  const n = Number(amount);
  return `${n.toLocaleString(locale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency || 'EUR'}`;
}

function formatDate(iso: string) {
  const d = new Date(iso.includes('T') ? iso : `${iso}T12:00:00`);
  return d.toLocaleDateString(locale.value);
}

function statusLabel(kind: StatusKind) {
  if (['approved', 'pending', 'rejected', 'expired'].includes(kind)) {
    return t(`invoicing.legend_quote_${kind}`);
  }
  return t(`invoicing.legend_${kind}`);
}

function isOverdue(d: { status: string; due_date?: string }) {
  if (d.status !== 'issued' || !d.due_date) return false;
  return new Date(`${d.due_date}T23:59:59`) < new Date();
}

function documentListNumber(d: { number?: string; title?: string; status: string }) {
  if (d.number) return d.number;
  if (d.status === 'draft' && d.title) {
    const match = d.title.match(/\b(PON\d{7,}|ZAL\d{8}|\d{8,})\b/i);
    if (match) return match[1];
  }
  return t('invoicing.draft_label');
}

function canMarkPaid(d: { status: string; type?: string }) {
  if (d.type === 'quote') return false;
  return d.status === 'issued' || d.status === 'draft';
}

function canIssue(d: { status: string }) {
  return d.status === 'draft';
}

function goPage(page: number) {
  if (page < 1 || page > lastPage.value) return;
  currentPage.value = page;
  load();
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const [companyRes, docsRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}`),
      api.get(`/invoicing/companies/${companyId.value}/documents`, {
        params: listQueryParams(),
      }),
    ]);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';
    documents.value = docsRes.data.data ?? [];
    totalCount.value = docsRes.data.total ?? documents.value.length;
    currentPage.value = docsRes.data.current_page ?? 1;
    lastPage.value = docsRes.data.last_page ?? 1;
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    loading.value = false;
  }
}

function openCreditNoteStart() {
  creditNoteStartOpen.value = true;
}

function goNewCreditNoteBlank() {
  creditNoteStartOpen.value = false;
  router.push({
    name: 'invoicing-credit-note-new',
    params: { companyId: companyId.value },
  });
}

function openCreditNotePick() {
  creditNoteStartOpen.value = false;
  creditNotePickOpen.value = true;
}

function onCreditNoteCreatedFromInvoice(documentId: string) {
  creditNotePickOpen.value = false;
  router.push({
    name: 'invoicing-credit-note-edit',
    params: { companyId: companyId.value, documentId },
  });
}

function setFilter(id: string) {
  activeFilter.value = id;
  currentPage.value = 1;
  clearSelection();
  load();
}

function onPeriodChange() {
  currentPage.value = 1;
  clearSelection();
  load();
}

function onAdvancedApply() {
  applyAdvancedDraft();
  currentPage.value = 1;
  clearSelection();
  load();
}

async function issueDoc(d: { id: string }) {
  actionId.value = d.id;
  try {
    await api.post(`/invoicing/companies/${companyId.value}/documents/${d.id}/issue`);
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function markPaid(d: { id: string }) {
  actionId.value = d.id;
  try {
    await api.post(`/invoicing/companies/${companyId.value}/documents/${d.id}/mark-paid`);
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function downloadPdf(d: { id: string; number?: string }) {
  actionId.value = d.id;
  try {
    const blob = await getWebBlob(businessDocumentPdfPath(companyId.value, d.id));
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `invoice-${d.number || d.id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function duplicateDoc(d: { id: string }) {
  actionId.value = d.id;
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/documents/${d.id}/duplicate`
    );
    const routes = invoicingDocumentRoutesForType(res.data.data.type);
    router.push({
      name: routes.edit,
      params: { companyId: companyId.value, documentId: res.data.data.id },
    });
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function deleteDoc(d: { id: string }) {
  if (!window.confirm(t('invoicing.confirm_delete'))) return;
  actionId.value = d.id;
  try {
    await api.delete(`/invoicing/companies/${companyId.value}/documents/${d.id}`);
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

async function cancelDoc(d: { id: string }) {
  if (!window.confirm(t('invoicing.confirm_cancel'))) return;
  actionId.value = d.id;
  try {
    await api.post(`/invoicing/companies/${companyId.value}/documents/${d.id}/cancel`);
    await load();
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    actionId.value = null;
  }
}

watch(companyId, () => {
  clearSelection();
  currentPage.value = 1;
  load();
});

watch(
  () => route.name,
  () => {
    activeFilter.value = 'all';
    resetOnRouteChange();
    clearSelection();
    currentPage.value = 1;
    load();
  }
);

useBtcpayPaymentPoll({
  enabled: listAwaitingBtcpay,
  status: listPollStatus,
  reload: load,
});

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>

<style scoped>
.invoice-table-wrap {
  overflow-x: auto;
  overflow-y: visible;
}

.invoice-table tbody td:last-child {
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

.invoice-table tbody tr {
  @apply transition-colors;
}

/* Superfaktúra-style corner status badge */
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

.status-corner--paid {
  background-color: #22c55e;
}

.status-corner--waiting {
  background-color: #eab308;
}

.status-corner--overdue {
  background-color: #ef4444;
}

.status-corner--draft {
  background-color: #6b7280;
}

.status-corner--cancelled {
  background-color: #9ca3af;
  opacity: 0.7;
}

.status-corner--approved {
  background-color: #22c55e;
}

.status-corner--pending {
  background-color: #eab308;
}

.status-corner--rejected {
  background-color: #6b7280;
}

.status-corner--expired {
  background-color: #d1d5db;
}
</style>

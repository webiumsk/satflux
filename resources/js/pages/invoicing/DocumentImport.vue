<template>
  <InvoicingPageShell content-class="pb-10">
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>

    <h1 class="invoicing-title mb-6">{{ t(localFirst ? "invoicing.migration.title" : "invoicing.import_title") }}</h1>

    <div v-if="localFirst" class="mb-6 max-w-4xl space-y-2 text-sm text-slate-600">
      <p>{{ t("invoicing.migration.intro") }}</p>
      <p>{{ t("invoicing.migration.summary_only") }}</p>
      <p>{{ t("invoicing.migration.original_scope") }}</p>
    </div>

    <fieldset class="mb-6" :disabled="importing">
      <legend class="invoicing-sf-label mb-2">{{ t("invoicing.import_kind") }}</legend>
      <div class="flex flex-wrap gap-5">
        <label class="flex items-center gap-2 text-sm">
          <input v-model="importKind" type="radio" value="invoices" name="import-kind" />
          {{ t("invoicing.doc_nav_invoice") }}
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="importKind" type="radio" value="expenses" name="import-kind" />
          {{ t("invoicing.main_nav_expenses") }}
        </label>
      </div>
    </fieldset>

    <template v-if="importKind === 'invoices'">
    <nav class="flex flex-wrap gap-2 mb-8 text-sm">
      <span
        v-for="(label, idx) in stepLabels"
        :key="idx"
        class="px-3 py-1.5 rounded-full border"
        :class="
          step === idx + 1
            ? 'bg-indigo-600 text-white border-indigo-600'
            : 'bg-gray-50 text-gray-600 border-gray-200'
        "
      >
        {{ label }}
      </span>
    </nav>

    <!-- Step 1 -->
    <section v-if="step === 1" class="invoicing-card-pad space-y-6 max-w-4xl">
      <h2 class="text-lg font-semibold text-gray-800">
        {{ t("invoicing.import_step1_heading") }}
      </h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <button
          v-for="src in availableSources"
          :key="src.id"
          type="button"
          class="rounded-lg border p-4 text-center transition-colors min-h-[120px] flex flex-col items-center justify-center gap-2"
          :class="
            src.enabled
              ? selectedSource === src.id
                ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-50'
              : 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed'
          "
          :disabled="!src.enabled || importing"
          @click="selectSource(src)"
        >
          <img
            v-if="src.id === 'superfaktura'"
            src="/img/superfaktura.svg"
            :alt="t(src.labelKey)"
            width="125"
            height="41"
            class="h-auto w-[7.5rem] max-w-full"
          />
          <img
            v-else-if="src.id === 'fakturoid'"
            src="/img/fakturoid.svg"
            :alt="t(src.labelKey)"
            width="844"
            height="226"
            class="h-auto w-[7.5rem] max-w-full"
          />
          <img
            v-else-if="src.id === 'zaprite'"
            src="/img/zaprite.svg"
            :alt="t(src.labelKey)"
            width="162"
            height="48"
            class="h-auto w-[7.5rem] max-w-full rounded-md bg-slate-950 px-2"
          />
          <template v-else>
            <span class="text-2xl font-bold text-gray-400">{{ sourceIcon(src.id) }}</span>
            <span class="text-sm font-medium text-gray-800">{{ t(src.labelKey) }}</span>
          </template>
          <span v-if="!src.enabled" class="text-xs text-gray-500">{{
            t("invoicing.import_coming_soon")
          }}</span>
        </button>
      </div>
      <div class="flex justify-end">
        <button
          type="button"
          class="invoicing-btn-primary"
          :disabled="!selectedSource"
          @click="step = 2"
        >
          {{ t("common.continue") }}
        </button>
      </div>
    </section>

    <!-- Step 2 -->
    <fieldset v-else-if="step === 2" :disabled="importing" class="space-y-6 max-w-3xl">
      <div v-if="selectedSource === 'zaprite'" class="invoicing-card-pad space-y-4">
        <h2 class="font-semibold">{{ t("invoicing.migration.zaprite_title") }}</h2>
        <p class="text-sm text-slate-600">{{ t("invoicing.migration.zaprite_hint") }}</p>
        <label for="zaprite-api-key" class="invoicing-sf-label">{{ t("invoicing.migration.zaprite_key") }}</label>
        <input id="zaprite-api-key" v-model="zapriteApiKey" type="password" autocomplete="off" spellcheck="false" class="invoicing-sf-input" :disabled="loading" />
        <button type="button" class="invoicing-btn-primary" :disabled="!zapriteApiKey.trim() || loading" @click="loadZaprite">
          {{ zapriteProgress ? t("invoicing.migration.zaprite_loading", zapriteProgress) : t("invoicing.migration.zaprite_load") }}
        </button>
        <div class="space-y-2 border-t border-slate-200 pt-4">
          <label for="zaprite-export-file" class="invoicing-sf-label">{{ t("invoicing.migration.zaprite_json") }}</label>
          <input id="zaprite-export-file" type="file" accept=".json" :disabled="loading" @change="onFileChange" />
          <p v-if="selectedFile" class="text-sm break-all">{{ selectedFile.name }}</p>
          <button v-if="selectedFile && zapriteInfo" type="button" class="invoicing-btn-secondary" @click="downloadCsvBlob(selectedFile, selectedFile.name)">{{ t("invoicing.migration.zaprite_download") }}</button>
        </div>
        <p class="text-sm text-amber-900">{{ t("invoicing.migration.zaprite_currency_hint") }}</p>
        <label class="flex items-start gap-2 text-sm text-slate-700">
          <input v-model="excludeUnsupportedCurrencies" type="checkbox" class="mt-0.5" :disabled="loading" />
          <span>{{ t("invoicing.migration.zaprite_exclude") }}</span>
        </label>
        <p v-if="zapriteInfo" class="text-sm" role="status">{{ t("invoicing.migration.zaprite_loaded", { count: zapriteInfo.invoiceCount, ignored: zapriteInfo.ignoredOrders }) }}</p>
        <p v-if="zapriteExcluded" class="text-sm text-amber-900" role="status">{{ t("invoicing.migration.zaprite_excluded", { currencies: zapriteExcluded }) }}</p>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="createContacts" type="checkbox" />{{ t("invoicing.import_create_contacts") }}
        </label>
      </div>
      <div v-else-if="selectedSource === 'fakturoid'" class="invoicing-card-pad space-y-4">
        <h2 class="font-semibold">{{ t("invoicing.migration.fakturoid_title") }}</h2>
        <p class="text-sm text-slate-600">{{ t("invoicing.migration.fakturoid_hint") }}</p>
        <label for="fakturoid-export-file" class="invoicing-sf-label">{{ t("invoicing.migration.fakturoid_xml") }}</label>
        <input id="fakturoid-export-file" type="file" accept=".xml" :disabled="loading" @change="onFileChange" />
        <p v-if="selectedFile" class="text-sm break-all">{{ selectedFile.name }}</p>
        <p class="text-sm text-slate-600">{{ t("invoicing.migration.fakturoid_scope") }}</p>
        <p v-if="fakturoidInfo" class="text-sm" role="status">{{ t("invoicing.migration.fakturoid_loaded", { count: fakturoidInfo.invoiceCount }) }}</p>
        <div class="space-y-2 rounded-lg border border-slate-200 p-4">
          <label for="fakturoid-original-files" class="invoicing-sf-label">{{ t("invoicing.migration.originals_title") }}</label>
          <input id="fakturoid-original-files" type="file" multiple accept=".pdf,.xml,.isdoc" @change="pickOriginals" />
          <p class="text-xs text-slate-600">{{ t("invoicing.migration.fakturoid_originals", { max: MIGRATION_MAX_ORIGINALS }) }}</p>
          <ul class="max-h-40 overflow-auto text-sm"><li v-for="(file, index) in originalFiles" :key="index" class="break-all">{{ file.name }}</li></ul>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="createContacts" type="checkbox" />{{ t("invoicing.import_create_contacts") }}
        </label>
      </div>
      <div v-else class="invoicing-card-pad space-y-4">
        <p class="text-sm text-gray-600">
          {{ t("invoicing.import_files_hint") }}
          <button
            type="button"
            class="text-indigo-600 hover:underline"
            @click="downloadExample"
          >
            {{ t("invoicing.import_example_link") }}
          </button>
        </p>

        <div>
          <label class="invoicing-sf-label">{{
            t("invoicing.import_date_format")
          }}</label>
          <select v-model="dateFormat" class="invoicing-sf-input max-w-md">
            <option value="dmy_dot">
              {{ t("invoicing.import_date_format_dmy_dot") }}
            </option>
            <option value="ymd_dash">
              {{ t("invoicing.import_date_format_ymd_dash") }}
            </option>
            <option value="mdy_slash">
              {{ t("invoicing.import_date_format_mdy_slash") }}
            </option>
            <option value="auto">
              {{ t("invoicing.import_date_format_auto") }}
            </option>
          </select>
          <p class="text-xs text-gray-500 mt-1">
            {{ t("invoicing.import_date_format_hint") }}
          </p>
        </div>

        <div
          class="rounded-lg border-2 border-dashed border-indigo-300 bg-indigo-50/30 p-8 text-center"
          @dragover.prevent
          @drop.prevent="onDrop"
        >
          <label class="invoicing-btn-primary cursor-pointer">
            {{ t("invoicing.contact_import_drop") }}
            <input
              ref="fileInput"
              type="file"
              class="hidden"
              accept=".xlsx,.xls,.csv"
              @change="onFileChange"
            />
          </label>
          <p v-if="selectedFile" class="mt-3 text-sm text-gray-600">
            {{ selectedFile.name }}
          </p>
        </div>

        <div v-if="localFirst" class="space-y-2 rounded-lg border border-slate-200 p-4">
          <label for="migration-original-files" class="invoicing-sf-label">{{ t("invoicing.migration.originals_title") }}</label>
          <input id="migration-original-files" type="file" multiple accept=".pdf,.xml,.isdoc" @change="pickOriginals" />
          <p class="text-xs text-slate-600">{{ t("invoicing.migration.originals_hint", { max: MIGRATION_MAX_ORIGINALS }) }}</p>
          <p v-if="selectedSource === 'superfaktura'" class="text-xs text-slate-600">{{ t("invoicing.migration.isdoc_hint") }}</p>
          <p v-if="!originalFiles.length" class="text-sm text-amber-800">{{ t("invoicing.migration.originals_missing") }}</p>
          <ul class="max-h-40 overflow-auto text-sm"><li v-for="(file, index) in originalFiles" :key="index" class="break-all">{{ file.name }} ({{ file.size }} {{ t("invoicing.migration.bytes") }})</li></ul>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="oneRowPerInvoice"
            :disabled="localFirst"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.import_one_row_per_invoice") }}
        </label>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="createContacts"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.import_create_contacts") }}
        </label>
        <p class="text-xs text-gray-500 -mt-2">
          {{ t("invoicing.import_create_contacts_hint") }}
        </p>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.import_line_name")
            }}</label>
            <input v-model="lineName" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.import_line_description")
            }}</label>
            <input
              v-model="lineDescription"
              type="text"
              class="invoicing-sf-input"
            />
          </div>
        </div>
      </div>

      <div v-if="headers.length" class="invoicing-card-pad space-y-4">
        <h3 class="font-semibold text-gray-800">
          {{ t("invoicing.import_mapping_title") }}
        </h3>
        <p class="text-sm text-gray-600">
          {{ t("invoicing.import_mapping_hint") }}
        </p>

        <div class="space-y-2 max-h-[420px] overflow-auto pr-2">
          <div
            v-for="field in visibleFields"
            :key="field"
            class="grid sm:grid-cols-2 gap-2 items-center text-sm"
          >
            <span class="text-gray-700">
              {{ fieldLabel(field) }}
              <span v-if="isRequired(field)" class="text-red-500">*</span>
            </span>
            <select v-model="mapping[field]" class="invoicing-sf-input" :aria-label="fieldLabel(field)">
              <option :value="null">-</option>
              <option v-for="(header, idx) in headers" :key="idx" :value="idx">
                {{ header || t("invoicing.import_column", { n: idx + 1 }) }}
              </option>
            </select>
          </div>
        </div>

        <p class="text-xs text-gray-500">
          {{ t(localFirst ? "invoicing.migration.payment_evidence_hint" : "invoicing.import_paid_hint") }}
        </p>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex flex-wrap gap-3">
        <button type="button" class="invoicing-btn-secondary" @click="step = 1">
          {{ t("common.back") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary"
          :disabled="!selectedFile || loading || !mappingReady"
          @click="goToConfirm"
        >
          {{ originalScanProgress ? t("invoicing.migration.reading_originals", originalScanProgress) : loading ? t("common.loading") : t("common.continue") }}
        </button>
      </div>
    </fieldset>

    <!-- Step 3 -->
    <section v-else class="space-y-6 max-w-4xl">
      <div class="invoicing-card-pad space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">
          {{ t("invoicing.import_step3_heading") }}
        </h2>
        <p class="text-sm text-gray-600">
          {{ t(localFirst ? "invoicing.migration.confirm_summary" : "invoicing.import_confirm_summary", { count: rowCount }) }}
        </p>

        <template v-if="localFirst && migrationPreview">
          <p v-if="selectedSource === 'zaprite' && zapriteExcluded" class="text-sm text-amber-900" role="status">{{ t("invoicing.migration.zaprite_excluded", { currencies: zapriteExcluded }) }}</p>
          <p class="text-sm font-medium" role="status">{{ t("invoicing.migration.counts", migrationPreview) }}</p>
          <p v-if="migrationPreview.invalid || migrationPreview.conflict" class="text-sm text-red-700" role="alert">{{ t("invoicing.migration.blocked") }}</p>
          <details v-if="originalScanIssues.length" class="rounded-lg border border-amber-200 p-3 text-sm text-amber-900">
            <summary class="cursor-pointer">{{ t("invoicing.migration.original_scan_issues", { count: originalScanIssues.length }) }}</summary>
            <ul class="mt-2 max-h-40 space-y-1 overflow-auto">
              <li v-for="issue in originalScanIssues" :key="issue.index" class="break-words">
                <strong>{{ issue.filename }}</strong>
                <span class="ml-2">{{ issue.reason ? t(`invoicing.migration.isdoc_reason_${issue.reason}`) : migrationMessage(issue.code) }}</span>
              </li>
            </ul>
          </details>
          <div v-if="migrationPreview.totals.length" class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <caption class="text-left pb-2 text-slate-600">{{ t("invoicing.migration.totals_caption") }}</caption>
              <thead><tr><th class="p-2">{{ t("invoicing.migration.currency") }}</th><th class="p-2">{{ t("invoicing.col_total") }}</th><th class="p-2">{{ t("invoicing.migration.paid") }}</th><th class="p-2">{{ t("invoicing.migration.balance") }}</th></tr></thead>
              <tbody><tr v-for="total in migrationPreview.totals" :key="total.currency"><td class="p-2">{{ total.currency }}</td><td class="p-2">{{ total.total }}</td><td class="p-2">{{ total.paid }}</td><td class="p-2">{{ total.balance }}</td></tr></tbody>
            </table>
          </div>
          <div class="max-h-[32rem] overflow-auto rounded-lg border border-slate-200" tabindex="0" :aria-label="t('invoicing.migration.all_rows')">
            <table class="w-full text-sm text-left">
              <caption class="text-left p-2">{{ t("invoicing.migration.all_rows") }}</caption>
              <thead class="sticky top-0 bg-slate-50"><tr><th class="p-2">#</th><th class="p-2">{{ t("invoicing.col_number") }}</th><th class="p-2">{{ t("invoicing.col_total") }}</th><th class="p-2">{{ t("invoicing.migration.paid") }}</th><th class="p-2">{{ t("invoicing.migration.balance") }}</th><th class="p-2">{{ t("invoicing.import_preview_status") }}</th><th v-if="reviewOriginalItems" class="p-2">{{ t("invoicing.migration.items") }}</th></tr></thead>
              <tbody><tr v-for="entry in migrationPreview.rows" :key="entry.row" class="border-t border-slate-100">
                <td class="p-2">{{ entry.row }}</td><td class="p-2">{{ entry.invoice_number || '-' }}<small v-if="selectedSource === 'fakturoid' && entry.invoice" class="block text-slate-600">{{ entry.invoice.client_name }}</small></td>
                <td class="p-2 whitespace-nowrap">{{ entry.invoice?.total }} {{ entry.invoice?.currency }}</td>
                <td class="p-2">{{ entry.invoice?.amount_paid }}</td><td class="p-2">{{ entry.invoice?.balance }}</td>
                <td class="p-2">
                  <span :class="entry.kind === 'invalid' || entry.kind === 'conflict' ? 'text-red-700' : 'text-slate-700'">
                    {{ entry.kind === 'ready'
                      ? t(`invoicing.migration.state_${entry.invoice?.state}`)
                      : entry.code === 'structured_conflict' && entry.structuredMismatch
                        ? t(`invoicing.migration.structured_mismatch_${entry.structuredMismatch}`)
                        : migrationMessage(entry.code ?? entry.kind) }}
                  </span>
                  <small v-if="entry.invoice?.paid_at" class="block">{{ entry.invoice.paid_at }}</small>
                </td>
                <td v-if="reviewOriginalItems" class="p-2 align-top">
                  <details v-if="entry.invoice?.structured" class="min-w-36">
                    <summary class="cursor-pointer text-indigo-700">{{ t("invoicing.migration.item_count", { count: entry.invoice.structured.lines.length }) }}</summary>
                    <ul class="mt-2 space-y-2">
                      <li v-for="(line, index) in entry.invoice.structured.lines" :key="index" class="break-words">
                        <span class="block font-medium">{{ line.name }}</span>
                        <span>{{ t("invoicing.migration.item_amounts", { quantity: line.quantity, unit: line.unit ?? "", gross: line.lineTotal, currency: entry.invoice.currency, tax: line.taxRate }) }}</span>
                      </li>
                    </ul>
                  </details>
                  <span v-else-if="entry.kind === 'ready'" class="text-amber-800">{{ t("invoicing.migration.summary_item") }}</span>
                </td>
              </tr></tbody>
            </table>
          </div>
          <label v-if="summaryRows > 0" class="flex items-start gap-2 rounded-lg border border-amber-200 p-3 text-sm text-amber-900">
            <input v-model="allowSummary" type="checkbox" class="mt-0.5 rounded border-gray-300" :disabled="importing || importDone" />
            <span>{{ t("invoicing.migration.allow_summary", { count: summaryRows }) }}</span>
          </label>
        </template>

        <div
          v-if="!localFirst && previewRows.length"
          class="overflow-x-auto border border-gray-200 rounded-lg"
        >
          <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600">
              <tr>
                <th class="px-3 py-2">{{ t("invoicing.col_number") }}</th>
                <th class="px-3 py-2">{{ t("invoicing.col_client") }}</th>
                <th class="px-3 py-2">{{ t("invoicing.col_dates") }}</th>
                <th class="px-3 py-2 text-right">
                  {{ t("invoicing.col_total") }}
                </th>
                <th class="px-3 py-2">
                  {{ t("invoicing.import_preview_status") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in previewRows"
                :key="row.row"
                class="border-t border-gray-100"
              >
                <td class="px-3 py-2">{{ row.invoice_number || "-" }}</td>
                <td class="px-3 py-2">{{ row.client_name || "-" }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <template v-if="row.issue_date"
                    >{{ row.issue_date }} → {{ row.due_date }}</template
                  >
                  <span v-else class="text-red-600">{{ row.error }}</span>
                </td>
                <td class="px-3 py-2 text-right">
                  <template v-if="row.amount != null"
                    >{{ row.amount }} {{ row.currency }}</template
                  >
                </td>
                <td class="px-3 py-2 text-xs">
                  <span v-if="row.paid" class="text-green-700">{{
                    t("invoicing.filter_paid")
                  }}</span>
                  <span v-else-if="!row.error" class="text-gray-600">{{
                    t("invoicing.filter_unpaid")
                  }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div
        v-if="importResult"
        class="text-sm space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4"
      >
        <p class="text-green-700 font-medium">
          {{
            t("invoicing.import_result", {
              imported: importResult.imported,
              skipped: importResult.skipped,
            })
          }}
        </p>
        <p
          v-if="importResult.contacts_created || importResult.contacts_linked"
          class="text-gray-700"
        >
          {{
            t("invoicing.import_contacts_result", {
              created: importResult.contacts_created ?? 0,
              linked: importResult.contacts_linked ?? 0,
            })
          }}
        </p>
        <div v-if="importResult.errors.length">
          <p class="font-medium text-amber-900 mb-2">
            {{
              t("invoicing.import_skipped_heading", {
                count: importResult.errors.length,
              })
            }}
          </p>
          <ul class="text-amber-800 space-y-1 max-h-48 overflow-auto">
            <li v-for="(err, idx) in importResult.errors" :key="idx">
              {{ formatSkippedError(err) }}
            </li>
          </ul>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="invoicing-btn-secondary"
          :disabled="importing"
          @click="step = 2"
        >
          {{ t("common.back") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary px-8"
          :disabled="importing || storageNeedsRestart || !selectedFile || (localFirst && (!migrationPreview || !migrationPreview.ready || !!migrationPreview.invalid || !!migrationPreview.conflict || importDone || (summaryRows > 0 && !allowSummary)))"
          @click="runImport"
        >
          {{
            importing
              ? archiveProgress
                ? t("invoicing.migration.saving_originals", archiveProgress)
                : importProgress
                ? t("invoicing.import_progress_counter", { done: importProgress.done, total: importProgress.total })
                : t("common.loading")
              : t("invoicing.import_submit")
          }}
        </button>
        <RouterLink
          v-if="importDone"
          :to="{ name: 'invoicing-invoices', params: { companyId } }"
          class="invoicing-btn-secondary"
        >
          {{ t("invoicing.import_go_invoices") }}
        </RouterLink>
      </div>
    </section>
    <section v-if="localFirst" class="invoicing-card-pad mt-8 max-w-4xl space-y-3">
      <h2 class="font-semibold">{{ t("invoicing.migration.archive_title") }}</h2>
      <p class="text-sm text-slate-600">{{ t("invoicing.migration.archive_hint") }}</p>
      <p v-if="archiveError" class="text-sm text-red-700" role="alert">{{ archiveError }}</p>
      <button type="button" class="invoicing-btn-secondary" :disabled="importing || archivesLoading" @click="refreshArchives">{{ t("invoicing.migration.refresh_archive") }}</button>
      <p v-if="!archivesLoading && !archives.length" class="text-sm text-slate-500">{{ t("invoicing.migration.archive_empty") }}</p>
      <ul class="divide-y divide-slate-100">
        <li v-for="source in archives" :key="source.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
          <span class="break-all"><span class="mr-2 text-slate-500">{{ t(source.sourceKind === 'original' ? 'invoicing.migration.original_document' : 'invoicing.migration.source_export') }}</span>{{ source.originalFilename }} <span class="text-slate-500">({{ source.sizeBytes }} {{ t("invoicing.migration.bytes") }})</span></span>
          <button type="button" class="invoicing-btn-secondary" :disabled="importing || !!downloadingSource" @click="downloadOriginal(source)">{{ t("invoicing.migration.download_original") }}</button>
        </li>
      </ul>
    </section>
    </template>
    <section v-else class="invoicing-card-pad space-y-4 max-w-3xl">
      <h2 class="text-lg font-semibold text-gray-800">{{ t("invoicing.expense_import_title") }}</h2>
      <p class="text-sm text-gray-600">{{ t("invoicing.expense_import_intro") }}</p>
      <p class="text-sm text-gray-600">{{ t("invoicing.expense_import_metadata_hint") }}</p>
      <button type="button" class="invoicing-btn-primary" @click="showExpenseImport = true">
        {{ t("invoicing.expense_import_title") }}
      </button>
      <ExpenseImportModal
        :open="showExpenseImport"
        :company-id="companyId"
        :local-first="localFirst"
        @close="showExpenseImport = false"
      />
    </section>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import ExpenseImportModal from "../../components/invoicing/ExpenseImportModal.vue";
import {
  DOCUMENT_IMPORT_FIELD_KEYS,
  DOCUMENT_IMPORT_SOURCES,
  REQUIRED_DOCUMENT_IMPORT_FIELDS,
  type DocumentImportFieldKey,
  type DocumentImportMapping,
  type DocumentImportSource,
} from "../../composables/useDocumentImportFields";
import {
  allCompaniesDetailQuery,
  allContactsQuery,
  allDocumentsQuery,
  allDocumentLinesQuery,
  allMigrationSourcesQuery,
  useInvoicingEvolu,
} from "../../evolu/client";
import {
  evoluCompanyToApi,
  type EvoluCompanyRow,
} from "../../evolu/companyMap";
import {
  buildDocumentImportExampleCsvBlob,
  downloadCsvBlob,
  isDocumentImportSpreadsheetFile,
  parseDocumentImportFile,
  suggestDocumentMapping,
  type DocumentImportPreviewRow,
  type ImportDateFormat,
} from "../../evolu/documentImportLocal";
import { isInvoicingLocalFirst } from "../../evolu/flags";
import type { CompanyId } from "../../evolu/schema";
import { useInvoicingLayout } from "../../composables/useInvoicingLayout";
import { useStoresStore } from "../../store/stores";
import { invoicingApi } from "../../services/api";

import type { EvoluDocumentRow, EvoluDocumentLineRow } from "../../evolu/documentMap";
import { buildPreparedMigrationPreview, buildMigrationPreview, type MigrationPreview } from "../../evolu/migrationValidation";
import { fetchZapriteMigrationExport } from "../../services/zapriteMigration";
import { parseFakturoidMigrationExport } from "../../evolu/migrationFakturoid";
import { parseZapriteMigrationExport } from "../../evolu/migrationZaprite";
import { importMigrationFile, MigrationImportStorageError } from "../../evolu/migrationImport";
import { assertMigrationSchemaReady } from "../../evolu/migrationSchema";
import { MIGRATION_MAX_ORIGINALS, readMigrationOriginal, validateMigrationOriginalFiles, type MigrationSource } from "../../evolu/migrationArchive";
import { MigrationIsdocError, type MigrationIsdocRejection, type MigrationStructuredInvoice } from "../../evolu/migrationIsdoc";
import { companyShareInfo, companyShareRoutingKey, waitCompanyShareRegistryReady } from "../../evolu/companyShareRegistry";
import { toAppRows } from "../../evolu/queryLoad";
import { useAuthStore } from "../../store/auth";
import { hasEffectiveFeature } from "../../composables/useEffectiveAccess";

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const auth = useAuthStore();
const evolu = localFirst ? useInvoicingEvolu() : null;
const storesStore = useStoresStore();
const { companyId, rememberCompany } = useInvoicingLayout();

const step = ref(1);
const importKind = ref<"invoices" | "expenses">("invoices");
const showExpenseImport = ref(false);
const selectedSource = ref<DocumentImportSource | null>(localFirst ? "superfaktura" : "excel");
const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const csvRows = ref<string[][]>([]);
const headers = ref<string[]>([]);
const mapping = reactive<DocumentImportMapping>({});
const previewRows = ref<DocumentImportPreviewRow[]>([]);
const rowCount = ref(0);
const oneRowPerInvoice = ref(true);
const createContacts = ref(true);
const lineName = ref("Imported item");
const lineDescription = ref("");
const dateFormat = ref<ImportDateFormat>("auto");
const loading = ref(false);
const importing = ref(false);
const storageNeedsRestart = ref(false);
const importProgress = ref<{ done: number; total: number } | null>(null);
const error = ref("");
const importDone = ref(false);

type ImportSkippedError = {
  row: number;
  invoice_number?: string | null;
  message: string;
};

type ImportResult = {
  imported: number;
  skipped: number;
  contacts_created?: number;
  contacts_linked?: number;
  errors: ImportSkippedError[];
};

const importResult = ref<ImportResult | null>(null);

const stepLabels = computed(() => [
  t("invoicing.import_step1"),
  t("invoicing.import_step2"),
  t("invoicing.import_step3"),
]);

const mappingReady = computed(() => selectedSource.value === "fakturoid" ? !!fakturoidInfo.value
  : selectedSource.value === "zaprite" ? !!zapriteInfo.value :
  REQUIRED_DOCUMENT_IMPORT_FIELDS.every(
    (field) => mapping[field] !== null && mapping[field] !== undefined,
  ),
);

function isRequired(field: DocumentImportFieldKey) {
  return REQUIRED_DOCUMENT_IMPORT_FIELDS.includes(field);
}

function formatSkippedError(err: ImportSkippedError) {
  const message = err.message === "contact_write_failed"
    ? t("invoicing.migration.error_contact_write_failed") : err.message;
  if (err.invoice_number) {
    return t("invoicing.import_skipped_row_with_invoice", {
      invoice: err.invoice_number,
      row: err.row,
      message,
    });
  }

  return t("invoicing.import_skipped_row", {
    row: err.row,
    message,
  });
}

function sourceIcon(id: DocumentImportSource) {
  const icons: Record<DocumentImportSource, string> = {
    superfaktura: "SF",
    excel: "XLS",
    money: "M",
    pohoda: "P",
    isdoc: "ID",
    idoklad: "iD",
    zaprite: "Z",
    fakturoid: "F",
  };
  return icons[id];
}

function selectSource(src: (typeof DOCUMENT_IMPORT_SOURCES)[number]) {
  if (!src.enabled || importing.value) return;
  if (selectedSource.value !== src.id) {
    cancelZaprite(); fileReadVersion++;
    selectedFile.value = null; headers.value = []; csvRows.value = []; originalFiles.value = []; zapriteInfo.value = null; fakturoidInfo.value = null;
  }
  selectedSource.value = src.id;
}

function localCompanyApi() {
  if (!evolu) return null;
  const row = evolu
    .getQueryRows(allCompaniesDetailQuery)
    .find((c) => c.id === companyId.value);
  if (!row) return null;
  return evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
    const store = storesStore.stores.find((s) => s.id === storeId);
    return store
      ? {
          id: store.id,
          name: store.name,
          ...(store.default_currency !== undefined ? { default_currency: store.default_currency } : {}),
        }
      : undefined;
  });
}

async function ensureLocalQueriesLoaded() {
  if (!evolu) return;
  await assertMigrationSchemaReady(evolu);
  await Promise.all([
    evolu.loadQuery(allCompaniesDetailQuery),
    evolu.loadQuery(allDocumentsQuery),
    evolu.loadQuery(allContactsQuery),
  ]);
}

function pickFile(file: File | undefined) {
  if (!file || importing.value) return;
  sourceSelectionRevision.value++;
  selectedFile.value = file;
  zapriteInfo.value = null; fakturoidInfo.value = null;
  csvRows.value = [];
  headers.value = [];
  previewRows.value = [];
  rowCount.value = 0;
  importResult.value = null;
  importDone.value = false;
  for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
    mapping[field] = null;
  }
  error.value = "";
  void loadPreview();
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  pickFile(input.files?.[0]);
}

function onDrop(e: DragEvent) {
  pickFile(e.dataTransfer?.files?.[0]);
}

function buildFormData(includeMapping: boolean) {
  const form = new FormData();
  if (selectedFile.value) form.append("file", selectedFile.value);
  if (includeMapping) form.append("mapping", JSON.stringify(mapping));
  form.append("line_name", lineName.value);
  if (lineDescription.value)
    form.append("line_description", lineDescription.value);
  form.append("create_contacts", createContacts.value ? "1" : "0");
  form.append("date_format", dateFormat.value);
  return form;
}

async function loadPreview() {
  if (!selectedFile.value) return;
  const file = selectedFile.value;
  const target = companyId.value;
  const account = auth.user?.id;
  const source = selectedSource.value;
  const version = ++fileReadVersion;
  const operation = ++loadingVersion;
  const isCurrent = () => !disposed && version === fileReadVersion && file === selectedFile.value
    && target === companyId.value && account === auth.user?.id && source === selectedSource.value;
  loading.value = true;
  error.value = "";
  try {
    if (localFirst) {
      if (!isSelectedImportFile(selectedFile.value)) {
        error.value = importFileInvalidMessage();
        headers.value = [];
        rowCount.value = 0;
        csvRows.value = [];
        return;
      }
      if (source === "fakturoid") {
        const context = await migrationContext();
        if (!isCurrent()) return;
        const bytes = new Uint8Array(await file.arrayBuffer());
        if (!isCurrent()) return;
        const parsed = parseFakturoidMigrationExport(bytes, { companyRegistrationNumber: context.registrationNumber });
        if (!isCurrent()) return;
        fakturoidInfo.value = parsed; rowCount.value = parsed.rows.length;
        return;
      }
      if (source === "zaprite") {
        const parsed = parseZapriteMigrationExport(new Uint8Array(await file.arrayBuffer()), { excludeUnsupportedCurrencies: excludeUnsupportedCurrencies.value });
        if (!isCurrent()) return;
        zapriteInfo.value = parsed; rowCount.value = parsed.rows.length;
        return;
      }
      const parsed = await parseDocumentImportFile(file);
      if (!isCurrent()) return;
      csvRows.value = parsed.rows;
      headers.value = parsed.headers;
      rowCount.value = parsed.rows.length;
      const suggested = suggestDocumentMapping(parsed.headers, selectedSource.value ?? undefined);
      for (const field of DOCUMENT_IMPORT_FIELD_KEYS) mapping[field] = suggested[field] ?? null;
      return;
    }

    const data = await invoicingApi.documents.import.preview<{
      headers?: string[];
      row_count?: number;
      suggested_mapping?: Record<string, number | null>;
    }>(companyId.value, buildFormData(false));
    if (!isCurrent()) return;
    headers.value = data.headers ?? [];
    rowCount.value = data.row_count ?? 0;
    const suggested = data.suggested_mapping ?? {};
    for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
      mapping[field] = suggested[field] ?? null;
    }
  } catch (rawError) {
    if (!isCurrent()) return;
    const e = asApiError(rawError);
    error.value = localFirst ? migrationFailureMessage(rawError) : e?.response?.data?.message || t("errors.generic");
    headers.value = [];
  } finally {
    if (operation === loadingVersion && isCurrent()) loading.value = false;
  }
}

async function goToConfirm() {
  if (!selectedFile.value || !mappingReady.value || loading.value) return;
  const file = selectedFile.value;
  const key = formKey.value;
  const operation = ++loadingVersion;
  const isCurrent = () => !disposed && file === selectedFile.value && formKey.value === key;
  loading.value = true;
  error.value = "";
  try {
    if (localFirst) {
      if (!isSelectedImportFile(selectedFile.value)) {
        error.value = importFileInvalidMessage();
        return;
      }
      validateMigrationOriginalFiles(selectedFile.value, originalFiles.value);
      await assertMigrationSchemaReady(evolu!);
      const context = await migrationContext();
      if (!isCurrent()) return;
      const evidence = await scanOriginals(key);
      if (!isCurrent()) return;
      const company = localCompanyApi();
      const provider = selectedSource.value === "fakturoid"
        ? parseFakturoidMigrationExport(new Uint8Array(await file.arrayBuffer()), { companyRegistrationNumber: context.registrationNumber })
        : selectedSource.value === "zaprite" ? parseZapriteMigrationExport(new Uint8Array(await file.arrayBuffer()), { excludeUnsupportedCurrencies: excludeUnsupportedCurrencies.value }) : null;
      const currentRows = toAppRows<EvoluDocumentRow & { ownerId?: string }>(await evolu!.loadQuery(allDocumentsQuery));
      const currentLines = toAppRows<EvoluDocumentLineRow & { ownerId?: string }>(await evolu!.loadQuery(allDocumentLinesQuery));
      const preview = provider ? buildPreparedMigrationPreview(provider.rows, {
        companyId: companyId.value, ownerId: context.ownerId, existing: currentRows, lines: currentLines,
      }) : buildMigrationPreview(csvRows.value, { ...mapping }, {
        companyId: companyId.value, ownerId: context.ownerId,
        defaultCurrency: company?.default_currency ?? "EUR", dateFormat: dateFormat.value,
        companyRegistrationNumber: context.registrationNumber,
        structuredInvoices: evidence.invoices,
        existing: currentRows,
        lines: currentLines,
      });
      if (!isCurrent()) return;
      migrationPreview.value = preview;
      originalScanIssues.value = evidence.issues;
      allowSummary.value = false;
      previewKey = key;
      previewOwnerId = context.ownerId;
      rowCount.value = preview.rows.length;
      step.value = 3;
      return;
    }

    const data = await invoicingApi.documents.import.preview<{
      preview?: typeof previewRows.value;
      row_count?: number;
    }>(companyId.value, buildFormData(true));
    if (!isCurrent()) return;
    previewRows.value = data.preview ?? [];
    rowCount.value = data.row_count ?? 0;
    step.value = 3;
  } catch (rawError) {
    if (!isCurrent()) return;
    const e = asApiError(rawError);
    error.value = localFirst ? migrationFailureMessage(rawError) : e?.response?.data?.message || t("errors.generic");
  } finally {
    if (operation === loadingVersion) { originalScanProgress.value = null; loading.value = false; }
  }
}

async function runImport() {
  if (!selectedFile.value || importing.value || loading.value || storageNeedsRestart.value) return;
  importing.value = true;
  error.value = "";
  importResult.value = null;
  try {
    if (localFirst && evolu) {
      if (!isSelectedImportFile(selectedFile.value)) {
        error.value = importFileInvalidMessage();
        return;
      }
      const key = previewKey;
      const target = companyId.value;
      const ownerId = previewOwnerId;
      const accountId = auth.user?.id;
      const assertCurrent = () => {
        if (disposed || formKey.value !== key || companyId.value !== target || auth.user?.id !== accountId) throw new Error("preview_changed");
      };
      assertCurrent();
      const expectedPreview = migrationPreview.value;
      if (!expectedPreview || expectedPreview.invalid || expectedPreview.conflict) throw new Error("preview_blocked");
      if (summaryRows.value > 0 && !allowSummary.value) throw new Error("summary_confirmation_required");
      if (!await auth.fetchUser() || auth.user?.id !== accountId || auth.user?.is_guest || !auth.user?.email_verified_at
          || (!companyShareInfo(target) && !hasEffectiveFeature(auth.user, "business_invoicing"))) throw new Error("access_unavailable");
      assertCurrent();
      const context = await migrationContext();
      if (context.ownerId !== ownerId) throw new Error("preview_changed");
      const file = selectedFile.value;
      validateMigrationOriginalFiles(file, originalFiles.value);
      const bytes = new Uint8Array(await file.arrayBuffer());
      const originals = [];
      for (const original of originalFiles.value) {
        assertCurrent();
        originals.push({ file: original, bytes: new Uint8Array(await original.arrayBuffer()) });
      }
      assertCurrent();
      const company = localCompanyApi();
      const result = await importMigrationFile(evolu, {
        companyId: target as CompanyId, ownerId, sourceKind: selectedSource.value ?? "excel", file, bytes, originals,
        rows: csvRows.value, mapping: { ...mapping }, defaultCurrency: company?.default_currency ?? "EUR", dateFormat: dateFormat.value,
        createContacts: createContacts.value, lineName: lineName.value, lineDescription: lineDescription.value,
        noteFooter: company?.legal_footer_note ?? null, assertCurrent,
        allowSummary: allowSummary.value, expectedPreview, excludeUnsupportedCurrencies: excludeUnsupportedCurrencies.value,
        onArchiveProgress: (done, total) => {
          if (!disposed && companyId.value === target) archiveProgress.value = done < total ? { done, total } : null;
        },
        onProgress: (done, total) => { if (!disposed && companyId.value === target) importProgress.value = { done, total }; },
      });
      assertCurrent();
      importProgress.value = null;
      importResult.value = { imported: result.imported, skipped: result.skipped, errors: [] };
      importDone.value = true;
      await refreshArchives();
      return;
    }

    const result = await invoicingApi.documents.import.run<Partial<ImportResult>>(companyId.value, buildFormData(true));
    importResult.value = {
      imported: result.imported ?? 0,
      skipped: result.skipped ?? 0,
      contacts_created: result.contacts_created ?? 0,
      contacts_linked: result.contacts_linked ?? 0,
      errors: result.errors ?? [],
    };
    importDone.value = true;
  } catch (rawError) {
    const e = asApiError(rawError);
    error.value = localFirst ? migrationFailureMessage(rawError) : e?.response?.data?.message || t("errors.generic");
  } finally {
    archiveProgress.value = null;
    importing.value = false;
  }
}

async function downloadExample() {
  if (localFirst) {
    downloadCsvBlob(
      buildDocumentImportExampleCsvBlob(),
      "invoice_import_example.csv",
    );
    return;
  }

  try {
    const data = await invoicingApi.documents.import.example(companyId.value);
    const url = URL.createObjectURL(data);
    const a = document.createElement("a");
    a.href = url;
    a.download = "invoice_import_example.xlsx";
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = t("errors.generic");
  }
}

const migrationPreview = ref<MigrationPreview | null>(null);
const originalFiles = ref<File[]>([]);
const sourceSelectionRevision = ref(0);
const originalSelectionRevision = ref(0);
const archiveProgress = ref<{ done: number; total: number } | null>(null);
const originalScanProgress = ref<{ done: number; total: number } | null>(null);
type OriginalScanIssue = { index: number; filename: string; code: string; reason?: MigrationIsdocRejection };
const originalScanIssues = ref<OriginalScanIssue[]>([]);
const allowSummary = ref(false);
const reviewOriginalItems = computed(() => selectedSource.value === "fakturoid" || selectedSource.value === "zaprite" || (selectedSource.value === "superfaktura" && originalFiles.value.length > 0));
const summaryRows = computed(() => reviewOriginalItems.value
  ? (migrationPreview.value?.rows.filter(row => row.kind === "ready" && !row.invoice?.structured).length ?? 0)
  : 0);
const archives = ref<MigrationSource[]>([]);
const archivesLoading = ref(false);
const archiveError = ref("");
const downloadingSource = ref("");
const availableSources = computed(() => localFirst
  ? DOCUMENT_IMPORT_SOURCES.filter(source => source.enabled)
  : DOCUMENT_IMPORT_SOURCES.filter(source => !["superfaktura", "zaprite", "fakturoid"].includes(source.id)));
const visibleFields = computed(() => localFirst ? DOCUMENT_IMPORT_FIELD_KEYS
  : DOCUMENT_IMPORT_FIELD_KEYS.filter(field => !["amount_paid", "payment_status", "payment_currency"].includes(field)));
const formKey = computed(() => JSON.stringify([companyId.value, companyShareRoutingKey(companyId.value), auth.user?.id, selectedSource.value,
  selectedFile.value?.name, selectedFile.value?.size, selectedFile.value?.lastModified, sourceSelectionRevision.value, dateFormat.value,
  originalSelectionRevision.value, excludeUnsupportedCurrencies.value,
  { ...mapping }, lineName.value, lineDescription.value, createContacts.value]));
let previewKey = "";
let previewOwnerId = "";
let disposed = false;
let fileReadVersion = 0;
let loadingVersion = 0;
let archiveVersion = 0;
const waiting = new Set<AbortController>();
const fakturoidInfo = ref<ReturnType<typeof parseFakturoidMigrationExport> | null>(null);
const zapriteApiKey = ref("");
const excludeUnsupportedCurrencies = ref(false);
const zapriteInfo = ref<ReturnType<typeof parseZapriteMigrationExport> | null>(null);
const zapriteProgress = ref<{ page: number; pages: number } | null>(null);
const zapriteExcluded = computed(() => zapriteInfo.value?.excludedCurrencies.map(item => `${item.currency}: ${item.count}`).join(", ") ?? "");
let zapriteRequest: AbortController | null = null;
let zapriteGeneration = 0;
function cancelZaprite() {
  zapriteGeneration++; loadingVersion++;
  zapriteRequest?.abort(); zapriteRequest = null; zapriteApiKey.value = ""; zapriteProgress.value = null; loading.value = false;
}
function importFileInvalidMessage() {
  if (selectedSource.value === "fakturoid") return t("invoicing.migration.error_fakturoid_invalid_export");
  if (selectedSource.value === "zaprite") return t("invoicing.migration.error_zaprite_invalid_export");
  return t("invoicing.import_file_invalid");
}
function isSelectedImportFile(file: File) {
  if (selectedSource.value === "fakturoid") return /\.xml$/i.test(file.name) && file.size > 0 && file.size <= 10 * 1024 * 1024;
  return selectedSource.value === "zaprite" ? /\.json$/i.test(file.name) && file.size <= 10 * 1024 * 1024 : isDocumentImportSpreadsheetFile(file);
}
async function loadZaprite() {
  if (loading.value || !zapriteApiKey.value.trim()) return;
  let key = zapriteApiKey.value.trim();
  cancelZaprite();
  const generation = zapriteGeneration;
  const target = companyId.value;
  const account = auth.user?.id;
  const controller = new AbortController(); zapriteRequest = controller;
  selectedFile.value = null; zapriteInfo.value = null;
  loading.value = true; error.value = "";
  try {
    await migrationContext();
    if (disposed || generation !== zapriteGeneration || account !== auth.user?.id || target !== companyId.value) return;
    const bytes = await fetchZapriteMigrationExport(key, { signal: controller.signal, onProgress: (page, pages) => {
      if (generation === zapriteGeneration && !disposed) zapriteProgress.value = { page, pages };
    } });
    if (disposed || generation !== zapriteGeneration || account !== auth.user?.id || target !== companyId.value) return;
    const file = new File([new Uint8Array(bytes).buffer], "zaprite-export.json", { type: "application/json" });
    selectedFile.value = file; sourceSelectionRevision.value++;
    zapriteInfo.value = parseZapriteMigrationExport(bytes, { excludeUnsupportedCurrencies: excludeUnsupportedCurrencies.value });
    rowCount.value = zapriteInfo.value.rows.length;
  } catch (failure) {
    if (!disposed && generation === zapriteGeneration) error.value = migrationFailureMessage(failure);
  } finally {
    key = "";
    if (generation === zapriteGeneration) { loading.value = false; zapriteProgress.value = null; zapriteRequest = null; }
  }
}
watch(excludeUnsupportedCurrencies, () => { if (selectedSource.value === "zaprite" && selectedFile.value) void loadPreview(); });
watch(() => auth.user?.id, () => {
  cancelZaprite(); fileReadVersion++; archiveVersion++;
  selectedFile.value = null; zapriteInfo.value = null; fakturoidInfo.value = null; loading.value = false;
  archives.value = []; archiveError.value = ""; headers.value = []; csvRows.value = []; originalFiles.value = []; step.value = 1;
}, { flush: "sync" });

function pickOriginals(event: Event) {
  if (importing.value) return;
  originalFiles.value = Array.from((event.target as HTMLInputElement).files ?? []);
  originalSelectionRevision.value++;
  error.value = "";
  if (selectedFile.value) {
    try { validateMigrationOriginalFiles(selectedFile.value, originalFiles.value); }
    catch (failure) { error.value = migrationMessage(failure instanceof Error ? failure.message : "failed"); }
  }
}
async function scanOriginals(key: string): Promise<{ invoices: MigrationStructuredInvoice[]; issues: OriginalScanIssue[] }> {
  const invoices: MigrationStructuredInvoice[] = [];
  const issues: OriginalScanIssue[] = [];
  if (selectedSource.value !== "superfaktura" || !reviewOriginalItems.value) return { invoices, issues };
  const files = [...originalFiles.value];
  const { readMigrationIsdocEvidence } = await import("../../evolu/migrationIsdocPdf");
  originalScanProgress.value = { done: 0, total: files.length };
  for (const [index, file] of files.entries()) {
    if (disposed || formKey.value !== key) throw new Error("preview_changed");
    try {
      const invoice = await readMigrationIsdocEvidence(file.name, new Uint8Array(await file.arrayBuffer()));
      if (invoice) invoices.push(invoice);
      else issues.push({ index, filename: file.name, code: "migration_isdoc_missing" });
    } catch (failure) {
      const known = failure instanceof MigrationIsdocError;
      issues.push({
        index, filename: file.name,
        code: known ? failure.message : "migration_isdoc_unreadable",
        reason: known ? failure.reason : undefined,
      });
    }
    originalScanProgress.value = { done: index + 1, total: files.length };
  }
  return { invoices, issues };
}
function fieldLabel(field: DocumentImportFieldKey) {
  return ["amount_paid", "payment_status", "payment_currency"].includes(field)
    ? t(`invoicing.migration.field_${field}`) : t(`invoicing.import_field_${field}`);
}
function migrationFailureMessage(failure: unknown): string {
  if (failure instanceof MigrationImportStorageError) {
    storageNeedsRestart.value = true;
    return t("invoicing.migration.error_storage_details", {
      phase: t(`invoicing.migration.storage_phase_${failure.phase}`),
      reason: t(`invoicing.migration.storage_reason_${failure.reason}`),
    });
  }
  const code = failure instanceof Error ? failure.message : "failed";
  if (code === "migration_schema_outdated") storageNeedsRestart.value = true;
  return migrationMessage(code);
}
function migrationMessage(code: string) {
  const known = ["existing_duplicate", "existing_conflict", "in_file_duplicate", "in_file_conflict", "invalid_amount", "invalid_currency",
    "unsupported_status", "missing_payment_evidence", "payment_currency_mismatch", "contradictory_payment", "too_many_rows", "too_many_columns", "file_too_large",
    "invalid_csv", "invalid_original", "archive_unverified", "preview_changed", "preview_blocked", "access_unavailable", "nothing_to_import",
    "bank_reconciliation_outcome_unknown", "migration_schema_outdated", "contact_write_failed", "document_readback_failed", "document_write_failed", "structured_conflict", "summary_confirmation_required",
    "fakturoid_invalid_export", "fakturoid_invalid_invoice", "fakturoid_totals", "fakturoid_payment_evidence", "fakturoid_unsupported", "fakturoid_company_mismatch", "fakturoid_unsupported_currency", "fakturoid_limit_exceeded",
    "zaprite_invalid_input", "zaprite_auth_failed", "zaprite_rate_limited", "zaprite_unavailable", "zaprite_response_invalid", "zaprite_limit_exceeded",
    "zaprite_cancelled", "zaprite_invalid_export", "zaprite_unsupported_currency", "zaprite_payment_evidence", "zaprite_totals", "zaprite_unsupported_status", "zaprite_invalid_invoice", "zaprite_incomplete_export",
    "migration_isdoc_missing", "migration_isdoc_invalid", "migration_isdoc_unsupported", "migration_isdoc_ambiguous", "migration_isdoc_unreadable"];
  if (code === "originals_limit") return t("invoicing.migration.error_originals_limit", { max: MIGRATION_MAX_ORIGINALS });
  if (known.includes(code)) return t(`invoicing.migration.error_${code}`);
  if (code.startsWith("invalid_")) return t("invoicing.migration.invalid_value");
  return t("invoicing.migration.error_failed");
}
async function migrationContext(): Promise<{ ownerId: string; registrationNumber: string | null }> {
  if (!evolu) throw new Error("access_unavailable");
  const target = companyId.value;
  const route = companyShareRoutingKey(target);
  const controller = new AbortController(); waiting.add(controller);
  const timer = setTimeout(() => controller.abort(), 10_000);
  try {
    await waitCompanyShareRegistryReady(controller.signal);
    const [companies, appOwner] = await Promise.all([evolu.loadQuery(allCompaniesDetailQuery), evolu.appOwner]);
    if (disposed || controller.signal.aborted || target !== companyId.value || companyShareRoutingKey(target) !== route) throw new Error("preview_changed");
    const matches = companies.filter(company => company.id === target);
    if (matches.length !== 1) throw new Error("access_unavailable");
    const ownerId = String(Reflect.get(matches[0], "ownerId") ?? "");
    const share = companyShareInfo(target);
    if (!ownerId || (share ? share.status !== "active" || share.ownerId !== ownerId : ownerId !== appOwner.id)) throw new Error("access_unavailable");
    return { ownerId, registrationNumber: matches[0].registrationNumber };
  } finally { clearTimeout(timer); waiting.delete(controller); }
}
async function refreshArchives() {
  if (!localFirst || !evolu) return;
  const version = ++archiveVersion;
  const target = companyId.value;
  archives.value = []; archiveError.value = ""; archivesLoading.value = true;
  try {
    await assertMigrationSchemaReady(evolu);
    const context = await migrationContext();
    const rows = toAppRows<MigrationSource>(await evolu.loadQuery(allMigrationSourcesQuery));
    if (!disposed && version === archiveVersion && target === companyId.value) archives.value = rows.filter(source => source.companyId === target && source.ownerId === context.ownerId);
  } catch (failure) {
    if (!disposed && version === archiveVersion) archiveError.value = migrationFailureMessage(failure);
  } finally { if (version === archiveVersion) archivesLoading.value = false; }
}
async function downloadOriginal(source: MigrationSource) {
  if (!evolu || downloadingSource.value) return;
  downloadingSource.value = source.id; archiveError.value = "";
  const target = companyId.value;
  const route = companyShareRoutingKey(target);
  try {
    const context = await migrationContext();
    const original = await readMigrationOriginal(evolu, target, context.ownerId, source.id);
    if (disposed || target !== companyId.value || route !== companyShareRoutingKey(target)) return;
    const blob = new Blob([new Uint8Array(original.bytes).buffer], { type: "application/octet-stream" });
    downloadCsvBlob(blob, original.source.originalFilename ?? "migration-export");
  } catch (failure) { archiveError.value = migrationFailureMessage(failure); }
  finally { downloadingSource.value = ""; }
}
watch(formKey, () => {
  migrationPreview.value = null; previewKey = ""; importDone.value = false;
  originalScanIssues.value = []; allowSummary.value = false;
  if (step.value === 3 && !importing.value) step.value = 2;
}, { flush: "sync" });
watch(() => [companyId.value, companyShareRoutingKey(companyId.value)], () => {
  cancelZaprite(); loading.value = false; zapriteInfo.value = null; fakturoidInfo.value = null;
  fileReadVersion++; archiveVersion++; archives.value = []; migrationPreview.value = null;
  selectedFile.value = null; originalFiles.value = []; headers.value = []; csvRows.value = []; step.value = 1; importResult.value = null;
  if (localFirst) void refreshArchives();
}, { flush: "sync" });
onBeforeUnmount(() => { cancelZaprite(); disposed = true; fileReadVersion++; archiveVersion++; waiting.forEach(controller => controller.abort()); });
onMounted(() => {
  rememberCompany(companyId.value);
  if (localFirst && evolu) {
    void ensureLocalQueriesLoaded().catch(failure => {
      if (!disposed) archiveError.value = migrationFailureMessage(failure);
    });
    void refreshArchives();
  }
});
</script>

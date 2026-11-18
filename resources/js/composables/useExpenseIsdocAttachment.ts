import { useEffectiveAccess } from "@/composables/useEffectiveAccess";
import { computed, onUnmounted, ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { createIdFromString, sqliteTrue } from '@evolu/common';
import { invoicingApi } from '../services/api';
import type { IsdocExtractQuota } from '../components/invoicing/ExpenseIsdocExtractModal.vue';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { expenseByIdIncludingDeletedQuery, useInvoicingEvolu } from '../evolu/client';
import { loadEvoluQueryWithTimeout } from '../evolu/queryLoad';
import {
  insertLocalExpenseAttachmentFromBase64,
  LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES,
  readFileAsAttachmentBase64,
} from '../evolu/expenseAttachmentCrud';
import type { ExpenseId } from '../evolu/schema';
import { saveManualExpenseAttachmentArchive } from '../evolu/manualExpenseAttachment';
import { EXPENSE_ORIGINAL_MAX_BYTES } from '../evolu/expenseOriginalReference';
import { companyShareRoutingKey } from '../evolu/companyShareRegistry';

export type ExpenseExtractionSource = 'isdoc' | 'pdf_text';

export type ExpenseImportDraft = {
  source?: ExpenseExtractionSource;
  supplier_name?: string | null;
  supplier_iban?: string | null;
  payment_review_required?: boolean | null;
  internal_note?: string | null;
  title?: string | null;
  external_number?: string | null;
  variable_symbol?: string | null;
  constant_symbol?: string | null;
  specific_symbol?: string | null;
  issue_date?: string;
  delivery_date?: string;
  due_date?: string | null;
  total?: number;
  currency?: string;
};

export type ExpenseFileDetectionStatus = 'pending' | 'skipped' | 'checking' | 'found' | 'text_found' | 'absent' | 'error';

export type PendingExpenseFile = {
  id: string;
  file: File;
  hasIsdoc: boolean;
  extractionSource: ExpenseExtractionSource | null;
  detectError: string;
  detectionStatus: ExpenseFileDetectionStatus;
};

function previewKindForFile(file: File): 'pdf' | 'image' | 'other' {
  if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
    return 'pdf';
  }
  if (file.type.startsWith('image/')) {
    return 'image';
  }
  return 'other';
}

function newPendingId() {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `pending-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function useExpenseIsdocAttachment(companyId: Ref<string>, expenseId?: Ref<string | undefined>) {
  const { t } = useI18n();
  const { purchases } = useEffectiveAccess();
  const localFirst = isInvoicingLocalFirst();
  const evolu = localFirst ? useInvoicingEvolu() : null;
  const quota = ref<IsdocExtractQuota | null>(null);
  const pendingFiles = ref<PendingExpenseFile[]>([]);
  const selectedPendingId = ref<string | null>(null);
  const isdocModalFileId = ref<string | null>(null);
  const processingConsentFileId = ref<string | null>(null);
  const consentQueue = ref<string[]>([]);
  const extractionQueue = ref<string[]>([]);
  const lastAddedFileId = ref<string | null>(null);
  const previewUrl = ref<string | null>(null);
  const previewKind = ref<'pdf' | 'image' | 'other' | null>(null);
  const showExtractModal = ref(false);
  const detecting = ref(false);
  const extracting = ref(false);
  const purchasing = ref(false);
  const selectionError = ref('');
  let generation = 0;
  let disposed = false;
  let activeDetections = 0;

  function context() {
    return { generation, company: companyId.value, expense: expenseId?.value,
      routing: localFirst ? companyShareRoutingKey(companyId.value) : 'server' };
  }

  function isCurrent(captured: ReturnType<typeof context>, fileId?: string) {
    return !disposed && captured.generation === generation
      && captured.company === companyId.value && captured.expense === expenseId?.value
      && captured.routing === (localFirst ? companyShareRoutingKey(companyId.value) : 'server')
      && (fileId === undefined || pendingFiles.value.some((entry) => entry.id === fileId));
  }

  const selectedPending = computed(
    () => pendingFiles.value.find((entry) => entry.id === selectedPendingId.value)
      ?? pendingFiles.value[0]
      ?? null,
  );

  const pendingAttachment = computed(() => selectedPending.value?.file ?? null);

  const pendingAttachmentName = computed(() => selectedPending.value?.file.name ?? '');

  const pendingFileSummaries = computed(() =>
    pendingFiles.value.map((entry) => ({
      id: entry.id,
      name: entry.file.name,
      detectionStatus: entry.detectionStatus,
    })),
  );

  const lastDetectHasIsdoc = computed(() => selectedPending.value?.hasIsdoc ?? false);
  const extractionSource = computed(() => pendingFiles.value.find(entry => entry.id === isdocModalFileId.value)?.extractionSource ?? null);

  const detectError = computed(() => selectedPending.value?.detectError || selectionError.value);
  const detectionStatus = computed(() => selectedPending.value?.detectionStatus ?? null);
  const processingConsentFileName = computed(() => pendingFiles.value.find(entry => entry.id === processingConsentFileId.value)?.file.name ?? '');
  const showProcessingConsent = computed(() => processingConsentFileId.value !== null);
  const hasUnresolvedProcessing = computed(() => showProcessingConsent.value || showExtractModal.value
    || detecting.value || extracting.value || consentQueue.value.length > 0 || extractionQueue.value.length > 0
    || pendingFiles.value.some(entry => entry.detectionStatus === 'pending' || entry.detectionStatus === 'checking'));
  const readyToUpload = computed(() => pendingFiles.value.length > 0 && !hasUnresolvedProcessing.value);

  function revokePreview() {
    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value);
      previewUrl.value = null;
    }
    previewKind.value = null;
  }

  function setPreview(file: File) {
    revokePreview();
    previewKind.value = previewKindForFile(file);
    if (previewKind.value === 'pdf' || previewKind.value === 'image') {
      const preview = previewKind.value === 'pdf' && file.type !== 'application/pdf'
        ? new Blob([file], { type: 'application/pdf' }) : file;
      previewUrl.value = URL.createObjectURL(preview);
    }
  }

  function updatePendingEntry(id: string, patch: Partial<Pick<PendingExpenseFile, 'hasIsdoc' | 'extractionSource' | 'detectError' | 'detectionStatus'>>) {
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry) return;
    if (patch.hasIsdoc !== undefined) entry.hasIsdoc = patch.hasIsdoc;
    if (patch.extractionSource !== undefined) entry.extractionSource = patch.extractionSource;
    if (patch.detectError !== undefined) entry.detectError = patch.detectError;
    if (patch.detectionStatus !== undefined) entry.detectionStatus = patch.detectionStatus;
  }

  /** Show one decision at a time, keeping the exact file identity throughout retries. */
  function advanceDialogs() {
    if (disposed || detecting.value || extracting.value || showExtractModal.value || showProcessingConsent.value) return;
    extractionQueue.value = extractionQueue.value.filter(id => pendingFiles.value.some(entry => entry.id === id && entry.extractionSource !== null));
    consentQueue.value = consentQueue.value.filter(id => pendingFiles.value.some(entry => entry.id === id && entry.detectionStatus === 'pending'));
    const extracted = extractionQueue.value[0];
    if (extracted) {
      isdocModalFileId.value = extracted;
      showExtractModal.value = true;
    } else processingConsentFileId.value = consentQueue.value[0] ?? null;
  }

  async function loadQuota() {
    const captured = context();
    const loaded = localFirst
      ? await invoicingApi.expenses.ephemeral.isdocQuota<IsdocExtractQuota>()
      : await invoicingApi.expenses.isdocQuota<IsdocExtractQuota>(captured.company);
    if (isCurrent(captured)) quota.value = loaded;
  }

  async function addDocument(file: File) {
    selectionError.value = '';
    if (localFirst && (!evolu || !companyId.value)) {
      selectionError.value = t('errors.generic');
      return;
    }
    if (localFirst && file.size > EXPENSE_ORIGINAL_MAX_BYTES) {
      selectionError.value = t('invoicing.expense_attachment_too_large');
      return;
    }
    if (localFirst && (!file.name || file.name.length > 255 || file.type.length > 128)) {
      selectionError.value = t('invoicing.expense_attachment_detect_failed');
      return;
    }
    const captured = context();
    const id = newPendingId();
    pendingFiles.value.push({
      id,
      file,
      hasIsdoc: false,
      extractionSource: null,
      detectError: '',
      detectionStatus: 'pending',
    });
    lastAddedFileId.value = id;
    selectedPendingId.value = id;
    setPreview(file);

    if (!isCurrent(captured, id)) return;
    if (localFirst) {
      consentQueue.value.push(id);
      advanceDialogs();
    } else await detectPendingFile(id);
  }

  /** Called for local files only after the explicit in-app consent decision. */
  async function detectPendingFile(id: string) {
    const entry = pendingFiles.value.find(item => item.id === id);
    if (!entry) return;
    const captured = context();
    updatePendingEntry(id, { hasIsdoc: false, extractionSource: null, detectError: '', detectionStatus: 'checking' });
    activeDetections++;
    detecting.value = true;

    const fd = new FormData();
    fd.append('file', entry.file);

    try {
      type Detection = { quota?: IsdocExtractQuota; has_isdoc?: boolean; extraction_source?: unknown };
      const detected = localFirst
        ? await invoicingApi.expenses.ephemeral.detectIsdoc<Detection>(fd)
        : await invoicingApi.expenses.detectIsdoc<Detection>(captured.company, fd);
      if (!isCurrent(captured, id)) return;
      if (typeof detected.has_isdoc !== 'boolean') throw new Error('detect_failed');
      const hasIsdoc = detected.has_isdoc;
      // Old servers expose only has_isdoc. New source metadata must agree with that literal claim.
      const source = detected.extraction_source === undefined ? (hasIsdoc ? 'isdoc' : null) : detected.extraction_source;
      if (!((source === 'isdoc' && hasIsdoc) || (source === null && !hasIsdoc)
        || (source === 'pdf_text' && !hasIsdoc && previewKindForFile(entry.file) === 'pdf'))) throw new Error('detect_failed');
      quota.value = (detected.quota as typeof quota.value) ?? quota.value;
      updatePendingEntry(id, { hasIsdoc, extractionSource: source,
        detectionStatus: source === 'isdoc' ? 'found' : source === 'pdf_text' ? 'text_found' : 'absent' });
      if (source !== null && !extractionQueue.value.includes(id)) extractionQueue.value.push(id);
    } catch (e: unknown) {
      if (!isCurrent(captured, id)) return;
      const err = e as { response?: { data?: { message?: string; errors?: { file?: string[] } } } };
      const fileErr = err?.response?.data?.errors?.file?.[0];
      const rawMessage = err?.response?.data?.message || '';
      const looksLikeXmlParseNoise =
        rawMessage.includes('Start tag expected') || rawMessage.includes('not well-formed');
      updatePendingEntry(id, {
        detectError: fileErr || (looksLikeXmlParseNoise ? 'detect_failed' : rawMessage) || 'detect_failed',
        detectionStatus: 'error',
      });
    } finally {
      if (isCurrent(captured)) {
        activeDetections--;
        detecting.value = activeDetections > 0;
        advanceDialogs();
      }
    }
  }

  async function confirmProcessingConsent() {
    if (detecting.value || extracting.value) return;
    const id = processingConsentFileId.value;
    if (!id) return;
    processingConsentFileId.value = null;
    consentQueue.value = consentQueue.value.filter(value => value !== id);
    await detectPendingFile(id);
  }

  function skipProcessingConsent() {
    if (detecting.value || extracting.value) return;
    const id = processingConsentFileId.value;
    if (!id) return;
    updatePendingEntry(id, { detectionStatus: 'skipped', hasIsdoc: false, extractionSource: null, detectError: '' });
    processingConsentFileId.value = null;
    consentQueue.value = consentQueue.value.filter(value => value !== id);
    advanceDialogs();
  }

  async function retryDetection() {
    if (detecting.value || extracting.value || showExtractModal.value || showProcessingConsent.value) return;
    const entry = selectedPending.value;
    if (!entry) return;
    if (entry.extractionSource !== null) {
      if (!extractionQueue.value.includes(entry.id)) extractionQueue.value.push(entry.id);
      advanceDialogs();
    } else if (localFirst) {
      updatePendingEntry(entry.id, { detectionStatus: 'pending', detectError: '' });
      if (!consentQueue.value.includes(entry.id)) consentQueue.value.push(entry.id);
      advanceDialogs();
    } else await detectPendingFile(entry.id);
  }

  async function onDocumentSelected(file: File) {
    await addDocument(file);
  }

  async function onDocumentsSelected(files: File[]) {
    const captured = context();
    for (const file of files) {
      if (!isCurrent(captured)) break;
      await addDocument(file);
    }
  }

  function selectPendingFile(id: string) {
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry) return;
    selectedPendingId.value = id;
    setPreview(entry.file);
  }

  function removePendingFile(id: string) {
    const index = pendingFiles.value.findIndex((item) => item.id === id);
    if (index < 0) return;

    pendingFiles.value.splice(index, 1);
    consentQueue.value = consentQueue.value.filter(value => value !== id);
    extractionQueue.value = extractionQueue.value.filter(value => value !== id);
    if (processingConsentFileId.value === id) processingConsentFileId.value = null;
    if (isdocModalFileId.value === id) {
      isdocModalFileId.value = null;
      showExtractModal.value = false;
    }
    if (lastAddedFileId.value === id) {
      lastAddedFileId.value = pendingFiles.value.at(-1)?.id ?? null;
    }

    if (selectedPendingId.value === id) {
      const next = pendingFiles.value[index] ?? pendingFiles.value[index - 1] ?? null;
      selectedPendingId.value = next?.id ?? null;
      if (next) {
        setPreview(next.file);
      } else {
        revokePreview();
      }
    }
    advanceDialogs();
  }

  async function confirmExtract(): Promise<ExpenseImportDraft | null> {
    if (extracting.value || (localFirst && !evolu)) return null;
    const id = isdocModalFileId.value ?? selectedPendingId.value;
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry?.extractionSource) return null;

    const captured = context();

    extracting.value = true;
    const fd = new FormData();
    fd.append('file', entry.file);
    try {
      const res = localFirst
        ? await invoicingApi.expenses.ephemeral.extract<ExpenseImportDraft>(fd)
        : await invoicingApi.expenses.extract<ExpenseImportDraft>(captured.company, fd);
      if (!isCurrent(captured, entry.id)) return null;
      if (res.data.source !== undefined && res.data.source !== entry.extractionSource) throw new Error(t('invoicing.expense_attachment_detect_failed'));
      const draft = entry.extractionSource === 'pdf_text'
        ? { ...res.data, source: 'pdf_text' as const, payment_review_required: true } : res.data;
      quota.value = (res.quota as typeof quota.value) ?? quota.value;
      showExtractModal.value = false;
      isdocModalFileId.value = null;
      extractionQueue.value = extractionQueue.value.filter(value => value !== entry.id);

      return draft;
    } catch (error: unknown) {
      if (!isCurrent(captured, entry.id)) return null;
      throw error;
    } finally {
      if (isCurrent(captured)) {
        extracting.value = false;
        advanceDialogs();
      }
    }
  }

  function skipExtract() {
    if (extracting.value) return;
    const id = isdocModalFileId.value;
    extractionQueue.value = extractionQueue.value.filter(value => value !== id);
    showExtractModal.value = false;
    isdocModalFileId.value = null;
    advanceDialogs();
  }

  async function purchasePack(credits: number) {
    if (localFirst || purchasing.value || !purchases.value.isdoc_packs) return;
    const captured = context();
    purchasing.value = true;
    try {
      const purchase = await invoicingApi.expenses.purchaseIsdocPack<{ checkoutLink?: string }>(captured.company, credits);
      if (!isCurrent(captured)) return;
      const url = purchase?.checkoutLink;
      if (url) {
        window.open(url, '_blank', 'noopener');
      }
    } finally {
      if (isCurrent(captured)) purchasing.value = false;
    }
  }

  function clearPendingAttachment(id?: string) {
    if (id) {
      removePendingFile(id);

      return;
    }

    pendingFiles.value = [];
    selectionError.value = '';
    selectedPendingId.value = null;
    isdocModalFileId.value = null;
    processingConsentFileId.value = null;
    consentQueue.value = [];
    extractionQueue.value = [];
    lastAddedFileId.value = null;
    showExtractModal.value = false;
    revokePreview();
  }

  async function uploadPendingFiles(expenseId: string, fileIds: string[]) {
    if (hasUnresolvedProcessing.value) throw new Error(t('invoicing.expense_attachment_processing_pending'));
    const captured = context();
    if (localFirst) {
      if (!evolu || !captured.company) throw new Error(t('errors.generic'));
      for (const id of fileIds) {
        const entry = pendingFiles.value.find((item) => item.id === id);
        if (!entry) continue;
        if (entry.file.size > LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES) {
          await saveManualExpenseAttachmentArchive(evolu, {
            companyId: captured.company, expenseId: expenseId as ExpenseId,
            attachmentId: createIdFromString(`satflux.manual-expense-attachment.v1.${id}`) as never,
            file: entry.file, assertCurrent: () => {
              if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
            },
          });
          if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
          removePendingFile(id);
          continue;
        }
        const contentBase64 = await readFileAsAttachmentBase64(entry.file);
        if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
        const targets = await loadEvoluQueryWithTimeout(evolu, expenseByIdIncludingDeletedQuery(expenseId as ExpenseId));
        if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
        const target = targets.find((row) => row.id === expenseId);
        if (!target || target.companyId !== captured.company || target.isDeleted === sqliteTrue) {
          throw new Error('expense_unavailable');
        }
        const result = insertLocalExpenseAttachmentFromBase64(
          evolu,
          expenseId as ExpenseId,
          { filename: entry.file.name, mimeType: entry.file.type || null, contentBase64 },
        );
        if (!result.ok) {
          const uploadError = new Error(t('invoicing.expense_attachment_detect_failed'));
          (uploadError as Error & { cause?: unknown }).cause = (result as { error?: unknown }).error;
          throw uploadError;
        }
        removePendingFile(id);
      }
      return;
    }

    for (const id of fileIds) {
      if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
      const entry = pendingFiles.value.find((item) => item.id === id);
      if (!entry) continue;

      const fd = new FormData();
      fd.append('file', entry.file);
      await invoicingApi.expenses.uploadAttachment(captured.company, expenseId, fd);
      if (isCurrent(captured)) removePendingFile(id);
    }
  }

  async function uploadPendingAttachment(expenseId: string) {
    const id = lastAddedFileId.value;
    if (!id) return;

    await uploadPendingFiles(expenseId, [id]);
    removePendingFile(id);
  }

  async function uploadAllPendingAttachments(expenseId: string) {
    const captured = context();
    const ids = pendingFiles.value.map((entry) => entry.id);
    if (ids.length === 0) return;

    await uploadPendingFiles(expenseId, ids);
    if (isCurrent(captured) && pendingFiles.value.length === 0) clearPendingAttachment();
  }

  watch([companyId, () => expenseId?.value, () => localFirst ? companyShareRoutingKey(companyId.value) : 'server'], () => {
    generation++;
    activeDetections = 0;
    detecting.value = false;
    extracting.value = false;
    purchasing.value = false;
    quota.value = null;
    clearPendingAttachment();
  }, { flush: 'sync' });

  onUnmounted(() => {
    disposed = true;
    generation++;
    revokePreview();
  });

  return {
    quota,
    pendingFiles,
    pendingFileSummaries,
    selectedPendingId,
    pendingAttachment,
    pendingAttachmentName,
    previewUrl,
    previewKind,
    lastDetectHasIsdoc,
    extractionSource,
    detectError,
    detectionStatus,
    showProcessingConsent,
    processingConsentFileName,
    hasUnresolvedProcessing,
    readyToUpload,
    showExtractModal,
    detecting,
    extracting,
    purchasing,
    loadQuota,
    purchasePack,
    onDocumentSelected,
    onDocumentsSelected,
    confirmProcessingConsent,
    skipProcessingConsent,
    retryDetection,
    selectPendingFile,
    removePendingFile,
    confirmExtract,
    skipExtract,
    clearPendingAttachment,
    uploadPendingAttachment,
    uploadAllPendingAttachments,
  };
}

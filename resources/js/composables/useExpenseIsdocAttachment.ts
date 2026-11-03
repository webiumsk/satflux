import { useEffectiveAccess } from "@/composables/useEffectiveAccess";
import { computed, onUnmounted, ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { invoicingApi } from '../services/api';
import type { IsdocExtractQuota } from '../components/invoicing/ExpenseIsdocExtractModal.vue';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { useInvoicingEvolu } from '../evolu/client';
import {
  insertLocalExpenseAttachmentFromBase64,
  LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES,
  readFileAsAttachmentBase64,
} from '../evolu/expenseAttachmentCrud';
import type { ExpenseId } from '../evolu/schema';

export type ExpenseImportDraft = {
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

export type PendingExpenseFile = {
  id: string;
  file: File;
  hasIsdoc: boolean;
  detectError: string;
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
    return { generation, company: companyId.value, expense: expenseId?.value };
  }

  function isCurrent(captured: ReturnType<typeof context>, fileId?: string) {
    return !disposed && captured.generation === generation
      && captured.company === companyId.value && captured.expense === expenseId?.value
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
    })),
  );

  const lastDetectHasIsdoc = computed(() => selectedPending.value?.hasIsdoc ?? false);

  const detectError = computed(() => selectedPending.value?.detectError || selectionError.value);

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
      previewUrl.value = URL.createObjectURL(file);
    }
  }

  function updatePendingEntry(id: string, patch: Partial<Pick<PendingExpenseFile, 'hasIsdoc' | 'detectError'>>) {
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry) return;
    if (patch.hasIsdoc !== undefined) entry.hasIsdoc = patch.hasIsdoc;
    if (patch.detectError !== undefined) entry.detectError = patch.detectError;
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
    if (localFirst && file.size > LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES) {
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
      detectError: '',
    });
    lastAddedFileId.value = id;
    selectedPendingId.value = id;
    setPreview(file);

    // A file may always stay local without server-assisted ISDOC processing.
    if (localFirst && !window.confirm(t('invoicing.expense_attachment_server_processing_confirm'))) return;
    if (!isCurrent(captured, id)) return;
    activeDetections++;
    detecting.value = true;

    const fd = new FormData();
    fd.append('file', file);

    try {
      type Detection = { quota?: IsdocExtractQuota; has_isdoc?: boolean };
      const detected = localFirst
        ? await invoicingApi.expenses.ephemeral.detectIsdoc<Detection>(fd)
        : await invoicingApi.expenses.detectIsdoc<Detection>(captured.company, fd);
      if (!isCurrent(captured, id)) return;
      quota.value = (detected.quota as typeof quota.value) ?? quota.value;
      const hasIsdoc = Boolean(detected.has_isdoc);
      updatePendingEntry(id, { hasIsdoc });
      if (hasIsdoc && !showExtractModal.value) {
        isdocModalFileId.value = id;
        showExtractModal.value = true;
      }
    } catch (e: unknown) {
      if (!isCurrent(captured, id)) return;
      const err = e as { response?: { data?: { message?: string; errors?: { file?: string[] } } } };
      const fileErr = err?.response?.data?.errors?.file?.[0];
      const rawMessage = err?.response?.data?.message || '';
      const looksLikeXmlParseNoise =
        rawMessage.includes('Start tag expected') || rawMessage.includes('not well-formed');
      updatePendingEntry(id, {
        detectError: fileErr || (looksLikeXmlParseNoise ? 'detect_failed' : rawMessage) || 'detect_failed',
      });
    } finally {
      if (isCurrent(captured)) {
        activeDetections--;
        detecting.value = activeDetections > 0;
      }
    }
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
  }

  async function confirmExtract(): Promise<ExpenseImportDraft | null> {
    if (extracting.value || (localFirst && !evolu)) return null;
    const id = isdocModalFileId.value ?? selectedPendingId.value;
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry?.hasIsdoc) return null;

    const captured = context();

    extracting.value = true;
    const fd = new FormData();
    fd.append('file', entry.file);
    try {
      const res = localFirst
        ? await invoicingApi.expenses.ephemeral.extract<ExpenseImportDraft>(fd)
        : await invoicingApi.expenses.extract<ExpenseImportDraft>(captured.company, fd);
      if (!isCurrent(captured, entry.id)) return null;
      quota.value = (res.quota as typeof quota.value) ?? quota.value;
      showExtractModal.value = false;
      isdocModalFileId.value = null;

      return res.data;
    } catch (error: unknown) {
      if (!isCurrent(captured, entry.id)) return null;
      throw error;
    } finally {
      if (isCurrent(captured)) extracting.value = false;
    }
  }

  function skipExtract() {
    showExtractModal.value = false;
    isdocModalFileId.value = null;
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
    lastAddedFileId.value = null;
    showExtractModal.value = false;
    revokePreview();
  }

  async function uploadPendingFiles(expenseId: string, fileIds: string[]) {
    const captured = context();
    if (localFirst) {
      if (!evolu || !captured.company) throw new Error(t('errors.generic'));
      for (const id of fileIds) {
        const entry = pendingFiles.value.find((item) => item.id === id);
        if (!entry) continue;
        const contentBase64 = await readFileAsAttachmentBase64(entry.file);
        if (!isCurrent(captured, id)) throw new Error(t('errors.generic'));
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

  watch([companyId, () => expenseId?.value], () => {
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
    detectError,
    showExtractModal,
    detecting,
    extracting,
    purchasing,
    loadQuota,
    purchasePack,
    onDocumentSelected,
    onDocumentsSelected,
    selectPendingFile,
    removePendingFile,
    confirmExtract,
    skipExtract,
    clearPendingAttachment,
    uploadPendingAttachment,
    uploadAllPendingAttachments,
  };
}

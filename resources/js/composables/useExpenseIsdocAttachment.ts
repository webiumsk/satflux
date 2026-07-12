import { computed, onUnmounted, ref, type Ref } from 'vue';
import type { IsdocExtractQuota } from '../components/invoicing/ExpenseIsdocExtractModal.vue';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { useInvoicingEvolu } from '../evolu/client';
import { insertLocalExpenseAttachment } from '../evolu/expenseAttachmentCrud';
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

export function useExpenseIsdocAttachment(companyId: Ref<string>) {
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

  const detectError = computed(() => selectedPending.value?.detectError ?? '');

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
    quota.value = await invoicingApi.expenses.isdocQuota(companyId.value);
  }

  async function addDocument(file: File) {
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
    detecting.value = true;

    const fd = new FormData();
    fd.append('file', file);

    try {
      const detected = await invoicingApi.expenses.detectIsdoc<{ quota?: unknown; has_isdoc?: boolean }>(companyId.value, fd);
      quota.value = (detected.quota as typeof quota.value) ?? quota.value;
      const hasIsdoc = Boolean(detected.has_isdoc);
      updatePendingEntry(id, { hasIsdoc });
      if (hasIsdoc && !showExtractModal.value) {
        isdocModalFileId.value = id;
        showExtractModal.value = true;
      }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string; errors?: { file?: string[] } } } };
      const fileErr = err?.response?.data?.errors?.file?.[0];
      const rawMessage = err?.response?.data?.message || '';
      const looksLikeXmlParseNoise =
        rawMessage.includes('Start tag expected') || rawMessage.includes('not well-formed');
      updatePendingEntry(id, {
        detectError: fileErr || (looksLikeXmlParseNoise ? 'detect_failed' : rawMessage) || 'detect_failed',
      });
    } finally {
      detecting.value = false;
    }
  }

  async function onDocumentSelected(file: File) {
    await addDocument(file);
  }

  async function onDocumentsSelected(files: File[]) {
    for (const file of files) {
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
    const id = isdocModalFileId.value ?? selectedPendingId.value;
    const entry = pendingFiles.value.find((item) => item.id === id);
    if (!entry) return null;

    extracting.value = true;
    const fd = new FormData();
    fd.append('file', entry.file);
    try {
      const res = await invoicingApi.expenses.extract<ExpenseImportDraft>(companyId.value, fd);
      quota.value = (res.quota as typeof quota.value) ?? quota.value;
      showExtractModal.value = false;
      isdocModalFileId.value = null;

      return res.data;
    } finally {
      extracting.value = false;
    }
  }

  function skipExtract() {
    showExtractModal.value = false;
    isdocModalFileId.value = null;
  }

  async function purchasePack(credits: number) {
    purchasing.value = true;
    try {
      const purchase = await invoicingApi.expenses.purchaseIsdocPack<{ checkoutLink?: string }>(companyId.value, credits);
      const url = purchase?.checkoutLink;
      if (url) {
        window.open(url, '_blank', 'noopener');
      }
    } finally {
      purchasing.value = false;
    }
  }

  function clearPendingAttachment(id?: string) {
    if (id) {
      removePendingFile(id);

      return;
    }

    pendingFiles.value = [];
    selectedPendingId.value = null;
    isdocModalFileId.value = null;
    lastAddedFileId.value = null;
    showExtractModal.value = false;
    revokePreview();
  }

  async function uploadPendingFiles(expenseId: string, fileIds: string[]) {
    if (localFirst && evolu) {
      for (const id of fileIds) {
        const entry = pendingFiles.value.find((item) => item.id === id);
        if (!entry) continue;
        const result = await insertLocalExpenseAttachment(
          evolu,
          expenseId as ExpenseId,
          entry.file,
        );
        if (!result.ok) {
          // Preserve the Evolu rejection reason (e.g. a maxLength failure)
          // instead of collapsing it - callers surface error.message and can
          // inspect the cause. The reason is a validation shape, never file
          // content.
          console.error('Expense attachment insert rejected for', entry.file.name);
          // TS lib target predates ErrorOptions - attach the cause manually.
          const uploadError = new Error(`upload_failed: ${entry.file.name}`);
          (uploadError as Error & { cause?: unknown }).cause = (result as { error?: unknown }).error;
          throw uploadError;
        }
      }
      return;
    }

    for (const id of fileIds) {
      const entry = pendingFiles.value.find((item) => item.id === id);
      if (!entry) continue;

      const fd = new FormData();
      fd.append('file', entry.file);
      await invoicingApi.expenses.uploadAttachment(companyId.value, expenseId, fd);
    }
  }

  async function uploadPendingAttachment(expenseId: string) {
    const id = lastAddedFileId.value;
    if (!id) return;

    await uploadPendingFiles(expenseId, [id]);
    removePendingFile(id);
  }

  async function uploadAllPendingAttachments(expenseId: string) {
    const ids = pendingFiles.value.map((entry) => entry.id);
    if (ids.length === 0) return;

    await uploadPendingFiles(expenseId, ids);
    clearPendingAttachment();
  }

  onUnmounted(() => {
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

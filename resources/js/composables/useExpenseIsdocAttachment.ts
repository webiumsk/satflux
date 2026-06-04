import { onUnmounted, ref, type Ref } from 'vue';
import api from '../services/api';
import type { IsdocExtractQuota } from '../components/invoicing/ExpenseIsdocExtractModal.vue';

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

function previewKindForFile(file: File): 'pdf' | 'image' | 'other' {
  if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
    return 'pdf';
  }
  if (file.type.startsWith('image/')) {
    return 'image';
  }
  return 'other';
}

export function useExpenseIsdocAttachment(companyId: Ref<string>) {
  const quota = ref<IsdocExtractQuota | null>(null);
  const pendingAttachment = ref<File | null>(null);
  const pendingAttachmentName = ref('');
  const previewUrl = ref<string | null>(null);
  const previewKind = ref<'pdf' | 'image' | 'other' | null>(null);
  const lastDetectHasIsdoc = ref(false);
  const detectError = ref('');
  const showExtractModal = ref(false);
  const detecting = ref(false);
  const extracting = ref(false);
  const purchasing = ref(false);

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

  async function loadQuota() {
    const res = await api.get(`/invoicing/companies/${companyId.value}/expenses/isdoc-extract-quota`);
    quota.value = res.data.data;
  }

  async function onDocumentSelected(file: File) {
    pendingAttachment.value = file;
    pendingAttachmentName.value = file.name;
    setPreview(file);
    detectError.value = '';
    lastDetectHasIsdoc.value = false;
    detecting.value = true;

    const fd = new FormData();
    fd.append('file', file);

    try {
      const res = await api.post(`/invoicing/companies/${companyId.value}/expenses/detect-isdoc`, fd);
      quota.value = res.data.data.quota ?? quota.value;
      lastDetectHasIsdoc.value = Boolean(res.data.data.has_isdoc);
      if (lastDetectHasIsdoc.value) {
        showExtractModal.value = true;
      }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string; errors?: { file?: string[] } } } };
      const fileErr = err?.response?.data?.errors?.file?.[0];
      const rawMessage = err?.response?.data?.message || '';
      const looksLikeXmlParseNoise =
        rawMessage.includes("Start tag expected") || rawMessage.includes("not well-formed");
      detectError.value =
        fileErr || (looksLikeXmlParseNoise ? 'detect_failed' : rawMessage) || 'detect_failed';
    } finally {
      detecting.value = false;
    }
  }

  async function confirmExtract(): Promise<ExpenseImportDraft | null> {
    if (!pendingAttachment.value) return null;
    extracting.value = true;
    const fd = new FormData();
    fd.append('file', pendingAttachment.value);
    try {
      const res = await api.post(`/invoicing/companies/${companyId.value}/expenses/extract`, fd);
      quota.value = res.data.quota ?? quota.value;
      showExtractModal.value = false;
      return res.data.data as ExpenseImportDraft;
    } finally {
      extracting.value = false;
    }
  }

  function skipExtract() {
    showExtractModal.value = false;
  }

  async function purchasePack(credits: number) {
    purchasing.value = true;
    try {
      const res = await api.post(
        `/invoicing/companies/${companyId.value}/expenses/isdoc-packs/purchase`,
        { credits },
      );
      const url = res.data.data?.checkoutLink;
      if (url) {
        window.open(url, '_blank', 'noopener');
      }
    } finally {
      purchasing.value = false;
    }
  }

  function clearPendingAttachment() {
    pendingAttachment.value = null;
    pendingAttachmentName.value = '';
    detectError.value = '';
    lastDetectHasIsdoc.value = false;
    revokePreview();
  }

  async function uploadPendingAttachment(expenseId: string) {
    if (!pendingAttachment.value) return;
    const fd = new FormData();
    fd.append('file', pendingAttachment.value);
    await api.post(`/invoicing/companies/${companyId.value}/expenses/${expenseId}/attachment`, fd);
    clearPendingAttachment();
  }

  onUnmounted(() => {
    revokePreview();
  });

  return {
    quota,
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
    confirmExtract,
    skipExtract,
    clearPendingAttachment,
    uploadPendingAttachment,
  };
}

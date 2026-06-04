import { ref, type Ref } from 'vue';
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

export function useExpenseIsdocAttachment(companyId: Ref<string>) {
  const quota = ref<IsdocExtractQuota | null>(null);
  const pendingAttachment = ref<File | null>(null);
  const pendingAttachmentName = ref('');
  const showExtractModal = ref(false);
  const detecting = ref(false);
  const extracting = ref(false);
  const purchasing = ref(false);

  async function loadQuota() {
    const res = await api.get(`/invoicing/companies/${companyId.value}/expenses/isdoc-extract-quota`);
    quota.value = res.data.data;
  }

  async function onDocumentSelected(file: File) {
    pendingAttachment.value = file;
    pendingAttachmentName.value = file.name;
    detecting.value = true;
    const fd = new FormData();
    fd.append('file', file);
    try {
      const res = await api.post(`/invoicing/companies/${companyId.value}/expenses/detect-isdoc`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      quota.value = res.data.data.quota ?? quota.value;
      if (res.data.data.has_isdoc) {
        showExtractModal.value = true;
      }
    } catch {
      pendingAttachment.value = null;
      pendingAttachmentName.value = '';
      throw new Error('detect_failed');
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
      const res = await api.post(`/invoicing/companies/${companyId.value}/expenses/extract`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
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
  }

  async function uploadPendingAttachment(expenseId: string) {
    if (!pendingAttachment.value) return;
    const fd = new FormData();
    fd.append('file', pendingAttachment.value);
    await api.post(`/invoicing/companies/${companyId.value}/expenses/${expenseId}/attachment`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    clearPendingAttachment();
  }

  return {
    quota,
    pendingAttachment,
    pendingAttachmentName,
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

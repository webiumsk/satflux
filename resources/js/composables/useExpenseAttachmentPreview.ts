import { computed, ref, watch, type Ref } from 'vue';
import type { SavedExpenseAttachment } from '../components/invoicing/ExpenseAttachmentPanel.vue';
import type { EvoluExpenseAttachmentRow } from '../evolu/expenseAttachmentCrud';
import { readVerifiedExpenseAttachment } from '../evolu/expenseOriginalArchive';
import { getWebBlob } from '../services/api';
import { useAuthStore } from '../store/auth';
import type { useInvoicingExpense } from './useInvoicingExpenses';

type PreviewExpense = {
  id: string;
  attachments?: unknown[];
  attachment_path?: string | null;
  original_filename?: string | null;
};

function isSavedAttachment(value: unknown): value is SavedExpenseAttachment {
  return !!value && typeof value === 'object' && 'id' in value && typeof value.id === 'string'
    && 'original_filename' in value && (typeof value.original_filename === 'string' || value.original_filename === null);
}

const xmlPreviewLimit = 2 * 1024 * 1024;

export function useExpenseAttachmentPreview(options: {
  companyId: Readonly<Ref<string>>;
  expenseId: Readonly<Ref<string | undefined>>;
  expense: Readonly<Ref<PreviewExpense | null>>;
  pendingAttachmentName: Readonly<Ref<string>>;
  resource: ReturnType<typeof useInvoicingExpense>;
  localFirst: boolean;
}) {
  const { companyId, expenseId, expense, pendingAttachmentName, resource, localFirst } = options;
  const auth = useAuthStore();
  // The local resource belongs to the account that opened this page.
  const sameSession = ref(Boolean(auth.user?.id));
  const selectedAttachmentId = ref<string | null>(null);
  const savedPreviewUrl = ref<string | null>(null);
  const savedPreviewKind = ref<'pdf' | 'image' | 'other' | null>(null);
  const embeddedUblXml = ref<string | null>(null);
  const embeddedUblTooLarge = ref(false);
  const previewLoading = ref(false);
  const previewFailed = ref(false);
  let request = 0;

  const scopedExpense = computed(() => {
    if (!sameSession.value || !expense.value || expense.value.id !== expenseId.value) return null;
    if (!localFirst) return expense.value;
    if (!resource.expenseRows.value.some(row => row.id === expenseId.value && row.companyId === companyId.value)) return null;
    // Keep the form draft independent while its attachment list follows the current owner-scoped projection.
    return resource.expense.value?.id === expenseId.value ? resource.expense.value : null;
  });
  const savedAttachments = computed(() => scopedExpense.value?.attachments?.filter(isSavedAttachment) ?? []);
  const selectedAttachment = computed(() => savedAttachments.value.find(att => att.id === selectedAttachmentId.value)
    ?? savedAttachments.value[0] ?? null);
  const panelFileName = computed(() => pendingAttachmentName.value || selectedAttachment.value?.original_filename
    || scopedExpense.value?.original_filename || '');
  const panelHasFile = computed(() => Boolean(pendingAttachmentName.value || selectedAttachment.value || scopedExpense.value?.attachment_path));

  function revokeSavedPreview() {
    request++;
    if (savedPreviewUrl.value) URL.revokeObjectURL(savedPreviewUrl.value);
    savedPreviewUrl.value = null;
    savedPreviewKind.value = null;
    embeddedUblXml.value = null;
    embeddedUblTooLarge.value = false;
    previewLoading.value = false;
    previewFailed.value = false;
  }

  function attachmentPreviewUrl(id?: string | null) {
    const base = `/api/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`;
    return id ? `${base}/attachments/${id}` : `${base}/attachment`;
  }

  function localAttachmentRow(id?: string | null): EvoluExpenseAttachmentRow | null {
    if (!id || !scopedExpense.value) return null;
    const row = resource.attachmentRows?.value.find(row => row.id === id && row.expenseId === expenseId.value);
    return row?.expenseId ? { ...row, expenseId: row.expenseId } : null;
  }

  async function loadSavedPreview() {
    revokeSavedPreview();
    if (pendingAttachmentName.value || !scopedExpense.value || !panelHasFile.value) return;
    const token = request;
    const att = selectedAttachment.value;
    const name = panelFileName.value.toLowerCase();
    const current = () => token === request && !!scopedExpense.value && att?.id === selectedAttachment.value?.id && !pendingAttachmentName.value;
    previewLoading.value = true;
    try {
      let blob: Blob | null;
      if (localFirst) {
        const row = localAttachmentRow(att?.id);
        if (!row || !resource.evolu) throw new Error('original_unavailable');
        blob = (await readVerifiedExpenseAttachment(resource.evolu, companyId.value, row)).blob;
      } else {
        blob = await getWebBlob(attachmentPreviewUrl(att?.id));
      }
      if (!current()) return;
      if (!blob) throw new Error('original_unavailable');
      const mime = blob.type.split(';')[0].trim().toLowerCase();
      if (name.endsWith('.pdf') || mime === 'application/pdf') {
        savedPreviewKind.value = 'pdf';
        savedPreviewUrl.value = URL.createObjectURL(mime === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' }));
      } else if (mime.startsWith('image/')) {
        savedPreviewKind.value = 'image';
        savedPreviewUrl.value = URL.createObjectURL(blob);
      } else {
        savedPreviewKind.value = 'other';
        if (name.endsWith('.xml') || mime === 'application/xml' || mime === 'text/xml') {
          if (blob.size > xmlPreviewLimit) embeddedUblTooLarge.value = true;
          else {
            const xml = await blob.text();
            if (current()) embeddedUblXml.value = xml;
          }
        }
      }
    } catch {
      if (current()) {
        savedPreviewKind.value = 'other';
        previewFailed.value = true;
      }
    } finally {
      if (current()) previewLoading.value = false;
    }
  }

  function onSelectAttachment(id: string) {
    if (!savedAttachments.value.some(att => att.id === id)) return;
    selectedAttachmentId.value = id;
    if (!pendingAttachmentName.value) void loadSavedPreview();
  }

  watch(() => auth.user?.id, () => {
    sameSession.value = false;
    selectedAttachmentId.value = null;
    revokeSavedPreview();
  }, { flush: 'sync' });
  watch(() => selectedAttachment.value?.id, (id, previousId) => {
    if (!previousId) return;
    if (id && id !== selectedAttachmentId.value) {
      // The selected row disappeared. Adopt the fallback just as the panel does.
      selectedAttachmentId.value = id;
      void loadSavedPreview();
    } else {
      if (!id) selectedAttachmentId.value = null;
      revokeSavedPreview();
    }
  }, { flush: 'sync' });
  watch(() => Boolean(scopedExpense.value), available => { if (!available) revokeSavedPreview(); }, { flush: 'sync' });
  return { selectedAttachmentId, selectedAttachment, savedAttachments, savedPreviewUrl, savedPreviewKind,
    embeddedUblXml, embeddedUblTooLarge, previewFailed, previewLoading, panelFileName, panelHasFile,
    attachmentPreviewUrl, localAttachmentRow, revokeSavedPreview, loadSavedPreview, onSelectAttachment };
}

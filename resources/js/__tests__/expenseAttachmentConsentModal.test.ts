import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';

const mocks = vi.hoisted(() => ({ detect: vi.fn(), extract: vi.fn(), read: vi.fn(), insert: vi.fn() }));
const company = ref('abcdefghijklmnopqrstuv');
const routing = ref('private');
vi.mock('@/composables/useEffectiveAccess', () => ({ useEffectiveAccess: () => ({ purchases: ref({ isdoc_packs: false }) }) }));
vi.mock('@/evolu/flags', () => ({ isInvoicingLocalFirst: () => true }));
vi.mock('@/evolu/client', () => ({ useInvoicingEvolu: () => ({ loadQuery: async () => [{ id: 'expense-a', companyId: company.value, isDeleted: null }] }), expenseByIdIncludingDeletedQuery: () => null }));
vi.mock('@/evolu/companyShareRegistry', () => ({ companyShareRoutingKey: () => routing.value }));
vi.mock('@/evolu/expenseAttachmentCrud', () => ({ LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES: 384 * 1024, readFileAsAttachmentBase64: mocks.read, insertLocalExpenseAttachmentFromBase64: mocks.insert }));
vi.mock('@/evolu/manualExpenseAttachment', () => ({ saveManualExpenseAttachmentArchive: vi.fn() }));
vi.mock('@/services/api', () => ({ invoicingApi: { expenses: { ephemeral: { detectIsdoc: mocks.detect, extract: mocks.extract } } } }));
import { useExpenseIsdocAttachment } from '@/composables/useExpenseIsdocAttachment';
import ExpenseAttachmentPanel from '@/components/invoicing/ExpenseAttachmentPanel.vue';
import ExpenseAttachmentConsentModal from '@/components/invoicing/ExpenseAttachmentConsentModal.vue';
import ExpenseIsdocExtractModal from '@/components/invoicing/ExpenseIsdocExtractModal.vue';

let wrapper: VueWrapper | undefined;
let resource: ReturnType<typeof useExpenseIsdocAttachment>;
let extracted: unknown;
const quota = { can_extract: true, unlimited: false, free_remaining: 19 };
const pdf = (name = 'synthetic.pdf') => new File(['%PDF synthetic only'], name, { type: 'application/pdf' });
function render() {
  wrapper = mount(defineComponent({ setup() {
    resource = useExpenseIsdocAttachment(company);
    return () => h('main', [
      h(ExpenseAttachmentPanel, { fileName: resource.pendingAttachmentName.value, hasFile: resource.pendingFiles.value.length > 0,
        hasPending: resource.pendingFiles.value.length > 0, pendingFiles: resource.pendingFileSummaries.value,
        selectedPendingId: resource.selectedPendingId.value, previewUrl: resource.previewUrl.value, previewKind: resource.previewKind.value,
        detecting: resource.detecting.value, detectError: resource.detectError.value, hasIsdoc: resource.lastDetectHasIsdoc.value,
        detectionStatus: resource.detectionStatus.value, onFileSelected: resource.onDocumentSelected,
        onFilesSelected: resource.onDocumentsSelected, onCheckIsdoc: resource.retryDetection,
      }),
      h(ExpenseAttachmentConsentModal, { open: resource.showProcessingConsent.value, fileName: resource.processingConsentFileName.value,
        busy: resource.detecting.value, onConfirm: resource.confirmProcessingConsent, onSkip: resource.skipProcessingConsent }),
      h(ExpenseIsdocExtractModal, { open: resource.showExtractModal.value, extracting: resource.extracting.value,
        quota: resource.quota.value, allowPurchases: false, onConfirm: async () => { extracted = await resource.confirmExtract(); }, onSkip: resource.skipExtract }),
    ]);
  } }), { attachTo: document.body, global: { plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })] } });
}
async function select(files = [pdf()]) {
  const input = wrapper!.get('input[type="file"]');
  Object.defineProperty(input.element, 'files', { configurable: true, value: files });
  await input.trigger('change');
  await flushPromises();
}
async function consent(accept: boolean) {
  const dialog = document.querySelector('[role="dialog"]')!;
  const label = accept ? en.invoicing.expense_attachment_processing_confirm : en.invoicing.expense_attachment_processing_skip;
  const button = Array.from(dialog.querySelectorAll('button')).find(entry => entry.textContent?.trim() === label)!;
  await new DOMWrapper(button).trigger('click'); await flushPromises();
}
beforeEach(() => {
  vi.resetAllMocks(); company.value = 'abcdefghijklmnopqrstuv'; routing.value = 'private'; extracted = null;
  mocks.detect.mockResolvedValue({ has_isdoc: true, quota });
  mocks.extract.mockResolvedValue({ data: { title: 'Synthetic invoice' }, quota });
  mocks.read.mockResolvedValue('c3ludGhldGlj'); mocks.insert.mockReturnValue({ ok: true, value: { id: 'attachment-a' } });
  vi.spyOn(window, 'confirm').mockImplementation(() => { throw new Error('Native confirm must not be used'); });
  vi.stubGlobal('URL', class extends URL { static createObjectURL = vi.fn(() => 'blob:synthetic'); static revokeObjectURL = vi.fn(); });
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; document.body.innerHTML = ''; vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('visible attachment consent and pending-file processing', () => {
  it('opens consent immediately from the real file input without uploads or a false absent claim', async () => {
    render(); await select();
    expect(document.querySelector('[role="dialog"]')?.textContent).toContain(en.invoicing.expense_attachment_processing_title);
    expect(resource.detectionStatus.value).toBe('pending');
    expect(wrapper!.text()).toContain(en.invoicing.expense_attachment_status_pending);
    expect(wrapper!.text()).not.toContain(en.invoicing.expense_attachment_status_absent);
    expect(mocks.detect).not.toHaveBeenCalled(); expect(mocks.insert).not.toHaveBeenCalled(); expect(window.confirm).not.toHaveBeenCalled();
    await expect(resource.uploadAllPendingAttachments('expense-a')).rejects.toThrow(en.invoicing.expense_attachment_processing_pending);
  });
  it('detects and extracts before persistence only after two deliberate choices', async () => {
    render(); await select(); await consent(true);
    expect(resource.showExtractModal.value).toBe(true); expect(mocks.detect).toHaveBeenCalledOnce();
    expect(mocks.extract).not.toHaveBeenCalled(); expect(mocks.insert).not.toHaveBeenCalled();
    await wrapper!.findComponent(ExpenseIsdocExtractModal).findAll('button').find(button => button.text() === en.invoicing.expense_isdoc_extract_confirm)!.trigger('click');
    await flushPromises();
    expect(extracted).toEqual({ title: 'Synthetic invoice' }); expect(mocks.insert).not.toHaveBeenCalled();
    expect(resource.hasUnresolvedProcessing.value).toBe(false);
    await resource.uploadAllPendingAttachments('expense-a'); expect(mocks.insert).toHaveBeenCalledOnce();
  });
  it('keeps a skipped file pending and rechecks the same identity through the real panel action', async () => {
    mocks.detect.mockResolvedValue({ has_isdoc: false, quota });
    render(); await select(); const id = resource.pendingFiles.value[0].id; await consent(false);
    expect(resource.detectionStatus.value).toBe('skipped'); expect(mocks.detect).not.toHaveBeenCalled();
    await wrapper!.findComponent(ExpenseAttachmentPanel).findAll('button').find(button => button.text() === en.invoicing.expense_attachment_check_isdoc)!.trigger('click');
    await consent(true);
    expect(resource.pendingFiles.value.map(entry => entry.id)).toEqual([id]);
    expect(resource.detectionStatus.value).toBe('absent'); expect(resource.hasUnresolvedProcessing.value).toBe(false);
  });
  it('retries a failed detection after consent without adding another pending file', async () => {
    mocks.detect.mockRejectedValueOnce(new Error('synthetic unavailable'));
    render(); await select(); const id = resource.pendingFiles.value[0].id; await consent(true);
    expect(resource.detectionStatus.value).toBe('error');
    await wrapper!.findComponent(ExpenseAttachmentPanel).findAll('button').find(button => button.text() === en.invoicing.expense_attachment_retry_isdoc)!.trigger('click');
    expect(mocks.detect).toHaveBeenCalledOnce(); await consent(true);
    expect(resource.pendingFiles.value.map(entry => entry.id)).toEqual([id]); expect(resource.showExtractModal.value).toBe(true);
  });
  it('serializes each file consent and extraction decision without a ready-to-upload gap', async () => {
    render(); await select([pdf('first.pdf'), pdf('second.pdf')]);
    expect(resource.processingConsentFileName.value).toBe('first.pdf'); await consent(true);
    expect(resource.hasUnresolvedProcessing.value).toBe(true); resource.skipExtract(); await flushPromises();
    expect(resource.processingConsentFileName.value).toBe('second.pdf'); expect(resource.readyToUpload.value).toBe(false);
    await consent(false); expect(resource.readyToUpload.value).toBe(true); expect(mocks.detect).toHaveBeenCalledOnce();
    expect(resource.pendingFiles.value).toHaveLength(2);
  });
  it('treats Escape as keeping the file without transmitting it', async () => {
    render(); await select();
    await new DOMWrapper(document.querySelector('[role="dialog"]')!).trigger('keydown', { key: 'Escape' }); await flushPromises();
    expect(resource.detectionStatus.value).toBe('skipped'); expect(resource.pendingFiles.value).toHaveLength(1);
    expect(resource.showProcessingConsent.value).toBe(false); expect(mocks.detect).not.toHaveBeenCalled();
  });
  it('invalidates queued consent on company owner routing changes', async () => {
    render(); await select(); routing.value = 'other-owner'; await flushPromises();
    expect(resource.pendingFiles.value).toHaveLength(0); expect(document.querySelector('[role="dialog"]')).toBeNull(); expect(mocks.detect).not.toHaveBeenCalled();
  });
  it('discards detection that completes after owner routing changes', async () => {
    let finish!: (response: { has_isdoc: boolean }) => void;
    mocks.detect.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
    render(); await select(); const pending = resource.confirmProcessingConsent();
    routing.value = 'other-owner'; finish({ has_isdoc: true }); await pending; await flushPromises();
    expect(resource.pendingFiles.value).toHaveLength(0); expect(resource.showExtractModal.value).toBe(false);
    expect(resource.quota.value).toBeNull(); expect(mocks.extract).not.toHaveBeenCalled();
  });
  it('moves to the next queued consent when its active file is removed', async () => {
    render(); await select([pdf('first.pdf'), pdf('second.pdf')]);
    resource.removePendingFile(resource.pendingFiles.value[0].id); await flushPromises();
    expect(resource.processingConsentFileName.value).toBe('second.pdf');
    await consent(false); expect(resource.pendingFiles.value).toHaveLength(1); expect(resource.readyToUpload.value).toBe(true);
  });
  it('does not claim absence when a detection response omits its result', async () => {
    mocks.detect.mockResolvedValueOnce({ quota }); render(); await select(); await consent(true);
    expect(resource.detectionStatus.value).toBe('error');
    expect(wrapper!.text()).not.toContain(en.invoicing.expense_attachment_status_absent);
  });
});

import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import Panel from '@/components/invoicing/UblEmbeddedPdfPanel.vue';

const pdf = '%PDF-1.4\nsynthetic\x00\xff\n%%EOF';
const attachment = (name = 'attached.pdf', content = btoa(pdf)) => `<cac:AdditionalDocumentReference><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="application/pdf" filename="${name}">${content}</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>`;
const xml = (body = attachment()) => `<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">${body}</Invoice>`;
let wrapper: VueWrapper | undefined;
function render(source: string | null) {
  wrapper = mount(Panel, { props: { xml: source }, global: {
    plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })],
    stubs: { UblEmbeddedPdfViewer: { name: 'UblEmbeddedPdfViewer', props: ['bytes', 'label'], template: '<div data-test="pdf-preview">{{ label }}</div>' } },
  } });
  return wrapper;
}
const click = (text: string) => wrapper!.findAll('button').find(button => button.text() === text)!.trigger('click');
function blobBytes(blob: Blob): Promise<Uint8Array> {
  return new Promise(resolve => {
    const reader = new FileReader();
    reader.onload = () => resolve(new Uint8Array(reader.result as ArrayBuffer));
    reader.readAsArrayBuffer(blob);
  });
}

beforeEach(() => {
  vi.stubGlobal('URL', class extends URL {
    static createObjectURL = vi.fn(() => 'blob:synthetic-download');
    static revokeObjectURL = vi.fn();
  });
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('embedded PDF actions', () => {
  it.each([null, xml(''), '<Invoice xmlns="http://isdoc.cz/namespace/2013"/>'])('hides the panel when there are no embedded PDFs', source => {
    expect(render(source).find('section').exists()).toBe(false);
  });
  it('opens a preview only on demand and downloads exact bytes with a safe filename', async () => {
    render(xml(attachment('../attached.pdf')));
    expect(wrapper!.find('[data-test="pdf-preview"]').exists()).toBe(false);
    expect(URL.createObjectURL).not.toHaveBeenCalled();
    await click('View');
    expect(wrapper!.get('[data-test="pdf-preview"]').text()).toBe('_attached.pdf');
    await click('Download');
    const blob = vi.mocked(URL.createObjectURL).mock.calls[0][0] as Blob;
    expect(blob.type).toBe('application/pdf');
    expect(Array.from(await blobBytes(blob))).toEqual(Array.from(pdf, c => c.charCodeAt(0)));
    const anchor = vi.mocked(HTMLAnchorElement.prototype.click).mock.instances[0] as HTMLAnchorElement;
    expect(anchor.download).toBe('_attached.pdf');
    expect(anchor.href).toBe('blob:synthetic-download');
    expect(wrapper!.find('iframe').exists()).toBe(false);
  });
  it('switches between multiple PDF previews and resets on source replacement', async () => {
    render(xml(attachment('one.pdf') + attachment('two.pdf')));
    await wrapper!.get('button[aria-label="View two.pdf"]').trigger('click');
    expect(wrapper!.get('[data-test="pdf-preview"]').text()).toBe('two.pdf');
    await wrapper!.get('button[aria-label="View one.pdf"]').trigger('click');
    expect(wrapper!.get('[data-test="pdf-preview"]').text()).toBe('one.pdf');
    await wrapper!.setProps({ xml: xml(attachment('new.pdf')) });
    expect(wrapper!.find('[data-test="pdf-preview"]').exists()).toBe(false);
    expect(wrapper!.text()).not.toContain('one.pdf');
  });
  it('cleans pending download URLs on source switch and unmount', async () => {
    render(xml());
    await click('Download');
    await wrapper!.setProps({ xml: xml(attachment('new.pdf')) });
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:synthetic-download');
    await click('Download');
    wrapper!.unmount(); wrapper = undefined;
    expect(URL.revokeObjectURL).toHaveBeenCalledTimes(2);
  });
  it('shows a fixed warning but retains valid PDFs when another attachment is malformed', () => {
    render(xml(attachment('good.pdf') + attachment('bad.pdf', 'not base64')));
    expect(wrapper!.get('[role="alert"]').text()).toBe(en.invoicing.ubl_pdf_rejected);
    expect(wrapper!.text()).toContain('good.pdf');
    expect(wrapper!.text()).not.toContain('bad.pdf');
    expect(wrapper!.findAll('button')).toHaveLength(2);
  });
});

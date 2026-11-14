import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import UblEmbeddedPdfViewer from '../components/invoicing/UblEmbeddedPdfViewer.vue';

const pdfjs = vi.hoisted(() => ({ getDocument: vi.fn(), createWorker: vi.fn() }));
const asset = vi.hoisted(() => ({ url: '/assets/synthetic-pdf.worker.mjs' }));
vi.mock('pdfjs-dist/legacy/build/pdf.mjs', () => ({
  getDocument: pdfjs.getDocument,
  PDFWorker: { create: pdfjs.createWorker },
  VerbosityLevel: { ERRORS: 0 },
}));
vi.mock('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url', () => ({ get default() { return asset.url; } }));
vi.mock('vue-i18n', async (importOriginal) => ({
  ...await importOriginal<typeof import('vue-i18n')>(),
  useI18n: () => ({ t: (key: string, params?: Record<string, string | number>) => {
    if (key === 'invoicing.ubl_pdf_preview_failed') return 'PDF preview unavailable. Download the attachment to open it.';
    if (key === 'invoicing.ubl_pdf_previous') return 'Previous';
    if (key === 'invoicing.ubl_pdf_next') return 'Next';
    if (key === 'invoicing.ubl_pdf_page') return `Page ${params?.page} of ${params?.total}`;
    if (key === 'invoicing.ubl_pdf_canvas') return `${params?.name}, page ${params?.page}`;
    return key;
  } }),
}));

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: Error) => void;
  const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

class OwnedWorker extends EventTarget {
  static instances: OwnedWorker[] = [];
  terminate = vi.fn();
  constructor(readonly url: URL, readonly options: WorkerOptions) {
    super();
    OwnedWorker.instances.push(this);
  }
  ready() {
    this.dispatchEvent(new MessageEvent('message', { data: { action: 'ready', sourceName: 'worker', targetName: 'main' } }));
  }
}

function pdfDocument(numPages = 2, width = 600, height = 800, renderPromise = Promise.resolve()) {
  const render = { promise: renderPromise, cancel: vi.fn() };
  const page = {
    getViewport: vi.fn(({ scale }: { scale: number }) => ({ width: width * scale, height: height * scale })),
    render: vi.fn(() => render),
    cleanup: vi.fn(),
  };
  return { numPages, getPage: vi.fn().mockResolvedValue(page), page, render };
}

function loadingTask(promise: Promise<ReturnType<typeof pdfDocument>>) {
  return { promise, destroy: vi.fn().mockResolvedValue(undefined) };
}

const wrappers: VueWrapper[] = [];
const bytes = () => new Uint8Array([37, 80, 68, 70, 45, 49, 46, 52]);
let document: ReturnType<typeof pdfDocument>;
let task: ReturnType<typeof loadingTask>;
let worker: { destroy: ReturnType<typeof vi.fn> };

function viewer(source = bytes(), label = 'Synthetic attachment.pdf') {
  const wrapper = mount(UblEmbeddedPdfViewer, { props: { bytes: source, label } });
  wrappers.push(wrapper);
  return wrapper;
}

async function readyViewer(source = bytes()) {
  const wrapper = viewer(source);
  await flushPromises();
  OwnedWorker.instances[OwnedWorker.instances.length - 1].ready();
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  pdfjs.getDocument.mockReset();
  pdfjs.createWorker.mockReset();
  asset.url = '/assets/synthetic-pdf.worker.mjs';
  OwnedWorker.instances = [];
  vi.stubGlobal('Worker', OwnedWorker);
  document = pdfDocument();
  task = loadingTask(Promise.resolve(document));
  worker = { destroy: vi.fn() };
  pdfjs.getDocument.mockReturnValue(task);
  pdfjs.createWorker.mockReturnValue(worker);
});

afterEach(async () => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount();
  await flushPromises();
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('UblEmbeddedPdfViewer', () => {
  it('renders the first page and supports bounded page navigation', async () => {
    const wrapper = await readyViewer();
    expect(document.getPage).toHaveBeenCalledWith(1);
    expect(document.page.render).toHaveBeenCalledTimes(1);
    expect(document.page.cleanup).toHaveBeenCalledTimes(1);
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    expect(wrapper.get('canvas').attributes('aria-label')).toBe('Synthetic attachment.pdf, page 1');
    expect(wrapper.get('canvas').element.width).toBe(900);
    expect(wrapper.get('canvas').element.height).toBe(1200);
    expect(wrapper.get('button[aria-label="Previous"]').attributes('disabled')).toBeDefined();
    await wrapper.get('button[aria-label="Next"]').trigger('click');
    await flushPromises();
    expect(document.getPage).toHaveBeenLastCalledWith(2);
    expect(wrapper.text()).toContain('Page 2 of 2');
    expect(wrapper.get('button[aria-label="Next"]').attributes('disabled')).toBeDefined();
    await wrapper.get('button[aria-label="Previous"]').trigger('click');
    await flushPromises();
    expect(document.getPage).toHaveBeenLastCalledWith(1);
    expect(document.page.render).toHaveBeenCalledTimes(3);
  });

  it('waits for the owned worker readiness and supplies only a local worker with bounded security options', async () => {
    const wrapper = viewer();
    await flushPromises();
    const port = OwnedWorker.instances[0];
    expect(port.url.origin).toBe(window.location.origin);
    expect(port.url.pathname).toBe('/assets/synthetic-pdf.worker.mjs');
    expect(port.options).toEqual({ type: 'module' });
    expect(pdfjs.getDocument).not.toHaveBeenCalled();
    port.dispatchEvent(new MessageEvent('message', { data: { action: 'ready', sourceName: 'untrusted', targetName: 'main' } }));
    await flushPromises();
    expect(pdfjs.getDocument).not.toHaveBeenCalled();
    port.ready();
    await flushPromises();
    expect(pdfjs.createWorker).toHaveBeenCalledWith({ port, verbosity: 0 });
    expect(pdfjs.getDocument).toHaveBeenCalledWith(expect.objectContaining({
      data: expect.any(Uint8Array), worker, disableWorkerRendering: true,
      enableXfa: false, disableFontFace: true, stopAtErrors: true,
      maxImageSize: 2_000_000, canvasMaxAreaInBytes: 8_000_000,
      useWorkerFetch: false, useWasm: false, verbosity: 0,
    }));
    expect(pdfjs.getDocument.mock.calls[0][0]).not.toHaveProperty('url');
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
  });

  it('transfers a copy to PDF.js while keeping the source bytes usable for download', async () => {
    const source = bytes();
    const original = Array.from(source);
    let forwarded: Uint8Array | undefined;
    pdfjs.getDocument.mockImplementation((options: { data: Uint8Array }) => {
      forwarded = options.data;
      expect(forwarded).not.toBe(source);
      const transferred = structuredClone(forwarded, { transfer: [forwarded.buffer as ArrayBuffer] });
      expect(Array.from(transferred)).toEqual(original);
      return task;
    });
    await readyViewer(source);
    expect(forwarded?.byteLength).toBe(0);
    expect(Array.from(source)).toEqual(original);
  });

  it.each([new Uint8Array(), new Uint8Array(1024 * 1024 + 1)])('handles invalid byte size without a worker or unhandled rejection', async (source) => {
    const unhandled = vi.fn();
    window.addEventListener('unhandledrejection', unhandled);
    try {
      const wrapper = viewer(source);
      await flushPromises();
      expect(wrapper.get('[role="alert"]').text()).toBe('PDF preview unavailable. Download the attachment to open it.');
      expect(OwnedWorker.instances).toHaveLength(0);
      expect(pdfjs.getDocument).not.toHaveBeenCalled();
      expect(unhandled).not.toHaveBeenCalled();
    } finally { window.removeEventListener('unhandledrejection', unhandled); }
  });

  it.each([0, 101, 1.5, Number.NaN])('rejects invalid page count %s and disposes its worker', async (count) => {
    document.numPages = count;
    const wrapper = await readyViewer();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    expect(document.getPage).not.toHaveBeenCalled();
    expect(task.destroy).toHaveBeenCalledTimes(1);
    expect(worker.destroy).toHaveBeenCalledTimes(1);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
  });

  it.each([[0, 800], [600, Number.NaN], [Number.POSITIVE_INFINITY, 800], [600, 20_001]])('rejects an invalid viewport %s by %s', async (width, height) => {
    document = pdfDocument(1, width, height);
    task = loadingTask(Promise.resolve(document));
    pdfjs.getDocument.mockReturnValue(task);
    const wrapper = await readyViewer();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    expect(document.page.render).not.toHaveBeenCalled();
    expect(wrapper.get('canvas').element.width).toBe(0);
    expect(wrapper.get('canvas').element.height).toBe(0);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
  });

  it('scales a large allowed page within canvas dimension and area limits', async () => {
    document = pdfDocument(1, 20_000, 20_000);
    task = loadingTask(Promise.resolve(document));
    pdfjs.getDocument.mockReturnValue(task);
    const wrapper = await readyViewer();
    const canvas = wrapper.get('canvas').element;
    expect(canvas.width).toBeGreaterThan(0);
    expect(canvas.width).toBeLessThanOrEqual(1600);
    expect(canvas.height).toBeLessThanOrEqual(1600);
    expect(canvas.width * canvas.height).toBeLessThanOrEqual(2_000_000);
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
  });

  it('cancels pending rendering and destroys the loading task and owned worker on unmount', async () => {
    const pendingRender = deferred<void>();
    document = pdfDocument(1, 600, 800, pendingRender.promise);
    task = loadingTask(Promise.resolve(document));
    pdfjs.getDocument.mockReturnValue(task);
    const wrapper = await readyViewer();
    expect(document.page.render).toHaveBeenCalledTimes(1);
    wrapper.unmount();
    expect(document.render.cancel).toHaveBeenCalledTimes(1);
    expect(task.destroy).toHaveBeenCalledTimes(1);
    expect(worker.destroy).toHaveBeenCalledTimes(1);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
    pendingRender.resolve();
    await flushPromises();
    expect(document.page.cleanup).not.toHaveBeenCalled();
  });

  it('ignores a late document load after switching source and renders only the current document', async () => {
    const oldDocument = pdfDocument();
    const lateLoad = deferred<ReturnType<typeof pdfDocument>>();
    const oldTask = loadingTask(lateLoad.promise);
    const oldWorker = { destroy: vi.fn() };
    pdfjs.getDocument.mockReturnValueOnce(oldTask).mockReturnValueOnce(task);
    pdfjs.createWorker.mockReturnValueOnce(oldWorker).mockReturnValueOnce(worker);
    const wrapper = await readyViewer();
    expect(oldDocument.getPage).not.toHaveBeenCalled();
    await wrapper.setProps({ bytes: bytes(), label: 'Current attachment.pdf' });
    await flushPromises();
    OwnedWorker.instances[1].ready();
    await flushPromises();
    expect(oldTask.destroy).toHaveBeenCalledTimes(1);
    expect(oldWorker.destroy).toHaveBeenCalledTimes(1);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
    expect(document.getPage).toHaveBeenCalledTimes(1);
    lateLoad.resolve(oldDocument);
    await flushPromises();
    expect(oldDocument.getPage).not.toHaveBeenCalled();
    expect(wrapper.get('canvas').attributes('aria-label')).toBe('Current attachment.pdf, page 1');
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
  });

  it('cancels the old render on source switch and ignores its late completion', async () => {
    const pendingRender = deferred<void>();
    const oldDocument = pdfDocument(1, 600, 800, pendingRender.promise);
    const oldTask = loadingTask(Promise.resolve(oldDocument));
    pdfjs.getDocument.mockReturnValueOnce(oldTask).mockReturnValueOnce(task);
    const wrapper = await readyViewer();
    await wrapper.setProps({ bytes: bytes() });
    await flushPromises();
    OwnedWorker.instances[1].ready();
    await flushPromises();
    expect(oldDocument.render.cancel).toHaveBeenCalledTimes(1);
    expect(oldTask.destroy).toHaveBeenCalledTimes(1);
    pendingRender.resolve();
    await flushPromises();
    expect(oldDocument.page.cleanup).not.toHaveBeenCalled();
    expect(document.page.cleanup).toHaveBeenCalledTimes(1);
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
  });

  it.each(['error', 'messageerror'])('fails closed on worker %s', async (eventName) => {
    const wrapper = viewer();
    await flushPromises();
    OwnedWorker.instances[0].dispatchEvent(new Event(eventName));
    await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
    expect(pdfjs.getDocument).not.toHaveBeenCalled();
  });

  it('rejects an external worker asset before creating a worker', async () => {
    asset.url = 'https://example.invalid/untrusted.worker.mjs';
    const wrapper = viewer();
    await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    expect(OwnedWorker.instances).toHaveLength(0);
    expect(pdfjs.getDocument).not.toHaveBeenCalled();
  });

  it('times out worker readiness at 15 seconds and terminates its owned port', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    const wrapper = viewer();
    await flushPromises();
    await vi.advanceTimersByTimeAsync(14_999);
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    await vi.advanceTimersByTimeAsync(1);
    await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    expect(OwnedWorker.instances[0].terminate).toHaveBeenCalledTimes(1);
    expect(pdfjs.getDocument).not.toHaveBeenCalled();
  });
});

<template>
  <div class="mt-3 rounded border border-gray-200 bg-gray-50 p-3">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
      <span class="min-w-0 break-all text-sm font-medium">{{ label }}</span>
      <div v-if="pages > 1" class="flex items-center gap-2 text-sm">
        <button type="button" class="invoicing-btn-secondary" :disabled="loading || pageNumber <= 1" :aria-label="t('invoicing.ubl_pdf_previous')" @click="changePage(-1)">{{ t('invoicing.ubl_pdf_previous') }}</button>
        <span>{{ t('invoicing.ubl_pdf_page', { page: pageNumber, total: pages }) }}</span>
        <button type="button" class="invoicing-btn-secondary" :disabled="loading || pageNumber >= pages" :aria-label="t('invoicing.ubl_pdf_next')" @click="changePage(1)">{{ t('invoicing.ubl_pdf_next') }}</button>
      </div>
    </div>
    <p v-if="failed" role="alert" class="text-sm text-amber-700">{{ t('invoicing.ubl_pdf_preview_failed') }}</p>
    <p v-else-if="loading" role="status" class="text-sm text-gray-600">{{ t('common.loading') }}</p>
    <canvas ref="canvas" v-show="!loading && !failed" role="img" :aria-label="t('invoicing.ubl_pdf_canvas', { name: label, page: pageNumber })" class="mx-auto h-auto max-w-full bg-white" />
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PDFDocumentProxy, PDFDocumentLoadingTask, PDFWorker, RenderTask } from 'pdfjs-dist/legacy/build/pdf.mjs';

const props = defineProps<{ bytes: Uint8Array; label: string }>();
const { t } = useI18n();
const canvas = ref<HTMLCanvasElement | null>(null);
const loading = ref(true);
const failed = ref(false);
const pages = ref(0);
const pageNumber = ref(1);
let stop = () => {};
let renderPage: (() => Promise<void>) | undefined;

watch(() => props.bytes, bytes => {
  stop();
  pages.value = 0;
  pageNumber.value = 1;
  failed.value = false;
  loading.value = true;
  renderPage = undefined;
  if (canvas.value) canvas.value.width = canvas.value.height = 0;

  let disposed = false;
  let port: Worker | undefined;
  let worker: PDFWorker | undefined;
  let task: PDFDocumentLoadingTask | undefined;
  let document: PDFDocumentProxy | undefined;
  let render: RenderTask | undefined;
  let renderGeneration = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let cancelWait: (() => void) | undefined;
  const cancelled = new Promise<never>((_, reject) => { cancelWait = () => reject(new Error('cancelled')); });
  void cancelled.catch(() => undefined);
  // Every async stage races cancellation, including dynamic module loading.
  const bounded = <T,>(promise: Promise<T>): Promise<T> => Promise.race([promise, cancelled]);
  const dispose = () => {
    if (disposed) return;
    disposed = true;
    renderGeneration++;
    clearTimeout(timer);
    cancelWait?.();
    try {
      render?.cancel();
      void task?.destroy().catch(() => undefined);
      worker?.destroy();
    } finally { port?.terminate(); }
  };
  stop = dispose;
  const fail = () => {
    if (disposed) return;
    failed.value = true;
    loading.value = false;
    pages.value = 0;
    if (canvas.value) canvas.value.width = canvas.value.height = 0;
    dispose();
  };
  const deadline = () => {
    clearTimeout(timer);
    timer = setTimeout(fail, 15_000);
  };
  const draw = async () => {
    if (!document || disposed) return;
    const generation = ++renderGeneration;
    render?.cancel();
    loading.value = true;
    deadline();
    try {
      const page = await bounded(document.getPage(pageNumber.value));
      if (disposed || generation !== renderGeneration) return;
      const base = page.getViewport({ scale: 1 });
      if (![base.width, base.height].every(value => Number.isFinite(value) && value > 0 && value <= 20_000)) throw new Error('page_size');
      const scale = Math.min(1.5, 1600 / base.width, 1600 / base.height, Math.sqrt(2_000_000 / (base.width * base.height)));
      const viewport = page.getViewport({ scale });
      const target = canvas.value;
      if (!target) throw new Error('canvas');
      target.width = Math.max(1, Math.floor(viewport.width));
      target.height = Math.max(1, Math.floor(viewport.height));
      render = page.render({ canvas: target, viewport });
      await bounded(render.promise);
      if (disposed || generation !== renderGeneration) return;
      clearTimeout(timer);
      loading.value = false;
      page.cleanup();
    } catch {
      if (!disposed && generation === renderGeneration) fail();
    }
  };
  const load = async () => {
    try {
      if (!bytes.length || bytes.length > 1024 * 1024) throw new Error('size');
      deadline();
      const [{ getDocument, PDFWorker, VerbosityLevel }, { default: workerUrl }] = await bounded(Promise.all([
        import('pdfjs-dist/legacy/build/pdf.mjs'),
        import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url'),
      ]));
      if (disposed) return;
      const url = new URL(workerUrl, window.location.href);
      if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) throw new Error('worker_origin');
      const livePort = port = new Worker(url, { type: 'module' });
      const workerFailure = new Promise<never>((_, reject) => {
        livePort.addEventListener('error', () => { fail(); reject(new Error('worker')); }, { once: true });
        livePort.addEventListener('messageerror', () => { fail(); reject(new Error('worker')); }, { once: true });
      });
      await bounded(Promise.race([new Promise<void>(resolve => {
        const ready = (event: MessageEvent<unknown>) => {
          const data = event.data;
          if (!data || typeof data !== 'object' || !('action' in data) || data.action !== 'ready'
            || !('sourceName' in data) || data.sourceName !== 'worker'
            || !('targetName' in data) || data.targetName !== 'main') return;
          livePort.removeEventListener('message', ready);
          resolve();
        };
        livePort.addEventListener('message', ready);
      }), workerFailure]));
      if (disposed) return;
      worker = PDFWorker.create({ port: livePort, verbosity: VerbosityLevel.ERRORS });
      task = getDocument({
        data: new Uint8Array(bytes), worker, disableWorkerRendering: true,
        enableXfa: false, disableFontFace: true, stopAtErrors: true,
        maxImageSize: 2_000_000, canvasMaxAreaInBytes: 8_000_000,
        useWorkerFetch: false, useWasm: false, verbosity: VerbosityLevel.ERRORS,
      });
      document = await bounded(Promise.race([task.promise, workerFailure]));
      if (disposed) return;
      if (!Number.isInteger(document.numPages) || document.numPages < 1 || document.numPages > 100) throw new Error('pages');
      pages.value = document.numPages;
      renderPage = draw;
      await draw();
    } catch { fail(); }
  };
  void load();
}, { immediate: true, flush: 'sync' });

function changePage(delta: number) {
  if (loading.value || failed.value) return;
  const next = pageNumber.value + delta;
  if (next < 1 || next > pages.value) return;
  pageNumber.value = next;
  void renderPage?.();
}
onBeforeUnmount(() => stop());
</script>

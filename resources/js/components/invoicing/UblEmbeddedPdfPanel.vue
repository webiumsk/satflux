<template>
  <section v-if="result.pdfs.length || result.rejectedCount || result.error" class="mt-3 space-y-2 rounded border border-gray-200 bg-white p-3" data-testid="ubl-embedded-pdfs">
    <h3 class="text-sm font-medium text-gray-900">{{ t('invoicing.ubl_pdf_title') }}</h3>
    <p v-if="result.rejectedCount || result.error" role="alert" class="text-sm text-amber-700">{{ t('invoicing.ubl_pdf_rejected') }}</p>
    <ul class="space-y-2">
      <li v-for="pdf in result.pdfs" :key="pdf.id" class="flex flex-wrap items-center justify-between gap-2">
        <span class="min-w-0 break-all text-sm">{{ pdf.filename }}</span>
        <div class="flex gap-2">
          <button type="button" class="invoicing-btn-secondary" :aria-expanded="selectedId === pdf.id" :aria-label="selectedId === pdf.id ? t('invoicing.ubl_pdf_close') : t('invoicing.ubl_pdf_view_named', { name: pdf.filename })" @click="selectedId = selectedId === pdf.id ? null : pdf.id">{{ t(selectedId === pdf.id ? 'invoicing.ubl_pdf_close' : 'invoicing.ubl_pdf_view') }}</button>
          <button type="button" class="invoicing-btn-secondary" :aria-label="t('invoicing.ubl_pdf_download_named', { name: pdf.filename })" @click="download(pdf)">{{ t('invoicing.ubl_pdf_download') }}</button>
        </div>
      </li>
    </ul>
    <UblEmbeddedPdfViewer v-if="selected" :key="selected.id" :bytes="selected.bytes" :label="selected.filename" />
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { extractUblEmbeddedPdfs, type EmbeddedUblPdf } from '@/utils/ublEmbeddedPdf';
import UblEmbeddedPdfViewer from './UblEmbeddedPdfViewer.vue';

const props = defineProps<{ xml: string | null }>();
const { t } = useI18n();
const result = computed(() => {
  const extracted = props.xml ? extractUblEmbeddedPdfs(props.xml) : { pdfs: [], rejectedCount: 0 };
  // Other XML attachments, such as ISDOC, keep their existing display.
  return extracted.error === 'unsupported_document' ? { pdfs: [], rejectedCount: 0 } : extracted;
});
const selectedId = ref<string | null>(null);
const selected = computed(() => result.value.pdfs.find(pdf => pdf.id === selectedId.value));
const downloads = new Map<string, ReturnType<typeof setTimeout>>();
function clearDownloads() {
  for (const [url, timer] of downloads) {
    clearTimeout(timer);
    URL.revokeObjectURL(url);
  }
  downloads.clear();
}
watch(() => props.xml, () => { selectedId.value = null; clearDownloads(); }, { flush: 'sync' });
function download(pdf: EmbeddedUblPdf) {
  const url = URL.createObjectURL(new Blob([new Uint8Array(pdf.bytes)], { type: 'application/pdf' }));
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = pdf.filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  downloads.set(url, setTimeout(() => { URL.revokeObjectURL(url); downloads.delete(url); }, 1000));
}
onBeforeUnmount(clearDownloads);
</script>

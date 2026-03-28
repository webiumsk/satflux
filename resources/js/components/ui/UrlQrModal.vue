<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="emit('close')"
    >
      <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-sm w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h5 class="text-lg font-bold text-white">{{ title || t('common.qr_code') }}</h5>
          <button type="button" @click="emit('close')" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div v-if="url" class="flex justify-center mb-4">
          <div class="p-4 bg-white rounded-lg">
            <canvas ref="qrCanvas" class="block"></canvas>
          </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 pt-2">
          <button
            v-if="showCopy && url"
            type="button"
            :title="copied ? t('common.copied') : t('common.copy_url')"
            @click="copyUrl"
            class="inline-flex items-center gap-2 px-4 py-2 border border-gray-600 text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors"
          >
            <svg v-if="copied" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            {{ copied ? t('common.copied') : t('common.copy_url') }}
          </button>
          <button
            v-if="showDownload && url"
            type="button"
            @click="downloadQr"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ t('common.download') }}
          </button>
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
          >
            {{ t('common.close') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import QRCode from 'qrcode';

const props = withDefaults(
  defineProps<{
    open: boolean;
    url: string;
    title?: string;
    showCopy?: boolean;
    showDownload?: boolean;
    downloadFilename?: string;
  }>(),
  { title: '', showCopy: true, showDownload: false, downloadFilename: 'qrcode.png' }
);

const emit = defineEmits<{
  close: [];
}>();

const { t } = useI18n();
const qrCanvas = ref<HTMLCanvasElement | null>(null);
const copied = ref(false);
let copiedTimeout: ReturnType<typeof setTimeout> | null = null;

const DOWNLOAD_SIZE = 1024;

async function copyUrl() {
  if (!props.url) return;
  try {
    await navigator.clipboard.writeText(props.url);
    copied.value = true;
    if (copiedTimeout) clearTimeout(copiedTimeout);
    copiedTimeout = setTimeout(() => {
      copied.value = false;
      copiedTimeout = null;
    }, 2000);
  } catch (e) {
    console.error('Copy URL failed', e);
  }
}

async function drawQr() {
  if (!props.open || !props.url || !qrCanvas.value) return;
  try {
    await QRCode.toCanvas(qrCanvas.value, props.url, { width: 256, margin: 2 });
  } catch (e) {
    console.error('QR draw failed', e);
  }
}

async function downloadQr() {
  if (!props.url) return;
  try {
    const canvas = document.createElement('canvas');
    await QRCode.toCanvas(canvas, props.url, { width: DOWNLOAD_SIZE, margin: 2 });
    canvas.toBlob((blob) => {
      if (!blob) return;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = props.downloadFilename;
      a.click();
      URL.revokeObjectURL(url);
    }, 'image/png');
  } catch (e) {
    console.error('QR download failed', e);
  }
}

watch(
  () => [props.open, props.url] as const,
  () => {
    if (props.open && props.url) {
      nextTick(() => drawQr());
    }
    if (!props.open) {
      copied.value = false;
      if (copiedTimeout) {
        clearTimeout(copiedTimeout);
        copiedTimeout = null;
      }
    }
  },
  { immediate: true }
);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 bg-black/50 flex items-center justify-center p-4"
      :class="zIndex === 50 ? 'z-50' : ''"
      :style="zIndex !== 50 ? { zIndex } : undefined"
      @click.self="emit('close')"
    >
      <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-sm w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h5 class="text-lg font-bold text-white">{{ title }}</h5>
          <button type="button" @click="emit('close')" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-sm text-gray-400 mb-4">{{ t('account.scan_qr_with_wallet') }}</p>

        <!-- Countdown -->
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span v-if="!expired">{{ t('account.expires_in') }} {{ countdownDisplay }}</span>
          <span v-else class="text-amber-400">{{ t('account.challenge_expired') }}</span>
        </div>

        <!-- QR code (hidden when expired) -->
        <div v-if="!expired && encodedLnurl" class="flex justify-center mb-4">
          <div class="p-4 bg-white rounded-lg">
            <canvas ref="qrCanvas" class="block"></canvas>
          </div>
        </div>

        <!-- Expired: show message + Try again button -->
        <div v-if="expired" class="mb-4 py-6 rounded-lg bg-gray-700/50 border border-gray-600 flex flex-col items-center justify-center gap-4">
          <p class="text-sm text-gray-400 text-center">{{ t('account.challenge_expired') }}</p>
          <button
            type="button"
            @click="emit('regenerate')"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors"
          >
            {{ t('account.try_again') }}
          </button>
        </div>

        <!-- Copy + Open in Wallet (disabled when expired) -->
        <div v-if="encodedLnurl" class="flex flex-wrap gap-2 mb-4">
          <button
            type="button"
            :disabled="expired"
            @click="copyToClipboard"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="expired
              ? 'text-gray-500 bg-gray-700 cursor-not-allowed'
              : 'text-gray-300 bg-gray-700 hover:bg-gray-600'"
            :title="t('account.copy_lnurl')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            {{ copySuccess ? t('common.copied') : t('account.copy_lnurl') }}
          </button>
          <a
            v-if="!expired"
            :href="'lightning:' + encodedLnurl"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-indigo-400 hover:text-indigo-300 bg-gray-700 hover:bg-gray-600 transition-colors"
          >
            {{ t('account.open_in_wallet') }}
          </a>
          <span
            v-else
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-500 bg-gray-700 cursor-not-allowed"
          >
            {{ t('account.open_in_wallet') }}
          </span>
        </div>

        <p v-if="error" class="text-sm text-red-400 mb-2">{{ error }}</p>
        <p v-if="polling && !expired" class="text-sm text-gray-500 mb-2">{{ t('account.waiting_for_wallet') }}...</p>

        <div class="flex justify-end pt-2">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
          >
            {{ t('common.cancel') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { bech32 } from 'bech32';
import QRCode from 'qrcode';

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    lnurl: string;
    error?: string;
    polling?: boolean;
    expiresInSeconds?: number;
    zIndex?: number;
  }>(),
  { error: '', polling: false, expiresInSeconds: 300, zIndex: 50 }
);

const emit = defineEmits<{
  close: [];
  regenerate: [];
}>();

const { t } = useI18n();

const qrCanvas = ref<HTMLCanvasElement | null>(null);
const countdownSeconds = ref(props.expiresInSeconds);
const copySuccess = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;
let copySuccessTimeout: ReturnType<typeof setTimeout> | null = null;

const encodedLnurl = computed(() => {
  if (!props.lnurl) return '';
  try {
    const bytes = new TextEncoder().encode(props.lnurl);
    const words = bech32.toWords(Array.from(bytes));
    return bech32.encode('lnurl', words, 1023).toUpperCase();
  } catch {
    return '';
  }
});

const expired = computed(() => countdownSeconds.value <= 0);

const countdownDisplay = computed(() => {
  const s = countdownSeconds.value;
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return `${m}:${sec.toString().padStart(2, '0')}`;
});

function startCountdown() {
  stopCountdown();
  countdownSeconds.value = props.expiresInSeconds;
  countdownInterval = setInterval(() => {
    countdownSeconds.value--;
    if (countdownSeconds.value <= 0) stopCountdown();
  }, 1000);
}

function stopCountdown() {
  if (countdownInterval != null) {
    clearInterval(countdownInterval);
    countdownInterval = null;
  }
}

async function drawQr() {
  if (!props.open || !encodedLnurl.value || expired.value || !qrCanvas.value) return;
  try {
    await QRCode.toCanvas(qrCanvas.value, encodedLnurl.value, { width: 256, margin: 2 });
  } catch (e) {
    console.error('QR draw failed', e);
  }
}

async function copyToClipboard() {
  if (expired.value || !encodedLnurl.value) return;
  try {
    await navigator.clipboard.writeText(encodedLnurl.value);
    copySuccess.value = true;
    if (copySuccessTimeout) clearTimeout(copySuccessTimeout);
    copySuccessTimeout = setTimeout(() => {
      copySuccess.value = false;
      copySuccessTimeout = null;
    }, 2000);
  } catch {
    // optional: emit or show copy_failed
  }
}

watch(
  () => [props.open, props.lnurl] as const,
  ([open, lnurl]) => {
    if (open && lnurl) {
      countdownSeconds.value = props.expiresInSeconds;
      startCountdown();
      nextTick(() => drawQr());
    } else {
      stopCountdown();
    }
  },
  { immediate: true }
);

watch(
  () => props.lnurl,
  (newUrl, oldUrl) => {
    if (newUrl && newUrl !== oldUrl && props.open) {
      countdownSeconds.value = props.expiresInSeconds;
      if (countdownInterval == null) startCountdown();
      nextTick(() => drawQr());
    }
  }
);

onUnmounted(() => {
  stopCountdown();
  if (copySuccessTimeout) clearTimeout(copySuccessTimeout);
});
</script>

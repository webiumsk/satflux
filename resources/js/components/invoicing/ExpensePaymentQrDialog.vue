<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      class="expense-payment-qr-dialog rounded-xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/40"
      :aria-labelledby="titleId"
      @cancel.prevent="emit('close')"
      @close="emit('close')"
      @click.self="emit('close')"
    >
      <div v-if="details" class="space-y-4 p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
          <h2 :id="titleId" class="text-lg font-semibold">{{ t('invoicing.expense_payment_qr_title') }}</h2>
          <button ref="closeButton" type="button" class="invoicing-btn-secondary shrink-0" @click="emit('close')">
            {{ t('common.close') }}
          </button>
        </div>

        <div class="flex flex-wrap gap-2" role="group" :aria-label="t('invoicing.expense_payment_qr_format')">
          <button
            v-for="option in formats"
            :key="option.value"
            type="button"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :class="format === option.value ? 'invoicing-btn-primary' : 'invoicing-btn-secondary'"
            :aria-pressed="format === option.value"
            :disabled="option.value === 'payme' && !paymeAvailable"
            :aria-describedby="option.value === 'payme' && !paymeAvailable ? paymeAmountHintId : undefined"
            @click="format = option.value"
          >{{ option.label }}</button>
        </div>
        <p v-if="!paymeAvailable" :id="paymeAmountHintId" class="text-sm text-gray-600">
          {{ t('invoicing.expense_payment_qr_payme_amount_unavailable') }}
        </p>

        <div class="flex min-h-64 items-center justify-center rounded-lg border border-gray-200 bg-white p-2" :aria-busy="loading">
          <img
            v-if="qrImage"
            :src="qrImage"
            :alt="t('invoicing.expense_payment_qr_image_alt', { format: formatLabel })"
            class="block h-auto max-w-full"
            width="256"
            height="256"
          />
          <p v-else-if="loading" role="status" class="text-sm text-gray-600">{{ t('invoicing.expense_payment_qr_loading') }}</p>
          <div v-else-if="failed" class="space-y-3 text-center">
            <p role="alert" class="text-sm text-red-700">{{ t('invoicing.expense_payment_qr_error') }}</p>
            <button type="button" class="invoicing-btn-secondary" @click="renderQr">{{ t('invoicing.expense_payment_qr_retry') }}</button>
          </div>
        </div>

        <dl class="space-y-2 text-sm">
          <div v-if="details.supplier">
            <dt class="text-gray-500">{{ t('invoicing.expense_payment_qr_recipient') }}</dt>
            <dd class="break-words font-medium">{{ details.supplier }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">{{ t('invoicing.expense_payment_qr_iban') }}</dt>
            <dd class="break-all font-mono">{{ details.iban }}</dd>
          </div>
          <div class="flex flex-wrap gap-x-8 gap-y-2">
            <div>
              <dt class="text-gray-500">{{ t('invoicing.expense_payment_qr_amount') }}</dt>
              <dd class="font-semibold">{{ details.amount }} {{ details.currency }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">{{ t('invoicing.expense_payment_qr_vs') }}</dt>
              <dd class="font-mono">{{ details.variableSymbol }}</dd>
            </div>
          </div>
        </dl>
        <p class="text-sm text-gray-600">{{ t('invoicing.expense_payment_qr_verify') }}</p>
        <a
          v-if="format === 'payme' && qrImage && currentPayload"
          :href="currentPayload"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="invoicing-btn-primary inline-flex"
        >{{ t('invoicing.expense_payment_qr_open_payme') }}</a>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import QRCode from 'qrcode';
import { canCreateExpensePayMe, expensePayBySquarePayload, expensePayMePayload, type expensePaymentQrDetails } from '../../utils/expensePaymentQr';

type PaymentDetails = NonNullable<ReturnType<typeof expensePaymentQrDetails>>;
type QrFormat = 'bysquare' | 'payme';

const props = defineProps<{ details: PaymentDetails | null }>();
const emit = defineEmits<{ close: [] }>();
const { t } = useI18n();
const titleId = `expense-payment-qr-${useId()}`;
const paymeAmountHintId = `${titleId}-payme-amount`;
const paymeAvailable = computed(() => Boolean(props.details && canCreateExpensePayMe(props.details)));
const dialog = ref<HTMLDialogElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const format = ref<QrFormat>('bysquare');
const formats: { value: QrFormat; label: string }[] = [
  { value: 'bysquare', label: 'PAY by square' },
  { value: 'payme', label: 'PayMe' },
];
const formatLabel = computed(() => formats.find(option => option.value === format.value)!.label);
const qrImage = ref('');
const currentPayload = ref('');
const loading = ref(false);
const failed = ref(false);
let generation = 0;
let previousFocus: HTMLElement | null = null;

function restoreFocus() {
  if (previousFocus?.isConnected) previousFocus.focus();
  previousFocus = null;
}

async function renderQr() {
  const request = ++generation;
  const details = props.details;
  const selectedFormat = format.value;
  qrImage.value = '';
  currentPayload.value = '';
  failed.value = false;
  loading.value = Boolean(details);

  if (!details) {
    if (dialog.value?.open) dialog.value.close();
    restoreFocus();
    return;
  }

  await nextTick();
  if (request !== generation) return;
  if (dialog.value && !dialog.value.open) {
    previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.value.showModal();
    closeButton.value?.focus();
  } else if (dialog.value && !dialog.value.contains(document.activeElement)) {
    // A retry removes its focused button while the next image is prepared.
    closeButton.value?.focus();
  }

  try {
    const payload = selectedFormat === 'payme' ? expensePayMePayload(details) : expensePayBySquarePayload(details);
    const data = selectedFormat === 'bysquare' ? [{ data: payload, mode: 'alphanumeric' as const }] : payload;
    const image = await QRCode.toDataURL(data, {
      width: 512, margin: 4, errorCorrectionLevel: selectedFormat === 'bysquare' ? 'L' : 'M',
    });
    if (request !== generation) return;
    currentPayload.value = payload;
    qrImage.value = image;
  } catch {
    if (request === generation) failed.value = true;
  } finally {
    if (request === generation) loading.value = false;
  }
}

watch([() => props.details, format, paymeAvailable], () => {
  if (format.value === 'payme' && !paymeAvailable.value) {
    format.value = 'bysquare';
    return;
  }
  void renderQr();
}, { immediate: true, flush: 'sync' });
onBeforeUnmount(() => {
  generation++;
  if (dialog.value?.open) dialog.value.close();
  restoreFocus();
});
</script>

<style scoped>
.expense-payment-qr-dialog {
  width: min(28rem, calc(100vw - 2rem));
  max-height: calc(100dvh - 2rem);
  overflow-y: auto;
}
</style>

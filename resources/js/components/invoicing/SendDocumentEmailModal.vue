<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
    role="dialog"
    aria-modal="true"
    @click.self="close"
  >
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">{{ t('invoicing.send_email_title') }}</h2>
        <button type="button" class="text-gray-400 hover:text-gray-700 text-xl leading-none" @click="close">×</button>
      </div>

      <form class="p-5 space-y-4" @submit.prevent="send">
        <div v-if="loadingPreview" class="text-sm text-gray-500">{{ t('common.loading') }}</div>

        <template v-else>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.send_email_to') }} *</label>
            <input
              v-model="toInput"
              type="text"
              class="invoicing-sf-input"
              :placeholder="t('invoicing.send_email_to_placeholder')"
              required
            />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.send_email_cc') }}</label>
            <input v-model="ccInput" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.send_email_bcc') }}</label>
            <input v-model="bccInput" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.send_email_subject') }}</label>
            <input v-model="subject" type="text" class="invoicing-sf-input" required />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.send_email_body') }}</label>
            <textarea v-model="body" rows="10" class="invoicing-sf-input font-mono text-sm" required />
          </div>
          <p v-if="attachmentName" class="text-xs text-gray-500">
            {{ t('invoicing.send_email_attachment') }}: {{ attachmentName }}
          </p>
        </template>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="invoicing-btn-secondary" :disabled="sending" @click="close">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="invoicing-btn-primary" :disabled="sending || loadingPreview">
            {{ sending ? t('invoicing.send_email_sending') : t('invoicing.send_email_submit') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { invoicingApi } from '../../services/api';
import {
  previewEphemeralEmail,
  sendEphemeralEmail,
  type EphemeralSnapshotPayload,
} from '../../evolu/ephemeralBridge';

const props = defineProps<{
  open: boolean;
  companyId: string;
  documentId: string;
  ephemeralSnapshot?: EphemeralSnapshotPayload | null;
  bridgeCompanyId?: string | null;
}>();

const emit = defineEmits<{
  close: [];
  sent: [payload?: { email_sent_at?: string }];
}>();

const { t } = useI18n();

const loadingPreview = ref(false);
const sending = ref(false);
const error = ref('');
const toInput = ref('');
const ccInput = ref('');
const bccInput = ref('');
const subject = ref('');
const body = ref('');
const attachmentName = ref('');

function extractError(e: unknown): string {
  if (e instanceof Error && e.message) {
    return e.message;
  }
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
  const fieldErrors = err?.response?.data?.errors;
  if (fieldErrors && typeof fieldErrors === 'object') {
    for (const messages of Object.values(fieldErrors)) {
      if (Array.isArray(messages) && messages[0]) {
        return String(messages[0]);
      }
    }
  }
  return (
    err?.response?.data?.message
    ?? err?.response?.data?.errors?.status?.[0]
    ?? t('common.error_generic')
  );
}

async function loadPreview() {
  if (!props.documentId && !props.ephemeralSnapshot) return;
  loadingPreview.value = true;
  error.value = '';
  try {
    if (props.ephemeralSnapshot) {
      const data = await previewEphemeralEmail(props.ephemeralSnapshot, props.bridgeCompanyId);
      toInput.value = data.to ?? '';
      subject.value = data.subject ?? '';
      body.value = data.body ?? '';
      attachmentName.value = data.attachment_filename ?? '';
      return;
    }
    const data = await invoicingApi.documents.emailPreview<{
      to?: string;
      subject?: string;
      body?: string;
      attachment_filename?: string;
    }>(props.companyId, props.documentId);
    toInput.value = data.to ?? '';
    subject.value = data.subject ?? '';
    body.value = data.body ?? '';
    attachmentName.value = data.attachment_filename ?? '';
  } catch (e: unknown) {
    error.value = extractError(e);
  } finally {
    loadingPreview.value = false;
  }
}

async function send() {
  sending.value = true;
  error.value = '';
  try {
    if (props.ephemeralSnapshot) {
      const result = await sendEphemeralEmail(props.ephemeralSnapshot, {
        to: toInput.value,
        cc: ccInput.value || undefined,
        bcc: bccInput.value || undefined,
        subject: subject.value,
        body: body.value,
      }, props.bridgeCompanyId);
      emit('sent', { email_sent_at: result.email_sent_at });
      close();
      return;
    }
    const sent = await invoicingApi.documents.sendEmail<{ email_sent_at?: string }>(props.companyId, props.documentId, {
      to: toInput.value,
      cc: ccInput.value || undefined,
      bcc: bccInput.value || undefined,
      subject: subject.value,
      body: body.value,
    });
    emit('sent', sent?.email_sent_at ? { email_sent_at: sent.email_sent_at } : {});
    close();
  } catch (e: unknown) {
    error.value = extractError(e);
  } finally {
    sending.value = false;
  }
}

function close() {
  emit('close');
}

watch(
  () => [props.open, props.documentId, props.ephemeralSnapshot, props.bridgeCompanyId] as const,
  ([isOpen]) => {
    if (isOpen) {
      loadPreview();
    }
  }
);
</script>

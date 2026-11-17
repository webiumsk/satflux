<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="dialogRoot"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
      @keydown="onDialogKeydown"
      @click.self="skip"
    >
      <div class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
        <button type="button" class="absolute right-3 top-3 rounded px-2 text-2xl text-gray-500" :aria-label="t('common.close')" :disabled="busy" @click="skip">×</button>
        <h3 :id="titleId" class="pr-8 text-lg font-semibold text-gray-900">{{ t('invoicing.expense_attachment_processing_title') }}</h3>
        <p class="mt-3 break-words text-sm leading-relaxed text-gray-600">{{ t('invoicing.expense_attachment_processing_body', { name: fileName }) }}</p>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
          <button type="button" class="invoicing-btn-secondary" :disabled="busy" @click="skip">{{ t('invoicing.expense_attachment_processing_skip') }}</button>
          <button type="button" class="invoicing-btn-primary" :disabled="busy" @click="$emit('confirm')">{{ t('invoicing.expense_attachment_processing_confirm') }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialogFocus } from '../../composables/useDialogFocus';

const props = defineProps<{ open: boolean; fileName: string; busy?: boolean }>();
const emit = defineEmits<{ confirm: []; skip: [] }>();
const { t } = useI18n();
const dialogRoot = ref<HTMLElement | null>(null);
const titleId = useId();
function skip() { if (!props.busy) emit('skip'); }
const { onDialogKeydown } = useDialogFocus(dialogRoot, () => props.open, skip);
</script>

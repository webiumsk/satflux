<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-8 relative">
      <button
        type="button"
        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 text-2xl leading-none"
        :title="t('common.close')"
        @click="$emit('close')"
      >
        ×
      </button>

      <p class="text-center text-gray-800 text-base leading-relaxed mb-8 pr-6">
        {{ t('invoicing.credit_note_link_hint') }}
      </p>

      <div class="flex flex-col sm:flex-row items-stretch justify-center gap-4">
        <button
          type="button"
          class="invoicing-btn-secondary min-w-[12rem] px-6"
          @click="$emit('continue-without')"
        >
          {{ t('invoicing.credit_note_continue_without') }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary min-w-[12rem] px-6 inline-flex items-center justify-center gap-2"
          @click="$emit('add-invoice')"
        >
          <span class="inline-flex w-5 h-5 rounded-full border-2 border-white items-center justify-center text-sm leading-none">+</span>
          {{ t('invoicing.credit_note_add_invoice') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{ open: boolean }>();

defineEmits<{
  close: [];
  'continue-without': [];
  'add-invoice': [];
}>();

const { t } = useI18n();
</script>

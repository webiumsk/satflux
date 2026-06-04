<template>
  <div class="invoicing-card-pad h-full flex flex-col min-h-[420px] relative z-0 isolate">
    <h2 class="invoicing-sf-label mb-1">{{ t('invoicing.expense_attachment') }}</h2>
    <p class="text-xs text-gray-600 mb-3">{{ t('invoicing.expense_attachment_isdoc_hint') }}</p>

    <div
      class="flex-1 rounded-lg border-2 border-dashed transition-colors flex flex-col min-h-[200px]"
      :class="
        dragOver
          ? 'border-indigo-500 bg-indigo-50'
          : 'border-gray-300 bg-gray-50/80 hover:border-gray-400'
      "
      @dragenter.prevent="dragOver = true"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <div v-if="!hasFile" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
        <p class="text-sm text-gray-600 mb-3">{{ t('invoicing.expense_attachment_drop_hint') }}</p>
        <label class="invoicing-btn-secondary cursor-pointer">
          {{ detecting ? t('common.loading') : t('invoicing.expense_attachment_choose') }}
          <input
            type="file"
            class="hidden"
            accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.isdoc,application/pdf,image/*,application/xml,text/xml"
            @change="onInputChange"
          />
        </label>
      </div>

      <div v-else class="flex-1 flex flex-col min-h-0">
        <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-200 bg-white rounded-t-lg">
          <span class="text-sm text-gray-800 truncate" :title="fileName">{{ fileName }}</span>
          <button
            type="button"
            class="text-xs text-gray-500 hover:text-red-600 shrink-0"
            @click="$emit('clear')"
          >
            {{ t('invoicing.expense_attachment_remove') }}
          </button>
        </div>

        <div class="flex-1 min-h-[280px] bg-gray-100 relative z-0 isolate overflow-hidden">
          <div v-if="detecting" class="absolute inset-0 flex items-center justify-center bg-white/70 z-10">
            <span class="text-sm text-gray-600">{{ t('invoicing.expense_attachment_scanning') }}</span>
          </div>
          <iframe
            v-if="previewKind === 'pdf' && previewUrl"
            :src="previewUrl"
            class="w-full h-full min-h-[280px] border-0 relative z-0"
            title="PDF preview"
          />
          <img
            v-else-if="previewKind === 'image' && previewUrl"
            :src="previewUrl"
            alt=""
            class="w-full h-full object-contain max-h-[480px]"
          />
          <div v-else class="p-4 text-sm text-gray-600 text-center">
            {{ t('invoicing.expense_preview_file_attached') }}
          </div>
        </div>
      </div>
    </div>

    <p v-if="detectError" class="mt-2 text-sm text-amber-700">{{ detectError }}</p>
    <p v-else-if="hasFile && !hasIsdoc && !detecting" class="mt-2 text-xs text-gray-500">
      {{ t('invoicing.expense_no_isdoc_in_file') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
  fileName: string;
  hasFile: boolean;
  previewUrl: string | null;
  previewKind: 'pdf' | 'image' | 'other' | null;
  detecting: boolean;
  detectError: string;
  hasIsdoc: boolean;
}>();

const emit = defineEmits<{
  'file-selected': [file: File];
  clear: [];
}>();

const { t } = useI18n();
const dragOver = ref(false);

function onInputChange(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (file) emit('file-selected', file);
}

function onDrop(ev: DragEvent) {
  dragOver.value = false;
  const file = ev.dataTransfer?.files?.[0];
  if (file) emit('file-selected', file);
}

function onDragLeave(ev: DragEvent) {
  const related = ev.relatedTarget as Node | null;
  const current = ev.currentTarget as HTMLElement;
  if (related && current.contains(related)) return;
  dragOver.value = false;
}
</script>

<template>
  <div
    class="flex flex-col relative z-0 isolate"
    :class="panelRootClass"
  >
    <template v-if="!embedded">
      <h2 class="invoicing-sf-label mb-1">{{ t('invoicing.expense_attachment') }}</h2>
      <p class="text-xs text-gray-600 mb-3">{{ t('invoicing.expense_attachment_isdoc_hint') }}</p>
    </template>

    <div
      class="flex flex-col flex-1 min-h-0 transition-colors"
      :class="dropZoneClass"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent="onDragEnter"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <div v-if="!hasAnyFile" class="flex-1 flex flex-col items-center justify-center p-6 text-center min-h-[200px]">
        <svg class="w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="text-sm text-gray-600 mb-3">
          {{ allowMultiple ? t('invoicing.expense_attachment_drop_hint_multiple') : t('invoicing.expense_attachment_drop_hint') }}
        </p>
        <label class="invoicing-btn-secondary cursor-pointer">
          {{ detecting ? t('common.loading') : t('invoicing.expense_attachment_choose') }}
          <input
            ref="fileInputRef"
            type="file"
            class="hidden"
            :multiple="allowMultiple"
            accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.isdoc,application/pdf,image/*,application/xml,text/xml"
            @change="onInputChange"
          />
        </label>
      </div>

      <div v-else class="flex-1 flex flex-col min-h-0">
        <div
          v-if="savedAttachments.length > 0 || pendingFiles.length > 0"
          class="shrink-0 border-b border-gray-200 bg-gray-50 max-h-32 overflow-y-auto"
        >
          <ul class="divide-y divide-gray-100">
            <li
              v-for="att in savedAttachments"
              :key="`saved-${att.id}`"
              class="flex items-center gap-1 px-2 py-1.5 text-sm"
              :class="att.id === selectedAttachmentId ? 'bg-indigo-50' : 'hover:bg-white'"
            >
              <button
                type="button"
                class="flex-1 min-w-0 text-left truncate text-gray-800"
                :title="att.original_filename || ''"
                @click="$emit('select-attachment', att.id)"
              >
                {{ att.original_filename || t('invoicing.expense_attachment_unnamed') }}
              </button>
              <button
                type="button"
                class="p-1 text-gray-500 hover:text-red-600 rounded hover:bg-gray-100 shrink-0"
                :title="t('invoicing.expense_attachment_remove')"
                @click="$emit('remove-saved', att.id)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </li>
            <li
              v-for="pf in pendingFiles"
              :key="`pending-${pf.id}`"
              class="flex items-center gap-1 px-2 py-1.5 text-sm"
              :class="pf.id === selectedPendingId ? 'bg-indigo-50' : 'hover:bg-white'"
            >
              <button
                type="button"
                class="flex-1 min-w-0 text-left truncate text-gray-800"
                :title="pf.name"
                @click="$emit('select-pending', pf.id)"
              >
                <span class="text-indigo-600 mr-1">{{ t('invoicing.expense_attachment_pending_badge') }}</span>
                {{ pf.name }}
              </button>
              <button
                type="button"
                class="p-1 text-gray-500 hover:text-red-600 rounded hover:bg-gray-100 shrink-0"
                :title="t('invoicing.expense_attachment_remove')"
                @click="$emit('remove-pending', pf.id)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </li>
          </ul>
        </div>

        <div
          class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-200 bg-white shrink-0"
          :class="embedded ? '' : 'rounded-t-lg'"
        >
          <span class="text-sm text-gray-800 truncate" :title="fileName">{{ fileName }}</span>
          <div class="flex items-center gap-1 shrink-0">
            <button
              v-if="embedded && showingSaved"
              type="button"
              class="p-1.5 text-gray-500 hover:text-indigo-700 rounded hover:bg-gray-100"
              :title="t('invoicing.expense_attachment_view')"
              @click="$emit('download')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </button>
            <label
              v-if="allowMultiple"
              class="p-1.5 text-gray-500 hover:text-indigo-700 rounded hover:bg-gray-100 cursor-pointer"
              :title="t('invoicing.expense_attachment_add_more')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <input
                ref="addMoreInputRef"
                type="file"
                class="hidden"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.isdoc,application/pdf,image/*,application/xml,text/xml"
                @change="onInputChange"
              />
            </label>
          </div>
        </div>

        <div class="flex-1 min-h-0 bg-gray-100 relative z-0 isolate overflow-hidden">
          <div v-if="detecting" class="absolute inset-0 flex items-center justify-center bg-white/70 z-10">
            <span class="text-sm text-gray-600">{{ t('invoicing.expense_attachment_scanning') }}</span>
          </div>
          <iframe
            v-if="previewKind === 'pdf' && previewUrl"
            :src="previewUrl"
            class="absolute inset-0 w-full h-full border-0"
            title="PDF preview"
          />
          <img
            v-else-if="previewKind === 'image' && previewUrl"
            :src="previewUrl"
            alt=""
            class="absolute inset-0 w-full h-full object-contain"
          />
          <div v-else class="absolute inset-0 flex items-center justify-center p-4 text-sm text-gray-600 text-center">
            {{ t('invoicing.expense_preview_file_attached') }}
          </div>
        </div>
      </div>
    </div>

    <p v-if="detectError" class="mt-2 text-sm text-amber-700 shrink-0 px-1">{{ detectError }}</p>
    <p v-else-if="hasAnyFile && !hasIsdoc && !detecting && hasPending" class="mt-2 text-xs text-gray-500 shrink-0 px-1">
      {{ t('invoicing.expense_no_isdoc_in_file') }}
    </p>
    <p v-if="allowMultiple && pendingFiles.length > 1" class="mt-2 text-xs text-gray-500 shrink-0 px-1">
      {{ t('invoicing.expense_attachment_pending_count', { count: pendingFiles.length }) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export type SavedExpenseAttachment = {
  id: string;
  original_filename: string | null;
};

export type PendingExpenseAttachment = {
  id: string;
  name: string;
};

const props = withDefaults(
  defineProps<{
    fileName: string;
    hasFile: boolean;
    previewUrl: string | null;
    previewKind: 'pdf' | 'image' | 'other' | null;
    detecting: boolean;
    detectError: string;
    hasIsdoc: boolean;
    embedded?: boolean;
    fillHeight?: boolean;
    savedAttachments?: SavedExpenseAttachment[];
    pendingFiles?: PendingExpenseAttachment[];
    selectedAttachmentId?: string | null;
    selectedPendingId?: string | null;
    hasPending?: boolean;
    allowMultiple?: boolean;
  }>(),
  {
    embedded: false,
    fillHeight: false,
    savedAttachments: () => [],
    pendingFiles: () => [],
    selectedAttachmentId: null,
    selectedPendingId: null,
    hasPending: false,
    allowMultiple: true,
  },
);

const emit = defineEmits<{
  'file-selected': [file: File];
  'files-selected': [files: File[]];
  clear: [];
  download: [];
  'select-attachment': [id: string];
  'remove-saved': [id: string];
  'select-pending': [id: string];
  'remove-pending': [id: string];
}>();

const { t } = useI18n();
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const addMoreInputRef = ref<HTMLInputElement | null>(null);

const hasAnyFile = computed(
  () => props.hasFile || props.savedAttachments.length > 0 || props.pendingFiles.length > 0,
);

const showingSaved = computed(
  () => !props.hasPending && props.savedAttachments.length > 0,
);

const panelRootClass = computed(() => {
  if (props.embedded && props.fillHeight) {
    return 'h-full p-3';
  }
  if (props.embedded) {
    return 'p-4 min-h-[360px]';
  }

  return 'invoicing-card-pad min-h-[420px] h-full';
});

const dropZoneClass = computed(() => {
  const base = hasAnyFile.value && props.embedded
    ? 'rounded-lg border border-gray-200 bg-white overflow-hidden'
    : 'rounded-lg border-2 border-dashed min-h-[200px]';

  const state = dragOver.value
    ? 'border-indigo-500 bg-indigo-50'
    : hasAnyFile.value
      ? ''
      : 'border-gray-300 bg-gray-50/80 hover:border-gray-400';

  return `${base} ${state}`;
});

function emitSelectedFiles(fileList: FileList | null | undefined) {
  const files = fileList ? Array.from(fileList) : [];
  if (files.length === 0) return;
  if (files.length === 1) {
    emit('file-selected', files[0]);
  } else {
    emit('files-selected', files);
  }
}

function onInputChange(ev: Event) {
  const input = ev.target as HTMLInputElement;
  emitSelectedFiles(input.files);
  input.value = '';
}

function onDrop(ev: DragEvent) {
  dragOver.value = false;
  emitSelectedFiles(ev.dataTransfer?.files);
}

function onDragEnter() {
  if (props.allowMultiple || !hasAnyFile.value) {
    dragOver.value = true;
  }
}

function onDragLeave(ev: DragEvent) {
  const related = ev.relatedTarget as Node | null;
  const current = ev.currentTarget as HTMLElement;
  if (related && current.contains(related)) return;
  dragOver.value = false;
}
</script>

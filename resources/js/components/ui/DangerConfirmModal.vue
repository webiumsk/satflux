<template>
  <div
    v-if="open"
    ref="dialogRef"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    :class="variant === 'dark' ? 'bg-gray-900/90 backdrop-blur-sm' : 'bg-black/50'"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="titleId"
    :aria-describedby="bodyId"
    @click.self="!busy && $emit('close')"
    @keydown="onKeydown"
  >
    <div
      class="w-full max-w-md rounded-lg shadow-xl p-5 space-y-4"
      :class="variant === 'dark' ? 'bg-gray-800 border border-gray-700' : 'bg-white'"
    >
      <h3 :id="titleId" class="text-lg font-semibold" :class="variant === 'dark' ? 'text-white' : 'text-gray-900'">
        {{ title }}
      </h3>
      <p :id="bodyId" class="text-sm" :class="variant === 'dark' ? 'text-gray-300' : 'text-gray-700'">
        {{ body }}
      </p>
      <ul
        v-if="items?.length"
        class="text-sm list-disc pl-5 space-y-1"
        :class="variant === 'dark' ? 'text-gray-400' : 'text-gray-600'"
      >
        <li v-for="item in items" :key="item">{{ item }}</li>
      </ul>

      <div
        v-if="backupState"
        class="rounded-md p-3 text-sm space-y-1"
        :class="variant === 'dark' ? 'bg-gray-900/60 border border-gray-700' : 'bg-amber-50 border border-amber-200'"
      >
        <p v-if="backupState.legacyPhraseOnDevice" class="text-amber-500 font-medium">
          {{ t("common.danger_legacy_phrase_warning") }}
        </p>
        <p :class="variant === 'dark' ? 'text-gray-400' : 'text-gray-700'">
          {{
            backupState.lastExportAt
              ? t("common.danger_backup_last_export_at", { date: formatDate(backupState.lastExportAt) })
              : t("common.danger_backup_never_exported")
          }}
        </p>
      </div>

      <div v-if="confirmWord">
        <label class="block text-sm font-medium mb-1" :class="variant === 'dark' ? 'text-gray-300' : 'text-gray-700'">
          {{ t("common.danger_type_word_label", { word: confirmWord }) }}
        </label>
        <input
          ref="wordInputRef"
          v-model="typedWord"
          type="text"
          class="w-full rounded-md px-3 py-2 text-sm border"
          :class="
            variant === 'dark'
              ? 'border-gray-600 bg-gray-900/80 text-gray-200'
              : 'border-gray-300 bg-white text-gray-900'
          "
          :aria-label="t('common.danger_type_word_label', { word: confirmWord })"
        />
      </div>

      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

      <div class="flex justify-end gap-3">
        <button
          ref="cancelButtonRef"
          type="button"
          class="px-4 py-2 rounded-md text-sm font-medium border"
          :class="
            variant === 'dark'
              ? 'border-gray-600 text-gray-300 hover:text-white'
              : 'border-gray-300 text-gray-700 hover:bg-gray-50'
          "
          :disabled="busy"
          @click="$emit('close')"
        >
          {{ t("common.cancel") }}
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
          :disabled="busy || !confirmEnabled"
          @click="$emit('confirm')"
        >
          {{ busy ? t("common.loading") : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

/**
 * Shared destructive-action confirmation (P1 data-loss guards).
 *
 * With `confirmWord` set, the user must type the word to enable the action
 * (extracted from the CompanySettingsForm typed-name pattern); without it the
 * modal is a lightweight confirm. `backupState` renders an informational line
 * about the last data export and, when the recovery phrase still sits only in
 * this browser, an explicit warning.
 *
 * Accessibility: the dialog is named/described via aria-labelledby and
 * aria-describedby, focus moves into the modal on open, Tab and Shift+Tab are
 * trapped while it is open, Escape closes it (unless busy), and focus returns
 * to the triggering element on close.
 */
const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    body: string;
    confirmLabel: string;
    items?: string[];
    confirmWord?: string | null;
    busy?: boolean;
    error?: string | null;
    variant?: "light" | "dark";
    backupState?: {
      lastExportAt: string | null;
      legacyPhraseOnDevice: boolean;
    } | null;
  }>(),
  { variant: "light", confirmWord: null, busy: false, error: null, backupState: null, items: undefined },
);

const emit = defineEmits<{ close: []; confirm: [] }>();

const { t, locale } = useI18n();

const instanceId = `danger-confirm-${Math.random().toString(36).slice(2, 10)}`;
const titleId = `${instanceId}-title`;
const bodyId = `${instanceId}-body`;

const typedWord = ref("");
const dialogRef = ref<HTMLElement | null>(null);
const wordInputRef = ref<HTMLInputElement | null>(null);
const cancelButtonRef = ref<HTMLButtonElement | null>(null);
let triggerElement: HTMLElement | null = null;

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      typedWord.value = "";
      triggerElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      await nextTick();
      (wordInputRef.value ?? cancelButtonRef.value)?.focus();
    } else if (triggerElement) {
      triggerElement.focus();
      triggerElement = null;
    }
  },
);

const confirmEnabled = computed(() => {
  if (!props.confirmWord) return true;
  return typedWord.value.trim().toLowerCase() === props.confirmWord.trim().toLowerCase();
});

function focusableElements(): HTMLElement[] {
  if (!dialogRef.value) return [];
  return Array.from(
    dialogRef.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), input:not([disabled]), [href], select, textarea, [tabindex]:not([tabindex="-1"])',
    ),
  );
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === "Escape" && !props.busy) {
    event.preventDefault();
    emit("close");
    return;
  }
  if (event.key !== "Tab") return;
  const elements = focusableElements();
  if (elements.length === 0) return;
  const first = elements[0];
  const last = elements[elements.length - 1];
  const active = document.activeElement;
  if (event.shiftKey && (active === first || !dialogRef.value?.contains(active))) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (active === last || !dialogRef.value?.contains(active))) {
    event.preventDefault();
    first.focus();
  }
}

function formatDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale.value);
}
</script>

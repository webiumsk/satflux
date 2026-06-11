<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity ease-linear duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-linear duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="invoicing-drawer-backdrop fixed inset-0 z-50 bg-black/40"
        aria-hidden="true"
        @click="emit('close')"
      />
    </Transition>

    <Transition
      enter-active-class="transition ease-in-out duration-300 transform"
      :enter-from-class="panelEnterFrom"
      enter-to-class="translate-x-0"
      leave-active-class="transition ease-in-out duration-300 transform"
      leave-from-class="translate-x-0"
      :leave-to-class="panelLeaveTo"
    >
      <div
        v-if="open"
        ref="panelRef"
        class="invoicing-drawer-panel fixed inset-y-0 z-50 flex w-full max-w-sm flex-col bg-white shadow-xl focus:outline-none"
        tabindex="-1"
        :class="side === 'left' ? 'left-0 border-r border-gray-200' : 'right-0 border-l border-gray-200'"
        role="dialog"
        :aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.escape="emit('close')"
      >
        <header class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
          <div class="min-w-0">
            <h2 :id="titleId" class="text-base font-semibold text-gray-900 truncate">
              {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-0.5 text-sm text-gray-500 truncate">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="invoicing-mobile-icon-btn shrink-0"
            :title="t('common.close')"
            @click="emit('close')"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="flex-1 overflow-y-auto overscroll-y-contain px-4 py-4">
          <slot />
        </div>

        <footer v-if="$slots.footer" class="shrink-0 border-t border-gray-200 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <slot name="footer" />
        </footer>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
  defineProps<{
    open: boolean;
    side?: 'left' | 'right';
    title: string;
    subtitle?: string;
  }>(),
  { side: 'right' }
);

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const panelRef = ref<HTMLElement | null>(null);
const titleId = `invoicing-drawer-title-${Math.random().toString(36).slice(2, 9)}`;

const panelEnterFrom = computed(() =>
  props.side === 'left' ? '-translate-x-full' : 'translate-x-full'
);
const panelLeaveTo = computed(() =>
  props.side === 'left' ? '-translate-x-full' : 'translate-x-full'
);

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') {
      return;
    }
    document.body.style.overflow = isOpen ? 'hidden' : '';
    if (isOpen) {
      requestAnimationFrame(() => panelRef.value?.focus());
    }
  }
);
</script>

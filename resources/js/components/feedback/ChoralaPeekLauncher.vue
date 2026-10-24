<template>
  <Teleport to="body">
    <button
      v-if="enabled"
      type="button"
      class="chorala-peek-launcher group fixed left-0 z-40 flex items-center gap-1 rounded-r-full bg-orange-600 py-1.5 pl-2 pr-2.5 text-white shadow-md transition-transform duration-200 ease-out translate-x-[calc(-100%+2rem)] hover:translate-x-0 focus-visible:translate-x-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900 bottom-[calc(4.5rem+env(safe-area-inset-bottom,0px))] md:bottom-6 md:gap-2 md:py-2.5 md:pl-3 md:pr-4 md:shadow-lg md:translate-x-[calc(-100%+2.75rem)]"
      :aria-label="t('common.feedback_open_aria')"
      :aria-busy="opening"
      :disabled="opening"
      @click="handleOpen"
    >
      <svg
        class="h-4 w-4 shrink-0 md:h-5 md:w-5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      <span
        class="hidden whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100 md:inline"
      >
        {{ t('common.feedback_label') }}
      </span>
    </button>
    <div
      v-if="enabled && unavailable"
      role="alert"
      class="fixed bottom-[calc(8rem+env(safe-area-inset-bottom,0px))] left-3 z-50 max-w-xs rounded-xl border border-orange-500/40 bg-gray-900 p-4 text-sm text-white shadow-lg md:bottom-20"
    >
      <p>{{ t('common.feedback_unavailable') }}</p>
      <button
        type="button"
        class="mt-3 rounded px-2 py-1 font-medium text-orange-300 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 disabled:opacity-50"
        :disabled="opening"
        @click="handleOpen"
      >
        {{ t(opening ? 'common.feedback_loading' : 'common.feedback_retry') }}
      </button>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { isChoralaConfigured, openChoralaWidget } from '../../services/chorala';

const { t } = useI18n();
const enabled = ref(false);
const opening = ref(false);
const unavailable = ref(false);

function showUnavailable(): void {
    unavailable.value = true;
}

onMounted(() => {
    enabled.value = isChoralaConfigured();
    window.addEventListener('satflux:feedback-unavailable', showUnavailable);
});

onBeforeUnmount(() => window.removeEventListener('satflux:feedback-unavailable', showUnavailable));

async function handleOpen(): Promise<void> {
    if (opening.value) return;
    opening.value = true;
    unavailable.value = false;
    try {
        await openChoralaWidget();
    } catch {
        unavailable.value = true;
    } finally {
        opening.value = false;
    }
}
</script>

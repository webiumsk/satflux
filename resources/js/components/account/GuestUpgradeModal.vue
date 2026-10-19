<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[200] flex items-center justify-center p-4"
      role="presentation"
    >
      <div
        class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"
        aria-hidden="true"
        @click="closeGuestUpgradeModal"
      />
      <div
        class="relative z-10 w-full max-w-lg max-h-[min(90vh,48rem)] overflow-y-auto rounded-2xl border border-gray-700 bg-gray-800 shadow-xl"
        ref="dialogRoot"
        tabindex="-1"
        @keydown="onDialogKeydown"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="dialogTitleId"
        @click.stop
      >
        <div class="p-6 sm:p-8 space-y-4">
          <div class="flex justify-between items-start gap-4">
            <h3 :id="dialogTitleId" class="text-lg font-bold text-white">
              {{ t('account.guest_upgrade_title') }}
            </h3>
            <button
              type="button"
              class="text-gray-400 hover:text-white text-sm shrink-0"
              @click="closeGuestUpgradeModal"
            >
              {{ t('common.close') }}
            </button>
          </div>

          <GuestUpgradeForm
            :feature-label-key="featureLabelKey ?? undefined"
            :show-default-desc="!featureLabelKey"
            id-prefix="guest-upgrade-modal"
            @success="onUpgradeSuccess"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useDialogFocus } from '../../composables/useDialogFocus';
import { useI18n } from 'vue-i18n';
import GuestUpgradeForm from './GuestUpgradeForm.vue';
import { useGuestUpgradeModal } from '../../composables/useGuestUpgradeModal';

const { t } = useI18n();
const { open, featureLabelKey, closeGuestUpgradeModal } = useGuestUpgradeModal();

const isOpen = computed(() => open.value);
const dialogRoot = ref<HTMLElement | null>(null);
const dialogTitleId = useId();
const { onDialogKeydown } = useDialogFocus(dialogRoot, () => isOpen.value, closeGuestUpgradeModal);

function onUpgradeSuccess(): void {
  // The code was confirmed in place; the store now holds the non-guest user
  // and every guest gate re-evaluates on its own.
  closeGuestUpgradeModal();
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed z-[100] inset-0 overflow-y-auto"
      @click.self="closeGuestUpgradeModal"
    >
      <div
        class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm"
        @click.self="closeGuestUpgradeModal"
      />
      <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div
          class="relative w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-800 shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="guest-upgrade-modal-title"
        >
          <div class="p-6 sm:p-8 space-y-4">
            <div class="flex justify-between items-start gap-4">
              <h3 id="guest-upgrade-modal-title" class="text-lg font-bold text-white">
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
              :key="formInstanceKey"
              :feature-label-key="resolvedFeatureLabelKey"
              :show-default-desc="!resolvedFeatureLabelKey"
              id-prefix="guest-upgrade-modal"
              @success="closeGuestUpgradeModal"
            />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import GuestUpgradeForm from './GuestUpgradeForm.vue';
import { useGuestUpgradeModal } from '../../composables/useGuestUpgradeModal';

const { t } = useI18n();
const { open, featureLabelKey, closeGuestUpgradeModal } = useGuestUpgradeModal();

const resolvedFeatureLabelKey = computed(() => featureLabelKey.value ?? null);

const formInstanceKey = ref(0);

watch(open, (isOpen) => {
  if (isOpen) {
    formInstanceKey.value += 1;
  }
});
</script>

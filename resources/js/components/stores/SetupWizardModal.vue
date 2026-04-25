<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-black/70 backdrop-blur-sm"
        @click="emit('close')"
      />

      <!-- Modal -->
      <div
        class="relative w-full max-w-lg bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-indigo-500/20 flex items-center justify-center">
              <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-bold text-white">{{ t('setup_wizard.title') }}</h2>
              <p class="text-xs text-gray-400">{{ store?.name }}</p>
            </div>
          </div>
          <button
            type="button"
            class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
            :aria-label="t('common.close')"
            @click="emit('close')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Step indicator -->
        <div class="flex px-6 pt-4 gap-2">
          <button
            v-for="(step, idx) in steps"
            :key="idx"
            type="button"
            class="flex-1 h-1.5 rounded-full transition-colors"
            :class="currentStep === idx ? 'bg-indigo-500' : completedSteps.includes(idx) ? 'bg-indigo-500/50' : 'bg-gray-700'"
            :aria-label="step.titleKey ? t(step.titleKey) : ''"
            @click="currentStep = idx"
          />
        </div>

        <!-- Content -->
        <div class="px-6 py-6 min-h-[280px]">
          <!-- Step 1: Wallet -->
          <div v-show="currentStep === 0" class="space-y-4">
            <h3 class="text-base font-bold text-white">{{ t('setup_wizard.step_wallet_title') }}</h3>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_wallet_desc') }}</p>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_wallet_why') }}</p>
            <div class="flex flex-wrap gap-3 pt-2">
              <router-link
                :to="`/stores/${storeId}/wallet-connection`"
                class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
                @click="emit('close')"
              >
                {{ t('setup_wizard.go_wallet_connection') }}
              </router-link>
              <router-link
                :to="`/stores/${storeId}/checklist`"
                class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-gray-300 bg-gray-700 hover:bg-gray-600 transition-colors"
                @click="emit('close')"
              >
                {{ t('next_steps.view_onboarding_checklist') }}
              </router-link>
            </div>
          </div>

          <!-- Step 2: PoS -->
          <div v-show="currentStep === 1" class="space-y-4">
            <h3 class="text-base font-bold text-white">{{ t('setup_wizard.step_pos_title') }}</h3>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_pos_desc') }}</p>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_pos_why') }}</p>
            <router-link
              :to="{ name: 'stores-apps-create', params: { id: storeId }, query: { type: 'PointOfSale' } }"
              class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors mt-2"
              @click="emit('close')"
            >
              {{ t('setup_wizard.create_pos') }}
            </router-link>
          </div>

          <!-- Step 3: LN Address -->
          <div v-show="currentStep === 2" class="space-y-4">
            <h3 class="text-base font-bold text-white">{{ t('setup_wizard.step_ln_address_title') }}</h3>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_ln_address_desc') }}</p>
            <p class="text-sm text-gray-400 leading-relaxed">{{ t('setup_wizard.step_ln_address_why') }}</p>
            <router-link
              :to="`/stores/${storeId}/lightning-addresses`"
              class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors mt-2"
              @click="emit('close')"
            >
              {{ t('setup_wizard.manage_ln_addresses') }}
            </router-link>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-700 bg-gray-900/50">
          <button
            v-if="currentStep > 0"
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors"
            @click="currentStep--"
          >
            {{ t('common.previous') }}
          </button>
          <div v-else />
          <div class="flex items-center gap-2">
            <button
              v-if="currentStep < steps.length - 1"
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
              @click="currentStep++"
            >
              {{ t('common.next') }}
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white transition-colors"
              @click="emit('close')"
            >
              {{ t('common.close') }}
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-3 text-center">
            <button type="button" class="hover:text-indigo-400 transition-colors" @click="emit('restart-pos-tour')">
              {{ t('onboarding.restart_tour') }}
            </button>
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
  storeId: string;
  store: { id: string; name: string; wallet_type?: string | null } | null;
}>();

const emit = defineEmits<{
  close: [];
  'restart-pos-tour': [];
}>();

const steps = [
  { titleKey: 'setup_wizard.step_wallet_title' },
  { titleKey: 'setup_wizard.step_pos_title' },
  { titleKey: 'setup_wizard.step_ln_address_title' },
];

const currentStep = ref(0);

const completedSteps = computed(() => {
  const completed: number[] = [];
  for (let i = 0; i < currentStep.value; i++) completed.push(i);
  return completed;
});
</script>

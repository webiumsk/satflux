<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import WalletTypeIcon from '../WalletTypeIcon.vue';
import {
  detectWalletConnectionInput,
  detectionLabelKey,
  type DetectedWalletKind,
} from '../../utils/detectWalletConnectionInput';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    connectionType: 'blink' | 'nwc' | 'aqua_descriptor';
    inputId?: string;
    showTypeOverride?: boolean;
  }>(),
  {
    inputId: 'wallet-connection-smart-paste',
    showTypeOverride: true,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  'update:connectionType': [value: 'blink' | 'nwc' | 'aqua_descriptor'];
  'detect-cashu': [payload: { mintUrl: string; lightningAddress: string | null }];
}>();

const { t } = useI18n();
const manualType = ref<'blink' | 'nwc' | 'aqua_descriptor' | null>(null);

const detection = computed(() => detectWalletConnectionInput(props.modelValue));

const effectiveKind = computed((): DetectedWalletKind => {
  if (manualType.value) {
    if (manualType.value === 'nwc') return 'nwc';
    if (manualType.value === 'blink') return 'blink';
    return 'aqua_descriptor';
  }
  return detection.value.kind;
});

const detectedLabel = computed(() => {
  if (effectiveKind.value === 'unknown') {
    return t('stores.wallet_detect_unknown');
  }
  const brand =
    effectiveKind.value === 'aqua_descriptor' ? detection.value.brand : null;
  return t(detectionLabelKey(effectiveKind.value, brand, detection.value));
});

watch(
  () => props.modelValue,
  (value) => {
    const result = detectWalletConnectionInput(value);
    if (result.kind === 'cashu' || result.kind === 'cashu_wallet_nwc') {
      emit('detect-cashu', {
        mintUrl: result.cashuMintUrl ?? '',
        lightningAddress: result.cashuLightningAddress,
      });
      return;
    }
    if (result.confidence === 'high' && result.connectionType) {
      manualType.value = null;
      if (result.connectionType !== props.connectionType) {
        emit('update:connectionType', result.connectionType);
      }
    }
  },
);

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}

function setManualType(type: 'blink' | 'nwc' | 'aqua_descriptor') {
  manualType.value = type;
  emit('update:connectionType', type);
}
</script>

<template>
  <div class="space-y-4">
    <label
      :for="inputId"
      class="block text-sm font-medium text-indigo-300 mb-2 uppercase tracking-wider"
    >
      {{ t('stores.wallet_smart_paste_label') }}
    </label>
    <textarea
      :id="inputId"
      :value="modelValue"
      rows="6"
      class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono p-4"
      :placeholder="t('stores.wallet_smart_paste_placeholder')"
      required
      @input="onInput"
    />

    <div
      v-if="modelValue.trim()"
      class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-700 bg-gray-800/60 px-4 py-3"
    >
      <span class="text-xs uppercase tracking-wider text-gray-500">
        {{ t('stores.wallet_detected_label') }}
      </span>
      <span
        class="inline-flex items-center gap-2 rounded-lg px-3 py-1 text-sm font-medium"
        :class="
          effectiveKind === 'unknown' || effectiveKind === 'cashu_wallet_nwc'
            ? 'bg-amber-500/15 text-amber-200 border border-amber-500/30'
            : effectiveKind === 'cashu'
              ? 'bg-amber-500/15 text-amber-200 border border-amber-500/30'
              : 'bg-indigo-500/15 text-indigo-100 border border-indigo-500/30'
        "
      >
        <WalletTypeIcon
          v-if="effectiveKind !== 'unknown'"
          :type="
            effectiveKind === 'aqua_descriptor'
              ? 'aqua_boltz'
              : effectiveKind === 'nwc'
                ? 'nwc'
                : effectiveKind === 'cashu'
                  ? 'cashu'
                  : 'blink'
          "
          :brand="detection.brand ?? undefined"
          size="sm"
        />
        {{ detectedLabel }}
      </span>
    </div>

    <p class="text-sm text-gray-400">
      {{ t('stores.wallet_smart_paste_hint') }}
    </p>

    <div
      v-if="showTypeOverride && effectiveKind === 'unknown' && modelValue.trim()"
      class="flex flex-wrap gap-2"
    >
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-600 text-gray-300 hover:border-indigo-500/50"
        @click="setManualType('nwc')"
      >
        {{ t('stores.wallet_detect_nwc') }}
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-600 text-gray-300 hover:border-indigo-500/50"
        @click="setManualType('aqua_descriptor')"
      >
        {{ t('stores.wallet_detect_aqua') }}
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-600 text-gray-300 hover:border-indigo-500/50"
        @click="setManualType('blink')"
      >
        {{ t('stores.wallet_detect_blink') }}
      </button>
    </div>

    <div
      v-if="effectiveKind === 'cashu_wallet_nwc'"
      class="p-4 rounded-xl border border-red-500/40 bg-red-500/10 space-y-2"
    >
      <p class="text-sm text-red-200 leading-relaxed">
        {{ t('stores.wallet_cashu_nwc_rejected') }}
      </p>
      <p
        v-if="detection.cashuLightningAddress"
        class="text-sm text-red-100/90"
      >
        {{ t('stores.wallet_cashu_nwc_use_ln_address', { address: detection.cashuLightningAddress }) }}
      </p>
    </div>

    <div
      v-if="effectiveKind === 'blink'"
      class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10"
    >
      <p class="text-sm text-amber-300">
        {{ t('stores.blink_eu_legacy_notice') }}
      </p>
    </div>

    <div
      v-if="effectiveKind === 'nwc'"
      class="p-4 rounded-xl border border-indigo-500/30 bg-indigo-500/10 text-sm text-indigo-100 space-y-2"
    >
      <p class="font-medium text-amber-200/95">{{ t('stores.wallet_guide_nwc_alby_requirement') }}</p>
      <p>{{ t('stores.nwc_connection_hint') }}</p>
    </div>

    <div
      v-if="effectiveKind === 'aqua_descriptor'"
      class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10"
    >
      <p class="text-sm text-amber-400">{{ t('stores.aqua_warning_btcpay') }}</p>
    </div>

    <div
      v-if="effectiveKind === 'cashu'"
      class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 text-sm text-amber-200/95"
    >
      {{ t('stores.cashu_beta_notice_short') }}
    </div>
  </div>
</template>

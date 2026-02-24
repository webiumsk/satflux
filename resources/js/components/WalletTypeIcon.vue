<template>
  <component
    :is="tag"
    v-if="iconSrc"
    class="inline-flex items-center gap-1.5"
    :class="sizeClass"
  >
    <img
      :src="iconSrc"
      :alt="altText"
      :title="altText"
      class="object-contain flex-shrink-0"
      :class="imgSizeClass"
    />
    <span v-if="showLabel" class="font-medium" :class="labelClass">
      {{ labelText }}
    </span>
  </component>
  <span v-else-if="showLabel && fallbackText" class="font-medium text-gray-500">
    {{ fallbackText }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
  defineProps<{
    /** store.wallet_type: 'blink' | 'aqua_boltz' | null, or connection.type: 'blink' | 'aqua_descriptor' */
    type: 'blink' | 'aqua_boltz' | 'aqua_descriptor' | null | undefined;
    size?: 'sm' | 'md' | 'lg';
    showLabel?: boolean;
    fallbackText?: string;
    tag?: string;
  }>(),
  {
    size: 'md',
    showLabel: false,
    tag: 'span',
  }
);

const { t } = useI18n();

const iconSrc = computed(() => {
  if (!props.type) return null;
  if (props.type === 'blink') return '/img/wallets/blink-64.webp';
  if (props.type === 'aqua_boltz' || props.type === 'aqua_descriptor') return '/img/wallets/aqua-64.webp';
  return null;
});

const altText = computed(() => {
  if (!props.type) return props.fallbackText ?? '';
  if (props.type === 'blink') return t('create_store.wallet_type_blink');
  return t('create_store.wallet_type_aqua');
});

const labelText = computed(() => {
  if (!props.type) return props.fallbackText ?? '';
  if (props.type === 'blink') return t('create_store.wallet_type_blink');
  return t('create_store.wallet_type_aqua');
});

const imgSizeClass = computed(() => {
  switch (props.size) {
    case 'sm': return 'w-5 h-5';
    case 'lg': return 'w-10 h-6';
    default: return 'w-6 h-6';
  }
});

const sizeClass = computed(() => {
  switch (props.size) {
    case 'sm': return 'text-sm';
    case 'lg': return 'text-base';
    default: return 'text-sm';
  }
});

const labelClass = computed(() => {
  return props.tag === 'span' ? 'text-inherit' : '';
});
</script>

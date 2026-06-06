<template>
  <div class="space-y-3">
    <label
      v-if="showTerms"
      class="flex items-start gap-3 cursor-pointer text-sm text-gray-300"
    >
      <input
        :id="`${idPrefix}-terms`"
        v-model="termsModel"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-600 focus:ring-indigo-500"
        :required="required"
      />
      <span>
        <i18n-t keypath="legal.consent.terms" tag="span">
          <template #termsLink>
            <router-link
              to="/legal/terms"
              class="text-indigo-400 hover:text-indigo-300 underline"
              target="_blank"
            >{{ t('legal.nav.terms') }}</router-link>
          </template>
        </i18n-t>
      </span>
    </label>

    <label class="flex items-start gap-3 cursor-pointer text-sm text-gray-300">
      <input
        :id="`${idPrefix}-privacy`"
        v-model="privacyModel"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-600 focus:ring-indigo-500"
        :required="required"
      />
      <span>
        <i18n-t keypath="legal.consent.privacy" tag="span">
          <template #privacyLink>
            <router-link
              to="/legal/privacy"
              class="text-indigo-400 hover:text-indigo-300 underline"
              target="_blank"
            >{{ t('legal.nav.privacy') }}</router-link>
          </template>
        </i18n-t>
      </span>
    </label>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
  defineProps<{
    privacyConsent: boolean;
    termsAccepted?: boolean;
    showTerms?: boolean;
    required?: boolean;
    idPrefix?: string;
  }>(),
  {
    termsAccepted: false,
    showTerms: true,
    required: true,
    idPrefix: 'legal-consent',
  },
);

const emit = defineEmits<{
  'update:privacyConsent': [value: boolean];
  'update:termsAccepted': [value: boolean];
}>();

const { t } = useI18n();

const privacyModel = computed({
  get: () => props.privacyConsent,
  set: (value: boolean) => emit('update:privacyConsent', value),
});

const termsModel = computed({
  get: () => props.termsAccepted,
  set: (value: boolean) => emit('update:termsAccepted', value),
});
</script>

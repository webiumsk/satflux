<template>
  <form class="space-y-3" @submit.prevent="onSubmit">
    <p v-if="introFeatureLabel" class="text-sm text-gray-300">
      {{ t('account.guest_upgrade_modal_intro', { feature: introFeatureLabel }) }}
    </p>
    <p v-else-if="showDefaultDesc" class="text-xs text-gray-300">
      {{ t('account.guest_upgrade_desc_email_only') }}
    </p>
    <input
      v-model="form.email"
      type="email"
      required
      class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      :placeholder="t('account.guest_upgrade_email_placeholder')"
    />
    <input
      v-model="form.password"
      type="password"
      required
      class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      :placeholder="t('account.new_password')"
    />
    <input
      v-model="form.password_confirmation"
      type="password"
      required
      class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      :placeholder="t('account.confirm_new_password')"
    />
    <p
      class="text-xs text-amber-200/90 leading-relaxed rounded-lg border border-amber-500/25 bg-amber-500/10 px-3 py-2"
      role="note"
    >
      {{ t('account.guest_upgrade_email_verify_notice') }}
    </p>
    <LegalConsentFields
      v-model:privacy-consent="privacyConsent"
      v-model:terms-accepted="termsAccepted"
      :id-prefix="idPrefix"
    />
    <button
      type="submit"
      :disabled="loading || !canSubmit"
      class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50"
    >
      {{ loading ? t('auth.saving') : t('account.guest_upgrade_email_submit') }}
    </button>
  </form>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LegalConsentFields from '../legal/LegalConsentFields.vue';
import { useGuestUpgradeSubmit } from '../../composables/useGuestUpgradeSubmit';

const props = withDefaults(
  defineProps<{
    featureLabelKey?: string | null;
    showDefaultDesc?: boolean;
    idPrefix?: string;
  }>(),
  {
    featureLabelKey: null,
    showDefaultDesc: true,
    idPrefix: 'guest-upgrade',
  },
);

const emit = defineEmits<{
  success: [];
}>();

const { t } = useI18n();
const {
  loading,
  privacyConsent,
  termsAccepted,
  form,
  canSubmit,
  submit,
} = useGuestUpgradeSubmit();

const introFeatureLabel = computed(() =>
  props.featureLabelKey ? t(props.featureLabelKey) : null,
);

async function onSubmit() {
  const ok = await submit();
  if (ok) {
    emit('success');
  }
}
</script>

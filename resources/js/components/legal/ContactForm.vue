<template>
  <form class="space-y-4" @submit.prevent="submit">
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label :for="`${idPrefix}-name`" class="block text-sm font-medium text-gray-300 mb-1">
          {{ t('legal.contact.name') }}
        </label>
        <input
          :id="`${idPrefix}-name`"
          v-model="form.name"
          type="text"
          required
          maxlength="120"
          class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>
      <div>
        <label :for="`${idPrefix}-email`" class="block text-sm font-medium text-gray-300 mb-1">
          {{ t('legal.contact.email') }}
        </label>
        <input
          :id="`${idPrefix}-email`"
          v-model="form.email"
          type="email"
          required
          maxlength="255"
          autocomplete="email"
          class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>
    </div>

    <div v-if="showSubject">
      <label :for="`${idPrefix}-subject`" class="block text-sm font-medium text-gray-300 mb-1">
        {{ t('legal.contact.subject') }}
      </label>
      <input
        :id="`${idPrefix}-subject`"
        v-model="form.subject"
        type="text"
        maxlength="200"
        class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
      />
    </div>

    <div>
      <label :for="`${idPrefix}-message`" class="block text-sm font-medium text-gray-300 mb-1">
        {{ t('legal.contact.message') }}
      </label>
      <textarea
        :id="`${idPrefix}-message`"
        v-model="form.message"
        required
        rows="5"
        maxlength="5000"
        class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
      />
    </div>

    <LegalConsentFields
      v-model:privacy-consent="privacyConsent"
      :show-terms="false"
      :id-prefix="`${idPrefix}-consent`"
    />

    <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
    <p v-if="success" class="text-sm text-green-400">{{ success }}</p>

    <button
      type="submit"
      :disabled="loading || !privacyConsent"
      class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 transition-colors"
    >
      {{ loading ? t('legal.contact.sending') : t('legal.contact.submit') }}
    </button>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';
import LegalConsentFields from './LegalConsentFields.vue';

const props = withDefaults(
  defineProps<{
    type: 'enterprise' | 'support' | 'general';
    showSubject?: boolean;
    idPrefix?: string;
    defaultSubject?: string;
  }>(),
  {
    showSubject: true,
    idPrefix: 'contact',
    defaultSubject: '',
  },
);

const { t, locale } = useI18n();

const form = reactive({
  name: '',
  email: '',
  subject: props.defaultSubject,
  message: '',
});

const privacyConsent = ref(false);
const loading = ref(false);
const error = ref('');
const success = ref('');

async function submit() {
  if (!privacyConsent.value) return;
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await api.post('/contact', {
      type: props.type,
      name: form.name.trim(),
      email: form.email.trim(),
      subject: form.subject.trim() || null,
      message: form.message.trim(),
      privacy_consent: true,
      locale: locale.value,
    });
    success.value = response.data?.message || t('legal.contact.success');
    form.name = '';
    form.email = '';
    form.subject = props.defaultSubject;
    form.message = '';
    privacyConsent.value = false;
  } catch (err: unknown) {
    const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
    const errors = axiosErr.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = axiosErr.response?.data?.message || t('legal.contact.error');
    }
  } finally {
    loading.value = false;
  }
}
</script>

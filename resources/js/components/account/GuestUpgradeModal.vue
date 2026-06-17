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
        role="dialog"
        aria-modal="true"
        aria-labelledby="guest-upgrade-modal-title"
        @click.stop
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

          <form class="space-y-3" @submit.prevent="onSubmit">
            <p v-if="featureLabel" class="text-sm text-gray-300">
              {{ t('account.guest_upgrade_modal_intro', { feature: featureLabel }) }}
            </p>
            <p v-else class="text-xs text-gray-300">
              {{ t('account.guest_upgrade_desc_email_only') }}
            </p>

            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :placeholder="t('account.guest_upgrade_email_placeholder')"
            />
            <input
              v-model="password"
              type="password"
              required
              autocomplete="new-password"
              class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :placeholder="t('account.new_password')"
            />
            <input
              v-model="passwordConfirmation"
              type="password"
              required
              autocomplete="new-password"
              class="w-full px-3 py-2 border border-gray-600 rounded-lg text-white bg-gray-900/70 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :placeholder="t('account.confirm_new_password')"
            />

            <p
              class="text-xs text-amber-200/90 leading-relaxed rounded-lg border border-amber-500/25 bg-amber-500/10 px-3 py-2"
              role="note"
            >
              {{ t('account.guest_upgrade_email_verify_notice') }}
            </p>

            <label class="flex items-start gap-3 cursor-pointer text-sm text-gray-300">
              <input
                id="guest-upgrade-modal-terms"
                v-model="termsAccepted"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-600 focus:ring-indigo-500"
                required
              />
              <span>
                <i18n-t keypath="legal.consent.terms" tag="span">
                  <template #termsLink>
                    <a
                      href="/legal/terms"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-indigo-400 hover:text-indigo-300 underline"
                    >{{ t('legal.nav.terms') }}</a>
                  </template>
                </i18n-t>
              </span>
            </label>

            <label class="flex items-start gap-3 cursor-pointer text-sm text-gray-300">
              <input
                id="guest-upgrade-modal-privacy"
                v-model="privacyConsent"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-600 focus:ring-indigo-500"
                required
              />
              <span>
                <i18n-t keypath="legal.consent.privacy" tag="span">
                  <template #privacyLink>
                    <a
                      href="/legal/privacy"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-indigo-400 hover:text-indigo-300 underline"
                    >{{ t('legal.nav.privacy') }}</a>
                  </template>
                </i18n-t>
              </span>
            </label>

            <button
              type="submit"
              :disabled="loading || !canSubmit"
              class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50"
            >
              {{ loading ? t('auth.saving') : t('account.guest_upgrade_email_submit') }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import { useFlashStore } from '../../store/flash';
import api from '../../services/api';
import { useGuestUpgradeModal } from '../../composables/useGuestUpgradeModal';

function redirectToEmailVerification(): void {
  window.location.assign('/account/check-email');
}

const { t, te } = useI18n();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const { open, featureLabelKey, closeGuestUpgradeModal } = useGuestUpgradeModal();

const isOpen = computed(() => open.value);
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const privacyConsent = ref(false);
const termsAccepted = ref(false);
const loading = ref(false);

const canSubmit = computed(() => privacyConsent.value && termsAccepted.value);

const featureLabel = computed(() => {
  const key = featureLabelKey.value;
  if (!key) return null;
  return te(key) ? t(key) : key;
});

function resetForm() {
  email.value = '';
  password.value = '';
  passwordConfirmation.value = '';
  privacyConsent.value = false;
  termsAccepted.value = false;
  loading.value = false;
}

watch(isOpen, (visible) => {
  if (visible) {
    resetForm();
  }
});

async function onSubmit() {
  if (!canSubmit.value || loading.value) return;
  loading.value = true;
  try {
    const response = await api.put('/user/guest/upgrade', {
      method: 'email',
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      privacy_consent: privacyConsent.value,
      terms_accepted: termsAccepted.value,
    });
    if (response?.data?.user) {
      authStore.user = response.data.user;
    } else {
      await authStore.fetchUser();
    }
    closeGuestUpgradeModal();
    redirectToEmailVerification();
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } };
    flashStore.error(
      err?.response?.data?.message || t('account.guest_upgrade_failed'),
    );
  } finally {
    loading.value = false;
  }
}
</script>

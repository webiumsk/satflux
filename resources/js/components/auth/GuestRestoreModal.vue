<template>
  <div
    v-if="open"
    class="fixed z-50 inset-0 overflow-y-auto"
    @click.self="$emit('close')"
  >
    <div class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm" @click.self="$emit('close')" />
    <div class="flex min-h-full items-center justify-center p-4">
      <div
        class="relative w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-800 shadow-xl"
        role="dialog"
        aria-modal="true"
      >
        <div class="p-6 sm:p-8 space-y-4">
          <div class="flex justify-between items-start gap-4">
            <h3 class="text-lg font-bold text-white">
              {{ t("auth.guest_restore_title") }}
            </h3>
            <button
              type="button"
              class="text-gray-400 hover:text-white text-sm"
              @click="$emit('close')"
            >
              {{ t("common.close") }}
            </button>
          </div>
          <p class="text-sm text-gray-400">
            {{ t("auth.guest_restore_hint") }}
          </p>
          <p class="text-xs text-indigo-300/90 rounded-lg border border-indigo-500/25 bg-indigo-500/10 px-3 py-2">
            {{ t("auth.guest_restore_passkey_hint") }}
          </p>
          <textarea
            v-model="mnemonicInput"
            rows="4"
            class="w-full rounded-xl border border-gray-600 bg-gray-900/80 px-4 py-3 text-sm text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            :placeholder="t('auth.guest_restore_placeholder')"
            autocomplete="off"
          />
          <div class="rounded-xl border border-gray-700 bg-gray-900/50 p-4 space-y-3">
            <label class="flex items-start gap-3 cursor-pointer select-none">
              <input
                v-model="rememberDevice"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
              />
              <span class="text-sm text-gray-200">
                {{ t("account.device_remember_label") }}
                <span class="block text-xs text-gray-500 mt-1">
                  {{ t("account.device_remember_hint") }}
                </span>
              </span>
            </label>
            <input
              v-if="rememberDevice"
              v-model="devicePassphrase"
              type="password"
              autocomplete="new-password"
              :aria-label="t('account.device_passphrase_new_placeholder')"
              class="w-full rounded-xl border border-gray-600 bg-gray-900/80 px-4 py-3 text-sm text-gray-200"
              :placeholder="t('account.device_passphrase_new_placeholder')"
            />
          </div>
          <div v-if="error" class="text-sm text-red-400">
            {{ error }}
          </div>
          <div
            v-if="ownerSwitchImpact"
            class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 space-y-2"
          >
            <p class="text-sm font-medium text-amber-300">
              {{ t("auth.guest_restore_owner_switch_title") }}
            </p>
            <p class="text-sm text-gray-300">
              {{
                t("auth.guest_restore_owner_switch_body", {
                  documents: ownerSwitchImpact.documents,
                  contacts: ownerSwitchImpact.contacts,
                  companies: ownerSwitchImpact.companies,
                })
              }}
            </p>
          </div>
          <button
            type="button"
            :disabled="loading"
            class="w-full py-3 rounded-xl text-white text-sm font-semibold disabled:opacity-50"
            :class="ownerSwitchImpact ? 'bg-amber-600 hover:bg-amber-500' : 'bg-indigo-600 hover:bg-indigo-500'"
            @click="submit"
          >
            {{
              loading
                ? t("common.loading")
                : ownerSwitchImpact
                  ? t("auth.guest_restore_owner_switch_confirm")
                  : t("auth.guest_restore_submit")
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { storeGuestMnemonic } from "../../services/guestRecovery";
import { rememberDeviceWithPassphrase } from "../../services/deviceUnlock/provider";
import { isAcceptableDevicePassphrase } from "../../services/deviceUnlock/envelope";
import { previewOwnerSwitchImpact, type OwnerSwitchImpact } from "../../services/accountSeed";
import { useFlashStore } from "../../store/flash";

const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
  close: [];
  success: [payload: { store_id?: string | null }];
}>();

const { t } = useI18n();
const authStore = useAuthStore();

const flashStore = useFlashStore();

const mnemonicInput = ref("");
const loading = ref(false);
const error = ref("");
const rememberDevice = ref(false);
const devicePassphrase = ref("");
const ownerSwitchImpact = ref<Extract<OwnerSwitchImpact, { switches: true }> | null>(null);
const ownerSwitchConfirmedFor = ref("");

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      mnemonicInput.value = "";
      error.value = "";
      rememberDevice.value = false;
      devicePassphrase.value = "";
      ownerSwitchImpact.value = null;
      ownerSwitchConfirmedFor.value = "";
    }
  },
);

// Editing the phrase after the warning was shown invalidates the confirmation.
watch(mnemonicInput, (value) => {
  if (ownerSwitchImpact.value && value !== ownerSwitchConfirmedFor.value) {
    ownerSwitchImpact.value = null;
    ownerSwitchConfirmedFor.value = "";
  }
});

async function submit() {
  error.value = "";
  // Snapshot the inputs: edits made while the restore request is in flight
  // must not change what was validated and what gets persisted.
  const mnemonic = mnemonicInput.value;
  const remember = rememberDevice.value;
  const passphrase = devicePassphrase.value;
  // Validate the optional device passphrase BEFORE authenticating, so a weak
  // passphrase fails fast without a half-done restore.
  if (remember && !isAcceptableDevicePassphrase(passphrase)) {
    error.value = t("account.device_passphrase_too_weak");
    return;
  }
  loading.value = true;
  try {
    // Data-loss guard (P1): restoring with a different phrase switches the
    // local Evolu owner and re-links existing local data to the new account.
    // Show the impact once; the second click on the amber button confirms.
    if (!ownerSwitchImpact.value || ownerSwitchConfirmedFor.value !== mnemonic) {
      const impact = await previewOwnerSwitchImpact(mnemonic);
      // The phrase was edited while the preview ran - the result no longer
      // describes the current input. Drop it and let the user resubmit.
      if (mnemonicInput.value !== mnemonic) {
        loading.value = false;
        return;
      }
      if (impact.switches) {
        ownerSwitchImpact.value = impact;
        ownerSwitchConfirmedFor.value = mnemonic;
        loading.value = false;
        return;
      }
    }
    const data = await authStore.restoreGuestFromMnemonic(mnemonic);
    storeGuestMnemonic(mnemonic);
    if (remember) {
      // Best-effort: the phrase is already session-bound; remembering failing
      // must not block the login.
      try {
        await rememberDeviceWithPassphrase(mnemonic, passphrase);
      } catch {
        flashStore.warning(t("account.device_remember_failed"));
      }
    }
    emit("success", { store_id: data?.store_id ?? null });
    emit("close");
  } catch (rawError) {
    const e = asApiError(rawError);
    error.value =
      e?.response?.data?.message || t("auth.guest_restore_error");
  } finally {
    loading.value = false;
  }
}
</script>

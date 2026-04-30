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
        <div class="p-6 sm:p-8 space-y-6">
          <div class="flex justify-between items-start gap-4">
            <h3 class="text-lg font-bold text-white">
              {{ step === 0 ? t("auth.guest_backup_title") : step === 1 ? t("auth.guest_backup_words_title") : t("auth.guest_backup_confirm_title") }}
            </h3>
            <button
              type="button"
              class="text-gray-400 hover:text-white text-sm"
              @click="$emit('close')"
            >
              {{ t("common.close") }}
            </button>
          </div>

          <template v-if="step === 0">
            <p class="text-sm text-gray-300 leading-relaxed">
              {{ t("auth.guest_backup_intro") }}
            </p>
            <button
              type="button"
              class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
              @click="startWords"
            >
              {{ t("common.continue") }}
            </button>
          </template>

          <template v-else-if="step === 1">
            <p class="text-sm text-amber-200/90 bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
              {{ t("auth.guest_backup_warning") }}
            </p>
            <div
              class="grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-xl border border-gray-600 bg-gray-900/80 p-4 font-mono text-xs text-gray-200 max-h-56 overflow-y-auto"
            >
              <span v-for="(w, i) in words" :key="i" class="truncate"
                ><span class="text-gray-500 mr-1">{{ i + 1 }}.</span>{{ w }}</span
              >
            </div>
            <button
              type="button"
              class="w-full py-2.5 rounded-xl border border-gray-600 text-sm text-indigo-300 hover:bg-gray-700/80"
              @click="copyWords"
            >
              {{ copied ? t("auth.guest_backup_copied") : t("auth.guest_backup_copy") }}
            </button>
            <button
              type="button"
              class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
              @click="step = 2"
            >
              {{ t("common.continue") }}
            </button>
          </template>

          <template v-else>
            <label class="flex items-start gap-3 text-sm text-gray-300 cursor-pointer">
              <input
                v-model="confirmedSaved"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-600"
              />
              <span>{{ t("auth.guest_backup_confirm_checkbox") }}</span>
            </label>
            <button
              type="button"
              :disabled="!confirmedSaved"
              class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
              @click="finish"
            >
              {{ t("auth.guest_backup_finish") }}
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import {
  generateGuestMnemonic24,
  guestRecoveryPublicKeyHexFromMnemonic,
  storeGuestMnemonic,
} from "../../services/guestRecovery";

const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
  close: [];
  done: [payload: { recoveryPublicKeyHex: string }];
}>();

const { t } = useI18n();

const step = ref(0);
const mnemonic = ref("");
const words = ref<string[]>([]);
const confirmedSaved = ref(false);
const copied = ref(false);

function reset() {
  step.value = 0;
  mnemonic.value = "";
  words.value = [];
  confirmedSaved.value = false;
  copied.value = false;
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      reset();
    }
  },
);

function startWords() {
  mnemonic.value = generateGuestMnemonic24();
  words.value = mnemonic.value.split(" ");
  step.value = 1;
}

async function copyWords() {
  try {
    await navigator.clipboard.writeText(mnemonic.value);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    copied.value = false;
  }
}

function finish() {
  try {
    const recoveryPublicKeyHex = guestRecoveryPublicKeyHexFromMnemonic(mnemonic.value);
    storeGuestMnemonic(mnemonic.value);
    emit("done", { recoveryPublicKeyHex });
    emit("close");
  } catch {
    emit("close");
  }
}
</script>

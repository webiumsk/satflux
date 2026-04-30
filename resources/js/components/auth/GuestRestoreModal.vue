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
          <textarea
            v-model="mnemonicInput"
            rows="4"
            class="w-full rounded-xl border border-gray-600 bg-gray-900/80 px-4 py-3 text-sm text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            :placeholder="t('auth.guest_restore_placeholder')"
            autocomplete="off"
          />
          <div v-if="error" class="text-sm text-red-400">
            {{ error }}
          </div>
          <button
            type="button"
            :disabled="loading"
            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50"
            @click="submit"
          >
            {{ loading ? t("common.loading") : t("auth.guest_restore_submit") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";

const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
  close: [];
  success: [payload: { store_id?: string | null }];
}>();

const { t } = useI18n();
const authStore = useAuthStore();

const mnemonicInput = ref("");
const loading = ref(false);
const error = ref("");

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      mnemonicInput.value = "";
      error.value = "";
    }
  },
);

async function submit() {
  error.value = "";
  loading.value = true;
  try {
    const data = await authStore.restoreGuestFromMnemonic(mnemonicInput.value);
    emit("success", { store_id: data?.store_id ?? null });
    emit("close");
  } catch (e: any) {
    error.value =
      e?.response?.data?.message || t("auth.guest_restore_error");
  } finally {
    loading.value = false;
  }
}
</script>

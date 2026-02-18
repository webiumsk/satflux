<template>
  <div class="space-y-8">
    <!-- Store identification -->
    <div class="space-y-4">
      <h2
        class="text-lg font-semibold text-white border-b border-gray-700 pb-2"
      >
        {{ t("stores.settings_store_identification") }}
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-4">
          <div v-if="settings?.id" class="grid grid-cols-1 gap-1">
            <label class="block text-sm font-medium text-gray-400">{{
              t("stores.settings_store_id")
            }}</label>
            <input
              type="text"
              :value="settings.id"
              readonly
              class="block w-full px-4 py-2 border border-gray-600 rounded-xl bg-gray-800/50 text-gray-400 font-mono text-sm cursor-not-allowed"
            />
          </div>
          <div>
            <label
              for="name"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("stores.store_name") }}</label
            >
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            />
          </div>
          <div>
            <label
              for="website"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("stores.settings_store_website") }}</label
            >
            <input
              id="website"
              v-model="form.website"
              type="url"
              placeholder="https://example.com"
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            />
          </div>
        </div>
        <div class="md:col-span-1">
          <p class="text-sm font-medium text-gray-300 mb-3">
            {{ t("stores.store_logo") }}
          </p>
          <div class="flex flex-col items-center gap-4">
            <div v-if="storeLogoUrl" class="relative group">
              <div
                class="w-32 h-32 rounded-xl bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-600"
              >
                <img
                  :src="storeLogoUrl"
                  alt="Store logo"
                  class="w-full h-full object-contain"
                />
              </div>
              <button
                type="button"
                @click="$emit('logo-delete')"
                :disabled="deletingLogo"
                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg hover:bg-red-600 transition-colors"
                :title="t('stores.delete_logo')"
              >
                <svg
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
            <div
              v-else
              class="w-32 h-32 rounded-xl bg-gray-700/50 flex items-center justify-center border-2 border-dashed border-gray-600 text-gray-500"
            >
              <span class="text-sm">{{ t("stores.no_logo") }}</span>
            </div>
            <div class="w-full">
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{
                  storeLogoUrl
                    ? t("stores.update_logo")
                    : t("stores.upload_logo")
                }}
              </label>
              <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                @change="onLogoFileChange"
                class="block w-full text-sm text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer bg-gray-700/50 rounded-lg border border-gray-600 focus:outline-none"
              />
              <p class="mt-2 text-xs text-gray-500">
                {{ t("stores.settings_logo_format") }}
              </p>
            </div>
          </div>
          <div
            v-if="logoError"
            class="mt-4 rounded-xl bg-red-500/10 border border-red-500/20 p-4"
          >
            <div class="flex">
              <svg
                class="h-5 w-5 text-red-400 mr-2"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <div class="text-sm text-red-400">{{ logoError }}</div>
            </div>
          </div>
          <div
            v-if="logoSuccess"
            class="mt-4 rounded-xl bg-green-500/10 border border-green-500/20 p-4"
          >
            <div class="flex">
              <svg
                class="h-5 w-5 text-green-400 mr-2"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <div class="text-sm text-green-400">{{ logoSuccess }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Branding (Pro+) -->
    <div class="space-y-4">
      <div class="flex items-center gap-2 flex-wrap">
        <h2
          class="text-lg font-semibold text-white border-b border-gray-700 pb-2"
        >
          {{ t("stores.settings_branding") }}
        </h2>
        <button
          v-if="!canEditBranding"
          type="button"
          @click="$emit('show-upgrade')"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
        >
          {{ t("stores.available_in_pro") }}
        </button>
      </div>
      <p class="text-sm text-gray-400">
        {{ t("stores.settings_branding_desc") }}
      </p>
      <div
        :class="[
          'space-y-4',
          !canEditBranding && 'pointer-events-none opacity-75 select-none',
        ]"
      >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label
              for="brand_color"
              class="block text-sm font-medium text-gray-300 mb-1"
              >{{ t("stores.settings_brand_color") }}</label
            >
            <div class="flex items-center gap-2">
              <input
                id="brand_color"
                v-model="form.brand_color"
                type="color"
                :disabled="!canEditBranding"
                class="h-10 w-14 rounded border border-gray-600 bg-gray-800 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
              />
              <input
                v-model="form.brand_color"
                type="text"
                :disabled="!canEditBranding"
                class="flex-1 px-4 py-2 border border-gray-600 rounded-xl bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
              />
            </div>
          </div>
        </div>
        <div>
          <label
            for="css_url"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_custom_css_url") }}</label
          >
          <input
            id="css_url"
            v-model="form.css_url"
            type="url"
            placeholder="https://example.com/theme.css"
            :disabled="!canEditBranding"
            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          />
        </div>
        <div>
          <label
            for="payment_sound_url"
            class="block text-sm font-medium text-gray-300 mb-1"
            >{{ t("stores.settings_payment_sound_url") }}</label
          >
          <input
            id="payment_sound_url"
            v-model="form.payment_sound_url"
            type="url"
            placeholder="https://example.com/sound.mp3"
            :disabled="!canEditBranding"
            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          />
        </div>
      </div>
    </div>

    <!-- Archived (Pro + admin/support only) -->
    <div class="space-y-4 pt-8 border-t border-gray-700 mt-8">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="text-base font-semibold text-white">
          {{ t("stores.settings_archived") }}
        </h3>
        <button
          v-if="!canEditArchivedOption"
          type="button"
          @click="$emit('show-upgrade')"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
        >
          {{ t("stores.available_in_pro") }}
        </button>
      </div>
      <div
        :class="[
          'flex flex-wrap gap-x-8 gap-y-4',
          !canEditArchivedOption &&
            'pointer-events-none opacity-75 select-none',
        ]"
      >
        <label class="flex items-center">
          <input
            v-model="form.archived"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded"
            :disabled="!canEditArchivedOption"
          />
          <span class="ml-2 text-sm text-gray-300">{{
            t("stores.settings_archived")
          }}</span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

const emit = defineEmits<{
  "show-upgrade": [];
  "logo-upload": [event: Event];
  "logo-delete": [];
}>();

defineProps<{
  form: Record<string, any>;
  settings: any;
  canEditBranding: boolean;
  canEditArchivedOption: boolean;
  storeLogoUrl: string | null;
  logoError: string;
  logoSuccess: string;
  deletingLogo: boolean;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);

function onLogoFileChange(event: Event) {
  emit("logo-upload", event);
  if (fileInputRef.value) fileInputRef.value.value = "";
}

const { t } = useI18n();
</script>

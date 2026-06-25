<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    :aria-label="t('invoicing.relay_sync_modal_title')"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
      <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between shrink-0">
        <h2 class="text-lg font-semibold">{{ t('invoicing.relay_sync_modal_title') }}</h2>
        <button
          type="button"
          class="text-white/80 hover:text-white text-2xl leading-none"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </div>

      <div class="overflow-auto flex-1 p-5 space-y-4">
        <div
          v-if="isRelaySyncing"
          class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 flex items-start gap-3"
        >
          <svg
            class="animate-spin h-5 w-5 shrink-0 text-amber-800 mt-0.5"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          <div>
            <p class="text-sm font-medium text-amber-950">{{ t('invoicing.relay_sync_loading') }}</p>
            <p class="text-sm text-amber-900 mt-2">{{ t('invoicing.relay_sync_wait_detail') }}</p>
          </div>
        </div>

        <div
          v-else-if="!relayEnabled"
          class="rounded-lg border border-red-200 bg-red-50 px-4 py-3"
        >
          <p class="text-sm font-medium text-red-950">{{ t('invoicing.relay_sync_build_disabled_title') }}</p>
          <p class="text-sm text-red-900 mt-2">{{ t('invoicing.relay_sync_build_disabled_detail') }}</p>
        </div>

        <div v-else class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
          <p class="text-sm text-emerald-900">{{ t('invoicing.relay_sync_ready') }}</p>
          <p class="text-xs text-emerald-800 mt-1">
            {{ t('invoicing.relay_sync_relay_host', { host: relayUrl }) }}
          </p>
          <p v-if="relayFromProfile" class="text-xs text-emerald-700 mt-1">
            {{ t('invoicing.relay_sync_relay_from_profile') }}
          </p>
          <p v-if="relayOwnerHint" class="text-xs text-emerald-800 mt-1 font-mono">
            {{ t('invoicing.relay_sync_owner_hint', { owner: relayOwnerHint }) }}
          </p>
          <p class="text-xs text-emerald-800 mt-2">{{ t('invoicing.relay_sync_push_pull_hint') }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="invoicing-btn-secondary text-sm"
              :disabled="relayPushBusy || isRelaySyncing"
              @click="emit('push')"
            >
              {{
                relayPushBusy
                  ? t('invoicing.relay_sync_push_in_progress')
                  : t('invoicing.relay_sync_push')
              }}
            </button>
            <button
              type="button"
              class="invoicing-btn-secondary text-sm"
              :disabled="relayPullBusy || isRelaySyncing"
              @click="emit('pull')"
            >
              {{
                relayPullBusy
                  ? t('invoicing.relay_sync_refresh_in_progress')
                  : t('invoicing.relay_sync_pull')
              }}
            </button>
            <button
              type="button"
              class="invoicing-btn-secondary text-sm"
              :disabled="relayForcePushBusy || isRelaySyncing"
              @click="emit('forcePush')"
            >
              {{
                relayForcePushBusy
                  ? t('invoicing.relay_sync_force_push_in_progress')
                  : t('invoicing.relay_sync_force_push')
              }}
            </button>
          </div>
        </div>

        <div
          v-if="showServerLegacyCleanup"
          class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-4"
        >
          <p class="text-sm font-medium text-sky-950">{{ t('invoicing.server_legacy_cleanup_title') }}</p>
          <p class="text-sm text-sky-900 mt-2">{{ t('invoicing.server_legacy_cleanup_detail') }}</p>
          <router-link
            to="/account/profile?tab=data"
            class="invoicing-link inline-block mt-3 text-sm font-medium"
            @click="emit('close')"
          >
            {{ t('invoicing.server_legacy_cleanup_link') }}
          </router-link>
        </div>

        <p class="text-sm text-gray-600">
          {{ t('invoicing.local_first_data_notice') }}
          <router-link to="/legal/privacy" class="invoicing-link">{{ t('legal.nav.privacy') }}</router-link>.
        </p>
      </div>

      <div class="px-5 py-3 border-t border-gray-100 shrink-0 flex justify-end">
        <button type="button" class="invoicing-btn-secondary" @click="emit('close')">
          {{ t('common.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
  open: boolean;
  relayEnabled: boolean;
  relayUrl: string;
  relayFromProfile: boolean;
  relayOwnerHint: string;
  isRelaySyncing: boolean;
  showServerLegacyCleanup: boolean;
  relayPushBusy: boolean;
  relayPullBusy: boolean;
  relayForcePushBusy: boolean;
}>();

const emit = defineEmits<{
  close: [];
  push: [];
  pull: [];
  forcePush: [];
}>();

const { t } = useI18n();
</script>

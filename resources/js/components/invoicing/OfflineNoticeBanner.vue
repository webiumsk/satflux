<template>
  <div
    v-if="!online"
    class="rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm text-gray-800"
    role="status"
  >
    <p class="font-medium">{{ t("invoicing.offline_notice_title") }}</p>
    <p class="text-gray-600">{{ t("invoicing.offline_notice_body") }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";
import { useOnlineStatus } from "../../composables/useOnlineStatus";

/**
 * Honest offline notice (P1 phase 6): names exactly what keeps working
 * offline (drafts, lists, edits, CSV) and what needs the server (issuing,
 * PDF, e-mail, e-faktura, BTCPay). See docs/OFFLINE_CAPABILITIES.md.
 */
const { t } = useI18n();
const online = useOnlineStatus();
</script>

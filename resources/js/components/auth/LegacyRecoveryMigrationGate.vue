<template>
  <GuestBackupWizardModal
    :open="authStore.requiresRecoveryMigration"
    mandatory
    variant="legacy-migration"
    @done="onMigrationDone"
  />
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";
import GuestBackupWizardModal from "./GuestBackupWizardModal.vue";
import { useAuthStore } from "../../store/auth";
import { useFlashStore } from "../../store/flash";

const { t } = useI18n();
const authStore = useAuthStore();
const flashStore = useFlashStore();

async function onMigrationDone(payload: {
  recoveryPublicKeyHex: string;
  mnemonic: string;
}): Promise<void> {
  try {
    await authStore.completeLegacyRecoveryMigration(payload);
    flashStore.success(t("account.legacy_recovery_migration_success"));
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } };
    flashStore.error(
      err?.response?.data?.message ||
        t("account.legacy_recovery_migration_failed"),
    );
  }
}
</script>

<template>
  <div v-if="active" class="rounded-xl border border-indigo-400/40 bg-indigo-500/10 p-4" role="status">
    <p class="font-semibold">{{ t('free_pro.title') }}</p>
    <p class="mt-2 text-sm">{{ t('free_pro.description') }}</p>
    <router-link to="/documentation/free-pro-access" class="mt-2 inline-flex underline text-sm">{{ t('free_pro.details') }}</router-link>
    <router-link v-if="auth.user?.is_guest" to="/account" class="mt-3 inline-flex underline font-medium">
      {{ t('free_pro.verify_account') }}
    </router-link>
    <router-link v-else-if="auth.user && !auth.user.email_verified_at" to="/account/check-email" class="mt-3 inline-flex underline font-medium">
      {{ t('free_pro.verify_email') }}
    </router-link>
    <router-link v-else-if="!auth.isAuthenticated" to="/register" class="mt-3 inline-flex underline font-medium">
      {{ t('free_pro.create_account') }}
    </router-link>
    <router-link v-else-if="showAction" to="/" class="mt-3 inline-flex underline font-medium">
      {{ t('free_pro.open_app') }}
    </router-link>
  </div>
</template>
<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/store/auth';
defineProps<{ active: boolean; showAction?: boolean }>();
const { t } = useI18n();
const auth = useAuthStore();
</script>

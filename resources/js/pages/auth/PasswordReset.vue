<template>
  <router-link to="/login">{{ t("auth.welcome_back") }}</router-link>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

const router = useRouter();
const { t } = useI18n();
// Old reset links must not forward email addresses or reset tokens.
onMounted(() => void router.replace({ path: "/login", query: {} }));
</script>

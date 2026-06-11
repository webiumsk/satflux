<template>
  <div class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-8">
      <header class="mb-8">
        <h1 class="text-2xl font-bold text-white">{{ t('common.info_page_title') }}</h1>
        <p class="mt-1 text-sm text-gray-400">{{ t('common.info_page_subtitle') }}</p>
      </header>
      <div class="rounded-2xl border border-gray-800 bg-gray-900/80 p-6 sm:p-8">
        <AppFooterContent layout="page" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppFooterContent from '../components/layout/AppFooterContent.vue';

const { t } = useI18n();
</script>

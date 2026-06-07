<template>
  <nav
    class="invoicing-sub-nav block w-full min-h-[42px] bg-slate-800 border-b border-slate-900"
    aria-label="Document types"
  >
    <div :class="[INVOICING_CONTAINER_CLASS, 'flex flex-wrap items-stretch']">
      <template v-for="doc in documentNavItems" :key="doc.kind">
        <RouterLink
          v-if="doc.mvpEnabled && companyId"
          :to="{ name: doc.routeName, params: { companyId } }"
          class="invoicing-sub-nav__item text-slate-300 hover:text-white hover:bg-slate-700/80 px-4 py-2.5 text-sm border-b-2 border-transparent"
          :class="{ 'text-white bg-slate-900 border-indigo-400 font-medium': activeDocumentKind === doc.kind }"
        >
          {{ t(doc.labelKey) }}
        </RouterLink>
        <span
          v-else
          class="invoicing-sub-nav__item invoicing-sub-nav__item--disabled text-slate-300 opacity-40 px-4 py-2.5 text-sm cursor-not-allowed"
          :title="t('invoicing.coming_soon')"
        >
          {{ t(doc.labelKey) }}
        </span>
      </template>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { INVOICING_CONTAINER_CLASS, useInvoicingLayout } from '../../composables/useInvoicingLayout';

const { t } = useI18n();
const { companyId, documentNavItems, activeDocumentKind } = useInvoicingLayout();
</script>

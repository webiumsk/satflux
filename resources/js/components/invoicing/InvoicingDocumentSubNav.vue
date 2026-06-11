<template>
  <nav
    class="invoicing-sub-nav w-full bg-slate-800 border-b border-slate-900"
    aria-label="Document types"
  >
    <div
      ref="scrollRef"
      :class="[INVOICING_CONTAINER_CLASS, 'invoicing-doc-sub-nav-scroll flex items-stretch min-h-[40px] md:min-h-[42px]']"
    >
      <template v-for="doc in documentNavItems" :key="doc.kind">
        <RouterLink
          v-if="doc.mvpEnabled && companyId"
          :to="{ name: doc.routeName, params: { companyId } }"
          class="invoicing-sub-nav__item invoicing-sub-nav__item--scroll text-slate-300 hover:text-white hover:bg-slate-700/80 border-b-2 border-transparent"
          :class="{ 'text-white bg-slate-900 border-indigo-400 font-medium': activeDocumentKind === doc.kind }"
        >
          {{ t(doc.labelKey) }}
        </RouterLink>
        <span
          v-else
          class="invoicing-sub-nav__item invoicing-sub-nav__item--scroll invoicing-sub-nav__item--disabled text-slate-300 opacity-40 cursor-not-allowed"
          :title="t('invoicing.coming_soon')"
        >
          {{ t(doc.labelKey) }}
        </span>
      </template>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { INVOICING_CONTAINER_CLASS, useInvoicingLayout } from '../../composables/useInvoicingLayout';

const { t } = useI18n();
const { companyId, documentNavItems, activeDocumentKind } = useInvoicingLayout();

const scrollRef = ref<HTMLElement | null>(null);

function scrollActiveIntoView() {
  nextTick(() => {
    const root = scrollRef.value;
    if (!root) return;
    const active = root.querySelector('.invoicing-sub-nav__item--active');
    if (active instanceof HTMLElement) {
      active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }
  });
}

onMounted(scrollActiveIntoView);
watch(activeDocumentKind, scrollActiveIntoView);
</script>

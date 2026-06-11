<template>
  <nav
    class="invoicing-sub-nav w-full bg-slate-800 border-b border-slate-900"
    aria-label="Company settings"
  >
    <div :class="INVOICING_CONTAINER_CLASS">
      <div
        ref="scrollRef"
        class="invoicing-doc-sub-nav-scroll flex items-stretch min-h-[40px] md:min-h-[42px]"
      >
        <RouterLink
          v-for="tool in toolsNavItems"
          :key="tool.section"
          :to="linkTo(tool)"
          class="invoicing-sub-nav__item invoicing-sub-nav__item--scroll text-slate-300 hover:text-white hover:bg-slate-700/80 border-b-2 border-transparent"
          :class="{
            'invoicing-sub-nav__item--active text-white bg-slate-900 border-indigo-400 font-medium': activeToolsSection === tool.section,
          }"
        >
          {{ t(tool.labelKey) }}
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  INVOICING_CONTAINER_CLASS,
  useInvoicingLayout,
  type InvoicingToolsNavItem,
} from '../../composables/useInvoicingLayout';

const { t } = useI18n();
const { toolsNavItems, activeToolsSection, companyId } = useInvoicingLayout();

const scrollRef = ref<HTMLElement | null>(null);

function linkTo(tool: InvoicingToolsNavItem) {
  if (tool.section === 'subscription') {
    return { name: tool.routeName };
  }
  if (!companyId.value) {
    return { name: 'invoicing' };
  }
  return { name: tool.routeName, params: { companyId: companyId.value } };
}

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
watch(activeToolsSection, scrollActiveIntoView);
</script>

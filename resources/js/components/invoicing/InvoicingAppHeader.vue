<template>
  <header class="invoicing-app-header">
    <div :class="INVOICING_CONTAINER_CLASS">
      <nav class="invoicing-main-nav" aria-label="Invoicing main">
        <button
          v-for="item in mainNav"
          :key="item.id"
          type="button"
          class="invoicing-main-nav__item"
          :class="{ 'invoicing-main-nav__item--active': activeMainSection === item.id }"
          @click="navigateMain(item.id)"
        >
          {{ t(item.labelKey) }}
        </button>
        <div v-if="companyId && companyLabel" class="invoicing-main-nav__company ml-auto hidden sm:block">
          {{ companyLabel }}
        </div>
      </nav>
    </div>

    <nav
      v-if="displayToolsSubNav"
      class="invoicing-sub-nav block w-full min-h-[42px] bg-slate-800 border-b border-slate-900"
      aria-label="Company settings"
    >
      <div :class="[INVOICING_CONTAINER_CLASS, 'flex flex-wrap items-stretch']">
        <RouterLink
          v-for="tool in toolsNavItems"
          :key="tool.section"
          :to="toolsLinkTo(tool)"
          class="invoicing-sub-nav__item"
          :class="{ 'invoicing-sub-nav__item--active': activeToolsSection === tool.section }"
        >
          {{ t(tool.labelKey) }}
        </RouterLink>
      </div>
    </nav>

    <div v-if="showFilterBar" class="invoicing-filter-bar">
      <div :class="[INVOICING_CONTAINER_CLASS, 'flex flex-wrap items-center gap-3 py-3']">
        <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
          <slot name="filters" />
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import '../../styles/invoicing-theme.css';
import {
  INVOICING_CONTAINER_CLASS,
  useInvoicingLayout,
  type InvoicingMainSection,
  type InvoicingToolsNavItem,
} from '../../composables/useInvoicingLayout';

const props = withDefaults(
  defineProps<{
    companyLabel?: string;
    showToolsSubNav?: boolean;
    showFilterBar?: boolean;
  }>(),
  {
    showFilterBar: true,
  }
);

const { t } = useI18n();
const {
  companyId,
  toolsNavItems,
  activeMainSection,
  activeToolsSection,
  navigateMain,
} = useInvoicingLayout();

const displayToolsSubNav = computed(() => {
  if (props.showToolsSubNav === false) return false;
  if (props.showToolsSubNav === true) return true;
  return activeMainSection.value === 'tools';
});

const mainNav: { id: InvoicingMainSection; labelKey: string }[] = [
  { id: 'documents', labelKey: 'invoicing.main_nav_documents' },
  { id: 'expenses', labelKey: 'invoicing.main_nav_expenses' },
  { id: 'contacts', labelKey: 'invoicing.main_nav_contacts' },
  { id: 'tools', labelKey: 'invoicing.main_nav_tools' },
];

function toolsLinkTo(tool: InvoicingToolsNavItem) {
  if (tool.section === 'subscription') {
    return { name: tool.routeName };
  }
  if (!companyId.value) {
    return { name: 'invoicing' };
  }
  return { name: tool.routeName, params: { companyId: companyId.value } };
}
</script>

<template>
  <InvoicingDrawer
    :open="open"
    side="left"
    :title="displayCompanyLabel || t('invoicing.title')"
    :subtitle="t('invoicing.mobile_nav_subtitle')"
    @close="emit('close')"
  >
    <InvoicingCompanySwitcher
      v-if="companyId && displayCompanyLabel"
      variant="inline"
      :current-label="displayCompanyLabel"
      class="mb-4"
      @switched="emit('close')"
    />
    <nav class="space-y-1" aria-label="Invoicing navigation">
      <div v-for="item in visibleMainNav" :key="item.id" class="space-y-0.5">
        <button
          type="button"
          class="invoicing-mobile-nav-item w-full"
          :class="{ 'invoicing-mobile-nav-item--active': activeMainSection === item.id && !hasNestedActive(item.id) }"
          @click="onMainClick(item.id)"
        >
          <InvoicingIcons :name="mainIcon(item.id)" />
          <span class="flex-1 text-left">{{ t(item.labelKey) }}</span>
          <InvoicingIcons
            v-if="hasNestedItems(item.id)"
            name="chevron-right"
            class="h-4 w-4 opacity-50 transition-transform"
            :class="{ 'rotate-90': expandedSection === item.id }"
          />
        </button>

        <div
          v-if="hasNestedItems(item.id) && expandedSection === item.id"
          class="ml-4 space-y-0.5 border-l border-gray-200 pl-2"
        >
          <template v-if="item.id === 'documents'">
            <button
              v-for="doc in documentNavItems"
              :key="doc.kind"
              type="button"
              class="invoicing-mobile-nav-subitem w-full"
              :class="{
                'invoicing-mobile-nav-subitem--active': activeDocumentKind === doc.kind,
                'invoicing-mobile-nav-subitem--disabled': !doc.mvpEnabled,
              }"
              :disabled="!doc.mvpEnabled"
              :title="!doc.mvpEnabled ? t('invoicing.coming_soon') : undefined"
              @click="onDocumentClick(doc)"
            >
              {{ t(doc.labelKey) }}
            </button>
          </template>

          <template v-else-if="item.id === 'tools'">
            <button
              v-for="tool in toolsNavItems"
              :key="tool.section"
              type="button"
              class="invoicing-mobile-nav-subitem w-full"
              :class="{ 'invoicing-mobile-nav-subitem--active': activeToolsSection === tool.section }"
              @click="onToolsClick(tool)"
            >
              {{ t(tool.labelKey) }}
            </button>
          </template>
        </div>
      </div>
    </nav>
  </InvoicingDrawer>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InvoicingDrawer from './ui/InvoicingDrawer.vue';
import InvoicingIcons from './icons/InvoicingIcons.vue';
import InvoicingCompanySwitcher from './InvoicingCompanySwitcher.vue';
import { useInvoicingCompanySummary } from '../../composables/useInvoicingCompanySummary';
import {
  useInvoicingLayout,
  type InvoicingDocumentNavItem,
  type InvoicingMainSection,
  type InvoicingToolsNavItem,
} from '../../composables/useInvoicingLayout';

const props = defineProps<{
  open: boolean;
  companyLabel?: string;
}>();

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const {
  companyId,
  documentNavItems,
  toolsNavItems,
  activeMainSection,
  activeDocumentKind,
  activeToolsSection,
  navigateMain,
  navigateToolsSection,
  navigateDocumentKind,
} = useInvoicingLayout();
const { hasBankAccount, companyName: summaryCompanyName } = useInvoicingCompanySummary();

const displayCompanyLabel = computed(() => props.companyLabel || summaryCompanyName.value);

const visibleMainNav = computed(() => {
  const items: { id: InvoicingMainSection; labelKey: string }[] = [
    { id: 'documents', labelKey: 'invoicing.main_nav_documents' },
    { id: 'expenses', labelKey: 'invoicing.main_nav_expenses' },
  ];
  if (hasBankAccount.value) {
    items.push({ id: 'payments', labelKey: 'invoicing.main_nav_payments' });
  }
  items.push(
    { id: 'contacts', labelKey: 'invoicing.main_nav_contacts' },
    { id: 'stock', labelKey: 'invoicing.main_nav_stock' },
    { id: 'tools', labelKey: 'invoicing.main_nav_tools' },
  );
  return items;
});

const expandedSection = computed(() => {
  if (activeMainSection.value === 'documents' || activeMainSection.value === 'tools') {
    return activeMainSection.value;
  }
  return null;
});

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return;
    }
  }
);

function mainIcon(id: InvoicingMainSection) {
  const map: Record<InvoicingMainSection, 'documents' | 'expenses' | 'payments' | 'contacts' | 'stock' | 'tools'> = {
    documents: 'documents',
    expenses: 'expenses',
    payments: 'payments',
    contacts: 'contacts',
    stock: 'stock',
    tools: 'tools',
  };
  return map[id];
}

function hasNestedItems(id: InvoicingMainSection) {
  return id === 'documents' || id === 'tools';
}

function hasNestedActive(id: InvoicingMainSection) {
  return id === 'documents' || id === 'tools';
}

function onMainClick(id: InvoicingMainSection) {
  if (hasNestedItems(id)) {
    if (activeMainSection.value !== id) {
      navigateMain(id);
    }
    emit('close');
    return;
  }
  navigateMain(id);
  emit('close');
}

function onDocumentClick(doc: InvoicingDocumentNavItem) {
  if (!doc.mvpEnabled) {
    return;
  }
  navigateDocumentKind(doc);
  emit('close');
}

function onToolsClick(tool: InvoicingToolsNavItem) {
  navigateToolsSection(tool);
  emit('close');
}
</script>

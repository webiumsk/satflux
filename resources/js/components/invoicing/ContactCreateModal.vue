<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
      <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between shrink-0">
        <h2 class="text-lg font-semibold">{{ t('invoicing.new_contact') }}</h2>
        <button type="button" class="text-white/80 hover:text-white text-2xl leading-none" @click="$emit('close')">
          ×
        </button>
      </div>

      <nav class="invoicing-tabs px-4 shrink-0 border-b border-gray-200">
        <button
          type="button"
          class="invoicing-tab"
          :class="activeTab === 'billing' ? 'invoicing-tab--active' : ''"
          @click="activeTab = 'billing'"
        >
          {{ t('invoicing.tab_billing') }}
        </button>
        <button
          type="button"
          class="invoicing-tab"
          :class="activeTab === 'defaults' ? 'invoicing-tab--active' : ''"
          @click="activeTab = 'defaults'"
        >
          {{ t('invoicing.tab_defaults') }}
        </button>
      </nav>

      <form class="overflow-auto flex-1 p-5" @submit.prevent="save">
        <ContactFormFields v-model="form" :active-tab="activeTab" />

        <p v-if="error" class="text-sm text-red-600 text-center mt-4">{{ error }}</p>

        <div class="flex flex-wrap justify-center gap-3 mt-6 pt-4 border-t border-gray-100">
          <button type="button" class="invoicing-btn-secondary" :disabled="saving" @click="$emit('close')">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="invoicing-btn-primary px-8" :disabled="saving">
            {{ t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ContactFormFields from './ContactFormFields.vue';
import { emptyContactForm, formToPayload, type ContactFormState } from '../../composables/useCompanyContact';
import api from '../../services/api';

const props = defineProps<{
  open: boolean;
  companyId: string;
}>();

const emit = defineEmits<{
  close: [];
  saved: [contact: Record<string, unknown>];
}>();

const { t } = useI18n();

const form = ref<ContactFormState>(emptyContactForm());
const activeTab = ref<'billing' | 'defaults'>('billing');
const saving = ref(false);
const error = ref('');

const showDelivery = computed(
  () =>
    Boolean(
      form.value.delivery_street ||
        form.value.delivery_postal_code ||
        form.value.delivery_city ||
        form.value.delivery_country
    )
);

function resetForm() {
  form.value = emptyContactForm();
  activeTab.value = 'billing';
  error.value = '';
  saving.value = false;
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm();
  }
);

async function save() {
  error.value = '';
  if (!form.value.name.trim()) {
    error.value = t('validation.required');
    activeTab.value = 'billing';
    return;
  }
  saving.value = true;
  try {
    const payload = formToPayload(form.value, showDelivery.value);
    const res = await api.post(`/invoicing/companies/${props.companyId}/contacts`, payload);
    emit('saved', res.data.data);
    emit('close');
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}
</script>

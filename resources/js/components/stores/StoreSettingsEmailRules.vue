<template>
  <div class="space-y-8">
    <p class="text-sm text-gray-400 max-w-3xl">
      {{ t('stores.email_rules_intro') }}
    </p>
    <div class="flex justify-end">
      <button
        type="button"
        @click="openCreate"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
      >
        {{ t('stores.email_rules_add') }}
      </button>
    </div>

    <div v-if="loading" class="text-gray-400 text-sm">{{ t('common.loading') }}</div>
    <div v-else-if="rules.length === 0" class="text-gray-500 text-sm border border-dashed border-gray-600 rounded-xl p-8 text-center">
      {{ t('stores.email_rules_empty') }}
    </div>
    <ul v-else class="space-y-3">
      <li
        v-for="r in rules"
        :key="r.id"
        class="rounded-xl border border-gray-700 bg-gray-800/50 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
      >
        <div>
          <p class="text-white font-medium">{{ r.trigger_label || r.trigger }}</p>
          <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ r.subject }}</p>
        </div>
        <div class="flex gap-2 shrink-0">
          <button
            type="button"
            @click="openEdit(r)"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700"
          >
            {{ t('common.edit') }}
          </button>
          <button
            type="button"
            @click="confirmDelete(r)"
            class="px-3 py-1.5 text-sm rounded-lg border border-red-500/40 text-red-400 hover:bg-red-500/10"
          >
            {{ t('common.delete') }}
          </button>
        </div>
      </li>
    </ul>

    <!-- Editor modal -->
    <div
      v-if="editorOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      aria-modal="true"
      role="dialog"
    >
      <div class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm" @click="closeEditor" />
      <div
        class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-700 bg-gray-800 shadow-xl p-6 space-y-4"
      >
        <h3 class="text-lg font-semibold text-white">
          {{ editingId ? t('stores.email_rules_edit') : t('stores.email_rules_add') }}
        </h3>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('stores.email_rules_trigger') }}</label>
          <select
            v-model="form.trigger"
            class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3"
          >
            <option v-for="opt in triggerOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <details v-if="triggerOptions.length" class="mt-2 rounded-lg border border-gray-700 bg-gray-900/50 px-3 py-2">
            <summary class="cursor-pointer text-xs font-medium text-gray-300">
              {{ t('stores.email_rules_trigger_help') }}
            </summary>
            <ul class="space-y-1 mt-2">
              <li
                v-for="opt in triggerOptions"
                :key="`hint-${opt.value}`"
                class="text-xs text-gray-400"
              >
                <span class="text-gray-300">{{ opt.label }}</span>
                <span class="text-gray-500"> - {{ triggerDescription(opt.value) }}</span>
              </li>
            </ul>
          </details>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('stores.email_rules_condition') }}</label>
          <input
            v-model="form.condition"
            type="text"
            class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3 font-mono"
            :placeholder="t('stores.email_rules_condition_placeholder')"
          />
          <p class="text-xs text-gray-500 mt-1">{{ t('stores.email_rules_condition_hint') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('stores.email_rules_to') }}</label>
          <input
            v-model="form.to_addresses"
            type="text"
            class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3"
          />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">CC</label>
            <input
              v-model="form.cc_addresses"
              type="text"
              class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">BCC</label>
            <input
              v-model="form.bcc_addresses"
              type="text"
              class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3"
            />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
          <input v-model="form.send_to_buyer" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-indigo-600" />
          {{ t('stores.email_rules_send_buyer') }}
        </label>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('stores.email_rules_subject') }}</label>
          <input
            v-model="form.subject"
            type="text"
            class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('stores.email_rules_body') }}</label>
          <textarea
            v-model="form.body"
            rows="8"
            class="w-full rounded-lg border border-gray-600 bg-gray-900 text-white text-sm py-2 px-3 font-mono"
          />
          <p class="text-xs text-gray-500 mt-1">{{ t('stores.email_rules_placeholders_hint') }}</p>
          <details class="mt-3 rounded-lg border border-gray-700 bg-gray-900/50 px-3 py-2">
            <summary class="cursor-pointer text-xs font-medium text-gray-300">
              {{ t('stores.email_rules_placeholder_examples_title') }}
            </summary>
            <ul class="space-y-1 mt-2 text-xs text-gray-400 font-mono">
              <li>{Invoice.Id}</li>
              <li>{Invoice.OrderId}</li>
              <li>{Invoice.Status}</li>
              <li>{Invoice.Amount} {Invoice.Currency}</li>
              <li>{Invoice.CheckoutLink}</li>
              <li>{Store.Name}</li>
              <li>{Invoice.Metadata.posData.itemsTotal}</li>
              <li>{Invoice.Metadata.itemsTotal}</li>
            </ul>
            <p class="text-xs text-gray-500 mt-2">{{ t('stores.email_rules_placeholder_examples_meta_note') }}</p>
          </details>
        </div>

        <p v-if="editorError" class="text-sm text-red-400">{{ editorError }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            @click="closeEditor"
            class="px-4 py-2 text-sm rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            :disabled="saving"
            @click="saveRule"
            class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            {{ saving ? t('auth.saving') : t('stores.save_changes') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';

const { t } = useI18n();

const props = defineProps({
  store: { type: Object, required: true },
});

const loading = ref(true);
const saving = ref(false);
const rules = ref<any[]>([]);
const triggerOptions = ref<{ value: string; label: string }[]>([]);
const editorOpen = ref(false);
const editingId = ref<string | null>(null);
const editorError = ref('');
const preferredDefaultTrigger = 'InvoiceSettled';

const form = ref({
  trigger: preferredDefaultTrigger,
  condition: '',
  to_addresses: '',
  cc_addresses: '',
  bcc_addresses: '',
  send_to_buyer: false,
  subject: '',
  body: '',
  sort_order: 0,
});

async function loadTriggers() {
  const res = await api.get(`/stores/${props.store.id}/email-rules/triggers`);
  triggerOptions.value = res.data.data ?? [];
  if (triggerOptions.value.length && !triggerOptions.value.some((o: { value: string; label: string }) => o.value === form.value.trigger)) {
    form.value.trigger =
      triggerOptions.value.find((o: { value: string; label: string }) => o.value === preferredDefaultTrigger)?.value ??
      triggerOptions.value[0].value;
  }
}

async function loadRules() {
  loading.value = true;
  try {
    const res = await api.get(`/stores/${props.store.id}/email-rules`);
    rules.value = res.data.data ?? [];
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  try {
    await Promise.all([loadTriggers(), loadRules()]);
  } catch (e) {
    console.error(e);
  }
});

function resetForm() {
  form.value = {
    trigger:
      triggerOptions.value.find((o: { value: string; label: string }) => o.value === preferredDefaultTrigger)?.value ??
      triggerOptions.value[0]?.value ??
      preferredDefaultTrigger,
    condition: '',
    to_addresses: '',
    cc_addresses: '',
    bcc_addresses: '',
    send_to_buyer: false,
    subject: 'Invoice {Invoice.Id} settled',
    body: `<p>Invoice {Invoice.Id} (Order: {Invoice.OrderId})</p>`,
    sort_order: 0,
  };
}

function triggerDescription(trigger: string) {
  return t(`stores.email_rules_trigger_desc.${trigger}`);
}

function openCreate() {
  editingId.value = null;
  editorError.value = '';
  resetForm();
  editorOpen.value = true;
}

function openEdit(r: any) {
  editingId.value = r.id;
  editorError.value = '';
  form.value = {
    trigger: r.trigger,
    condition: r.condition ?? '',
    to_addresses: r.to_addresses ?? '',
    cc_addresses: r.cc_addresses ?? '',
    bcc_addresses: r.bcc_addresses ?? '',
    send_to_buyer: !!r.send_to_buyer,
    subject: r.subject ?? '',
    body: r.body ?? '',
    sort_order: r.sort_order ?? 0,
  };
  editorOpen.value = true;
}

function closeEditor() {
  editorOpen.value = false;
}

async function saveRule() {
  editorError.value = '';
  saving.value = true;
  try {
    const payload = { ...form.value };
    if (editingId.value) {
      await api.put(`/stores/${props.store.id}/email-rules/${editingId.value}`, payload);
    } else {
      await api.post(`/stores/${props.store.id}/email-rules`, payload);
    }
    closeEditor();
    await loadRules();
  } catch (err: any) {
    const msg = err.response?.data?.message;
    const errs = err.response?.data?.errors;
    editorError.value =
      errs && typeof errs === 'object'
        ? Object.values(errs).flat().join(', ')
        : msg || 'Save failed';
  } finally {
    saving.value = false;
  }
}

async function confirmDelete(r: any) {
  if (!confirm(t('stores.email_rules_delete_confirm'))) return;
  try {
    await api.delete(`/stores/${props.store.id}/email-rules/${r.id}`);
    await loadRules();
  } catch (e) {
    console.error(e);
  }
}
</script>

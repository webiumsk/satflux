<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName">
        <template #filters>
          <input
            v-model="searchQuery"
            type="search"
            class="invoicing-sf-input max-w-xs min-w-[200px]"
            :placeholder="t('invoicing.search_contact')"
            @input="onSearchInput"
          />
        </template>
        <template #actions>
          <RouterLink :to="contactNewTo()" class="invoicing-btn-primary">
            + {{ t('invoicing.new_contact') }}
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <div class="invoicing-letter-filter">
      <button
        type="button"
        class="invoicing-letter"
        :class="activeLetter === 'all' ? 'invoicing-letter--active' : 'invoicing-letter--idle'"
        @click="setLetter('all')"
      >
        {{ t('invoicing.letter_all') }}
      </button>
      <button
        v-for="ch in ALPHABET_FILTER"
        :key="ch"
        type="button"
        class="invoicing-letter"
        :class="letterClass(ch)"
        @click="setLetter(ch)"
      >
        {{ ch }}
      </button>
    </div>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="contacts.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.no_contacts') }}
    </div>

    <div v-else class="invoicing-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="contact-table w-full min-w-[800px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="w-10">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="allSelected"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="min-w-[220px] text-left">{{ t('invoicing.col_contact_name') }}</th>
              <th class="min-w-[160px] text-right">
                <span class="block">{{ t('invoicing.col_invoiced') }}</span>
                <span class="block font-normal normal-case text-gray-500">{{ t('invoicing.col_overdue_sub') }}</span>
              </th>
              <th class="min-w-[140px] text-right">{{ t('invoicing.col_avg_payment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in contacts"
              :key="c.id"
              class="bg-white hover:bg-gray-50"
              :class="selectedIds.has(c.id) ? 'bg-indigo-50' : ''"
            >
              <td>
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="selectedIds.has(c.id)"
                  @change="toggleRow(c.id)"
                />
              </td>
              <td>
                <RouterLink :to="contactShowTo(c.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium">
                  {{ c.name }}
                </RouterLink>
              </td>
              <td class="text-right">
                <template v-if="statLine(c)">
                  <span class="invoicing-stat-main">{{ statLine(c).main }}</span>
                  <span
                    class="invoicing-stat-sub"
                    :class="statLine(c).subAlert ? 'invoicing-stat-sub--alert' : ''"
                  >
                    {{ statLine(c).sub }}
                  </span>
                </template>
              </td>
              <td class="text-right text-gray-700 whitespace-nowrap">
                <template v-if="c.stats?.avg_payment_days != null">
                  {{ c.stats.avg_payment_days }} {{ t('invoicing.days_suffix') }}
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  ALPHABET_FILTER,
  formatOverduePair,
  useContactRoutes,
  type CompanyContactRow,
} from '../../composables/useCompanyContact';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const { contactShowTo, contactNewTo } = useContactRoutes(companyId);
const companyName = ref('');

const contacts = ref<CompanyContactRow[]>([]);
const availableLetters = ref<string[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const activeLetter = ref('all');
const selectedIds = ref(new Set<string>());
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const allSelected = computed(
  () => contacts.value.length > 0 && contacts.value.every((c) => selectedIds.value.has(c.id))
);

async function load() {
  loading.value = true;
  try {
    const companyRes = await api.get(`/invoicing/companies/${companyId.value}`);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';

    const res = await api.get(`/invoicing/companies/${companyId.value}/contacts`, {
      params: {
        q: searchQuery.value.trim() || undefined,
        letter: activeLetter.value === 'all' ? undefined : activeLetter.value,
      },
    });
    contacts.value = res.data.data ?? [];
    availableLetters.value = res.data.meta?.letters ?? [];
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(), 300);
}

function setLetter(letter: string) {
  if (letter !== 'all' && !availableLetters.value.includes(letter)) return;
  activeLetter.value = letter;
  load();
}

function letterClass(ch: string) {
  if (activeLetter.value === ch) return 'invoicing-letter--active';
  if (!availableLetters.value.includes(ch)) return 'invoicing-letter--disabled';
  return 'invoicing-letter--idle';
}

function statLine(c: CompanyContactRow) {
  const s = c.stats;
  if (!s) return null;
  return formatOverduePair(
    s.invoiced_total,
    s.invoiced_count,
    s.overdue_total,
    s.overdue_count
  );
}

function toggleRow(id: string) {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set();
  } else {
    selectedIds.value = new Set(contacts.value.map((c) => c.id));
  }
}

watch(
  () => route.params.companyId,
  () => {
    activeLetter.value = 'all';
    searchQuery.value = '';
    load();
  }
);

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>

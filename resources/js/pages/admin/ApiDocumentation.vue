<template>
  <main class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
    <div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-8">
      <router-link to="/admin" class="text-indigo-400 hover:text-indigo-300 focus-visible:outline focus-visible:outline-2">
        {{ t('admin.api_docs.back') }}
      </router-link>
      <h1 class="mt-4 text-3xl font-bold text-white">{{ t('admin.api_docs.title') }}</h1>
      <p class="mt-2 text-gray-300">{{ t('admin.api_docs.description') }}</p>
      <p class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ t('admin.api_docs.read_only') }}
      </p>
      <p v-if="!allowed" role="alert" class="mt-6 text-red-300">{{ t('admin.api_docs.denied') }}</p>
      <template v-else>
        <p v-if="loading" role="status" class="mt-6 text-gray-300">{{ t('admin.api_docs.loading') }}</p>
        <div v-if="error" role="alert" class="mt-6 rounded-lg border border-red-500/40 p-4 text-red-200">
          <p>{{ t('admin.api_docs.load_error') }}</p>
          <button type="button" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2" @click="retry">
            {{ t('admin.api_docs.retry') }}
          </button>
        </div>
        <template v-if="catalog">
          <div class="mt-6">
            <label for="api-document" class="mb-2 block text-sm font-medium text-gray-200">{{ t('admin.api_docs.document') }}</label>
            <select id="api-document" v-model="selectedId" class="w-full rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-white focus:ring-2 focus:ring-indigo-500 sm:max-w-sm" @change="selectDocument">
              <option v-for="document in catalog.documents" :key="document.id" :value="document.id">{{ document.title }}</option>
            </select>
            <p class="mt-2 text-sm text-gray-400">{{ selectedDocument?.description }}</p>
          </div>
          <section v-if="catalog.providers.length" class="mt-6 rounded-xl border border-gray-700 bg-gray-800 p-4" :aria-label="t('admin.api_docs.providers')">
            <h2 class="font-semibold text-white">{{ t('admin.api_docs.providers') }}</h2>
            <p class="mt-1 text-sm text-gray-400">{{ t('admin.api_docs.providers_hint') }}</p>
            <ul class="mt-3 grid gap-3 sm:grid-cols-3">
              <li v-for="provider in catalog.providers" :key="provider.id">
                <a :href="provider.url" target="_blank" rel="noopener noreferrer" class="text-indigo-300 underline focus-visible:outline focus-visible:outline-2">{{ provider.title }} <span class="sr-only">{{ t('admin.api_docs.new_tab') }}</span></a>
                <p class="mt-1 text-xs text-gray-400">{{ provider.description }}</p>
              </li>
            </ul>
          </section>
          <div v-if="configuration && !loading && !error" :key="selectedId" class="api-reference mt-6 overflow-hidden rounded-xl border border-gray-700 bg-white">
            <ApiReference :configuration="configuration" />
          </div>
        </template>
      </template>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, shallowRef, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiReference } from '@scalar/api-reference';
import '@scalar/api-reference/style.css';
import { useAuthStore } from '../../store/auth';
import {
    apiDocumentationRendererOptions, loadApiDocument, loadApiDocumentationCatalog,
    type ApiDocumentId, type ApiDocumentationCatalog,
} from '../../services/apiDocumentation';

const { t } = useI18n();
const auth = useAuthStore();
const allowed = computed(() => auth.user?.role === 'admin' || auth.user?.role === 'support');
const catalog = shallowRef<ApiDocumentationCatalog | null>(null);
const configuration = shallowRef<ReturnType<typeof apiDocumentationRendererOptions> | null>(null);
const selectedId = ref<ApiDocumentId>('satflux');
const selectedDocument = computed(() => catalog.value?.documents.find(document => document.id === selectedId.value));
const loading = ref(false);
const error = ref(false);
let requestGeneration = 0;

async function load(resetCatalog: boolean) {
    const generation = ++requestGeneration;
    configuration.value = null;
    error.value = false;
    if (!allowed.value) return;
    loading.value = true;
    try {
        if (resetCatalog || !catalog.value) {
            const result = await loadApiDocumentationCatalog();
            if (generation !== requestGeneration) return;
            catalog.value = result;
            selectedId.value = result.documents[0].id;
        }
        const document = await loadApiDocument(selectedId.value);
        if (generation !== requestGeneration) return;
        configuration.value = apiDocumentationRendererOptions(document);
    } catch {
        if (generation === requestGeneration) error.value = true;
    } finally {
        if (generation === requestGeneration) loading.value = false;
    }
}
function retry() { void load(!catalog.value); }
function selectDocument() { void load(false); }
watch(() => [auth.user?.id, auth.user?.role], () => {
    requestGeneration++;
    catalog.value = null;
    configuration.value = null;
    loading.value = false;
    error.value = false;
    if (allowed.value) void load(true);
}, { immediate: true });
onBeforeUnmount(() => { requestGeneration++; });
</script>

<style scoped>
.api-reference {
    --scalar-font: inherit;
}
</style>

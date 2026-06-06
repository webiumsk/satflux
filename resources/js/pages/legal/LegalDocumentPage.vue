<template>
  <div class="min-h-screen bg-gray-900">
    <PublicHeader />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
      <nav class="mb-8 flex flex-wrap gap-3 text-sm" aria-label="Legal documents">
        <router-link
          v-for="item in legalNav"
          :key="item.id"
          :to="item.path"
          class="rounded-lg px-3 py-1.5 transition-colors"
          :class="
            item.id === docId
              ? 'bg-indigo-600/20 text-indigo-200 border border-indigo-500/40'
              : 'text-gray-400 hover:text-white hover:bg-gray-800'
          "
        >
          {{ t(item.labelKey) }}
        </router-link>
      </nav>

      <header class="mb-10">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3">
          {{ document.title }}
        </h1>
        <p class="text-sm text-gray-500">
          {{ t('legal.last_updated') }}: {{ document.lastUpdated }}
        </p>
        <p
          v-if="document.draftNotice"
          class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100/90"
          role="note"
        >
          {{ document.draftNotice }}
        </p>
      </header>

      <article class="prose prose-invert prose-sm sm:prose-base max-w-none">
        <section
          v-for="(section, index) in document.sections"
          :key="index"
          class="mb-8"
        >
          <h2 class="text-xl font-semibold text-white mb-3">
            {{ section.heading }}
          </h2>
          <p
            v-for="(paragraph, pIndex) in section.paragraphs"
            :key="pIndex"
            class="text-gray-300 leading-relaxed mb-3"
          >
            {{ paragraph }}
          </p>
        </section>
      </article>

      <p class="mt-12 text-sm text-gray-500 border-t border-gray-800 pt-6">
        {{ t('legal.questions') }}
        <a
          href="mailto:hello@satflux.io"
          class="text-indigo-300 hover:text-indigo-200 underline"
        >hello@satflux.io</a>
      </p>
    </div>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '../../components/layout/PublicHeader.vue';
import AppFooter from '../../components/layout/AppFooter.vue';
import { getLegalDocument, LEGAL_NAV } from '../../content/legal/documents';
import type { LegalDocId } from '../../content/legal/types';

const props = defineProps<{
  docId: LegalDocId;
}>();

const { t, locale } = useI18n();

const document = computed(() => getLegalDocument(props.docId, locale.value));
const legalNav = LEGAL_NAV;
</script>

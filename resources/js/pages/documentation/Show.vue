<template>
  <div class="min-h-screen bg-gray-900">
    <PublicHeader />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-24">
        <svg
          class="animate-spin h-10 w-10 text-indigo-500"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
      </div>

      <!-- Article Content -->
      <article v-else-if="article" class="bg-gray-800 border border-gray-700 rounded-2xl p-8">
        <!-- Back Button -->
        <router-link
          to="/documentation"
          class="inline-flex items-center text-sm text-gray-400 hover:text-indigo-400 mb-6 transition-colors"
        >
          <svg
            class="w-4 h-4 mr-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18"
            />
          </svg>
          {{ t('documentation.back_to_documentation') }}
        </router-link>

        <!-- Category Badge -->
        <div v-if="article.category" class="mb-4">
          <span
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"
          >
            {{ article.category.name }}
          </span>
        </div>

        <!-- Title -->
        <h1 class="text-4xl font-bold text-white mb-4">
          {{ article.title }}
        </h1>

        <!-- Meta -->
        <div class="flex items-center text-sm text-gray-400 mb-8 pb-8 border-b border-gray-700">
          <span>{{ formatDate(article.created_at) }}</span>
          <span v-if="article.updated_at !== article.created_at" class="ml-4">
            {{ t('documentation.updated') }}: {{ formatDate(article.updated_at) }}
          </span>
        </div>

        <!-- Content -->
        <div
          class="prose prose-invert prose-lg max-w-none"
          v-html="formatContent(article.content)"
        ></div>
      </article>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <h2 class="text-2xl font-bold text-white mb-4">
          {{ t('documentation.article_not_found') }}
        </h2>
        <router-link
          to="/documentation"
          class="text-indigo-400 hover:text-indigo-300"
        >
          {{ t('documentation.back_to_documentation') }}
        </router-link>
      </div>
    </div>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { updatePageMeta } from '../../composables/usePageMeta';
import { documentationApi } from '../../services/api';
import PublicHeader from "../../components/layout/PublicHeader.vue";
import AppFooter from "../../components/layout/AppFooter.vue";

const route = useRoute();
const { t } = useI18n();

const loading = ref(false);
const article = ref<any>(null);

const loadArticle = async () => {
  loading.value = true;
  try {
    const slug = route.params.slug as string;
    const response = await documentationApi.show(slug);
    article.value = response.data.data;
  } catch (error: any) {
    console.error('Failed to load article:', error);
    if (error.response?.status === 404) {
      article.value = null;
    }
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleDateString();
};

const formatContent = (content: string) => {
  // Simple markdown-like formatting (can be enhanced with a markdown parser)
  if (!content) return '';
  
  // Convert line breaks to <br>
  let formatted = content.replace(/\n\n/g, '</p><p>');
  formatted = formatted.replace(/\n/g, '<br>');
  
  // Convert **bold** to <strong>
  formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  
  // Convert *italic* to <em>
  formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
  
  // Convert # headings
  formatted = formatted.replace(/^### (.*$)/gim, '<h3>$1</h3>');
  formatted = formatted.replace(/^## (.*$)/gim, '<h2>$1</h2>');
  formatted = formatted.replace(/^# (.*$)/gim, '<h1>$1</h1>');
  
  return `<p>${formatted}</p>`;
};

onMounted(() => {
  loadArticle();
});

watch(article, (a: { title: string; meta_description?: string } | null) => {
  if (a) {
    updatePageMeta(route, {
      title: a.title,
      description: a.meta_description || a.title,
    });
  }
}, { immediate: true });
</script>

<style scoped>
.prose {
  color: #e5e7eb;
}

.prose h1,
.prose h2,
.prose h3 {
  color: #ffffff;
  font-weight: 700;
  margin-top: 2em;
  margin-bottom: 1em;
}

.prose h1 {
  font-size: 2.25em;
}

.prose h2 {
  font-size: 1.875em;
}

.prose h3 {
  font-size: 1.5em;
}

.prose p {
  margin-bottom: 1.25em;
  line-height: 1.75;
}

.prose strong {
  color: #ffffff;
  font-weight: 600;
}

.prose em {
  font-style: italic;
}

.prose a {
  color: #818cf8;
  text-decoration: underline;
}

.prose a:hover {
  color: #a5b4fc;
}
</style>

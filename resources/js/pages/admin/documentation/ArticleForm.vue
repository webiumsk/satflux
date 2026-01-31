<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">
        {{ isEdit ? t('admin.documentation.articles.edit_article') : t('admin.documentation.articles.create_article') }}
      </h1>
    </div>

    <form @submit.prevent="handleSubmit" class="bg-gray-800 rounded-xl border border-gray-700 p-6 space-y-6">
      
      <!-- Language tabs (shared for Title, Content, Meta Description) -->
      <div class="border-b border-gray-700">
        <nav class="-mb-px flex space-x-4">
          <button
            v-for="loc in formLocales"
            :key="loc.code"
            type="button"
            :class="[
              'py-2 px-4 border-b-2 font-medium text-sm transition-colors',
              activeLocale === loc.code
                ? 'border-indigo-500 text-indigo-400'
                : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-600'
            ]"
            @click="activeLocale = loc.code"
          >
            {{ loc.name }}
          </button>
        </nav>
      </div>

      <!-- Title (for active locale) -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.title_label') || 'Title' }} ({{ formLocales.find(l => l.code === activeLocale)?.name ?? activeLocale }})
          <span class="text-red-400">*</span>
        </label>
        <input
          :value="form.title[activeLocale] || ''"
          type="text"
          :placeholder="t('admin.documentation.articles.title_placeholder') || 'Article title'"
          class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          @input="updateTitleLocale(activeLocale, ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- Content (for active locale) -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.content_label') }} ({{ formLocales.find(l => l.code === activeLocale)?.name ?? activeLocale }})
          <span class="text-red-400">*</span>
        </label>
        <RichTextEditor
          :key="activeLocale"
          :model-value="form.content[activeLocale] || ''"
          :placeholder="t('admin.documentation.articles.content_placeholder')"
          @update:model-value="updateContentLocale(activeLocale, $event)"
        />
      </div>

      <!-- Meta Description (for active locale) -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.meta_description_label') || 'Meta Description' }} ({{ formLocales.find(l => l.code === activeLocale)?.name ?? activeLocale }})
        </label>
        <textarea
          :value="form.meta_description[activeLocale] || ''"
          :rows="3"
          :placeholder="t('admin.documentation.articles.meta_description_placeholder') || 'SEO meta description'"
          class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          @input="updateMetaDescriptionLocale(activeLocale, ($event.target as HTMLTextAreaElement).value)"
        />
      </div>

      <!-- Slug -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.slug') }}
        </label>
        <input
          v-model="form.slug"
          type="text"
          class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <p class="mt-1 text-sm text-gray-400">{{ t('admin.documentation.articles.slug_hint') }}</p>
      </div>

      <!-- Category -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.category') }}
        </label>
        <Select
          v-model="form.category_id"
          :options="categoryOptions"
          :placeholder="t('admin.documentation.articles.no_category')"
        />
      </div>

      <!-- Order -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.documentation.articles.order') }}
        </label>
        <input
          v-model.number="form.order"
          type="number"
          min="0"
          class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <!-- Published -->
      <div class="flex items-center">
        <input
          v-model="form.is_published"
          type="checkbox"
          id="is_published"
          class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500"
        />
        <label for="is_published" class="ml-2 text-sm text-gray-300">
          {{ t('admin.documentation.articles.published') }}
        </label>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4 pt-4 border-t border-gray-700">
        <router-link
          to="/admin/documentation"
          class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700"
        >
          {{ t('admin.documentation.articles.cancel') }}
        </router-link>
        <button
          type="submit"
          :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
        >
          {{ saving ? t('admin.documentation.articles.saving') : (isEdit ? t('admin.documentation.articles.update') : t('admin.documentation.articles.create')) }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { adminDocumentationApi } from '../../../services/api';
import RichTextEditor from '../../../components/admin/RichTextEditor.vue';
import Select from '../../../components/ui/Select.vue';

const formLocales = [
  { code: 'en', name: 'English' },
  { code: 'sk', name: 'Slovenčina' },
  { code: 'es', name: 'Español' },
  { code: 'cz', name: 'Čeština' },
  { code: 'de', name: 'Deutsch' },
  { code: 'fr', name: 'Français' },
  { code: 'hu', name: 'Magyar' },
  { code: 'pl', name: 'Polski' },
];

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);
const categories = ref<any[]>([]);
const activeLocale = ref('en');

function slugify(text: string): string {
  return text
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

function updateTitleLocale(locale: string, value: string) {
  form.value.title = { ...form.value.title, [locale]: value };
  if (!isEdit.value && !form.value.slug && value.trim()) {
    form.value.slug = slugify(value);
  }
}

function updateContentLocale(locale: string, html: string) {
  form.value.content = { ...form.value.content, [locale]: html };
}

function updateMetaDescriptionLocale(locale: string, value: string) {
  form.value.meta_description = { ...form.value.meta_description, [locale]: value };
}

const categoryOptions = computed(() => [
  { label: t('admin.documentation.articles.no_category'), value: null },
  ...categories.value.map(cat => ({
    label: cat.name?.en || cat.name,
    value: cat.id
  }))
]);

const form = ref({
  slug: '',
  title: {} as Record<string, string>,
  content: {} as Record<string, string>,
  meta_description: {} as Record<string, string>,
  category_id: null as string | null,
  order: 0,
  is_published: false,
});

const loadArticle = async () => {
  if (!isEdit.value) return;
  
  try {
    const response = await adminDocumentationApi.articles.show(route.params.id as string);
    const article = response.data.data;
    form.value = {
      slug: article.slug || '',
      title: article.title || {},
      content: article.content || {},
      meta_description: article.meta_description || {},
      category_id: article.category_id,
      order: article.order || 0,
      is_published: article.is_published || false,
    };
  } catch (error) {
    console.error('Failed to load article:', error);
    router.push('/admin/documentation');
  }
};

const loadCategories = async () => {
  try {
    const response = await adminDocumentationApi.categories.index();
    categories.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load categories:', error);
  }
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    if (isEdit.value) {
      await adminDocumentationApi.articles.update(route.params.id as string, form.value);
    } else {
      await adminDocumentationApi.articles.create(form.value);
    }
    router.push('/admin/documentation');
  } catch (error: any) {
    console.error('Failed to save article:', error);
    alert(error.response?.data?.message || t('admin.documentation.articles.save_error'));
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadCategories();
  if (isEdit.value) {
    loadArticle();
  }
});
</script>


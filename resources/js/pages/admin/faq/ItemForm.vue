<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">
        {{ isEdit ? t('admin.faq.items.edit_item') : t('admin.faq.items.create_item') }}
      </h1>
    </div>

    <form @submit.prevent="handleSubmit" class="bg-gray-800 rounded-xl border border-gray-700 p-6 space-y-6">
      <!-- Slug -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.faq.items.slug') }}
        </label>
        <input
          v-model="form.slug"
          type="text"
          class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <!-- Question (Multilanguage) -->
      <MultilanguageEditor
        v-model="form.question"
        label="Question"
        placeholder="FAQ question"
        required
        type="text"
      />

      <!-- Answer (Multilanguage) -->
      <MultilanguageEditor
        v-model="form.answer"
        label="Answer"
        placeholder="FAQ answer"
        required
        type="textarea"
        :rows="8"
      />

      <!-- Category -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.faq.items.category') }}
        </label>
        <Select
          v-model="form.category_id"
          :options="categoryOptions"
          :placeholder="t('admin.faq.items.no_category')"
        />
      </div>

      <!-- Order -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          {{ t('admin.faq.items.order') }}
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
          {{ t('admin.faq.items.published') }}
        </label>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4 pt-4 border-t border-gray-700">
        <router-link
          to="/admin/faq"
          class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700"
        >
          {{ t('admin.faq.items.cancel') }}
        </router-link>
        <button
          type="submit"
          :disabled="saving"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
        >
          {{ saving ? t('admin.faq.items.saving') : (isEdit ? t('admin.faq.items.update') : t('admin.faq.items.create')) }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { adminFaqApi } from '../../../services/api';
import MultilanguageEditor from '../../../components/admin/MultilanguageEditor.vue';
import Select from '../../../components/ui/Select.vue';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);
const categories = ref<any[]>([]);

const categoryOptions = computed(() => [
  { label: t('admin.faq.items.no_category'), value: null },
  ...categories.value.map(cat => ({ 
    label: cat.name?.en || cat.name, 
    value: cat.id 
  }))
]);

const form = ref({
  slug: '',
  question: {} as Record<string, string>,
  answer: {} as Record<string, string>,
  category_id: null as string | null,
  order: 0,
  is_published: false,
});

const loadItem = async () => {
  if (!isEdit.value) return;
  
  try {
    const response = await adminFaqApi.items.show(route.params.id as string);
    const item = response.data.data;
    form.value = {
      slug: item.slug || '',
      question: item.question || {},
      answer: item.answer || {},
      category_id: item.category_id,
      order: item.order || 0,
      is_published: item.is_published || false,
    };
  } catch (error) {
    console.error('Failed to load item:', error);
    router.push('/admin/faq');
  }
};

const loadCategories = async () => {
  try {
    const response = await adminFaqApi.categories.index();
    categories.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load categories:', error);
  }
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    if (isEdit.value) {
      await adminFaqApi.items.update(route.params.id as string, form.value);
    } else {
      await adminFaqApi.items.create(form.value);
    }
    router.push('/admin/faq');
  } catch (error: any) {
    console.error('Failed to save item:', error);
    alert(error.response?.data?.message || t('admin.faq.items.save_error'));
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadCategories();
  if (isEdit.value) {
    loadItem();
  }
});
</script>


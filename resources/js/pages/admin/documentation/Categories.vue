<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-white">{{ t('admin.documentation.categories.title') }}</h1>
        <p class="text-gray-400 mt-1">{{ t('admin.documentation.categories.description') }}</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t('admin.documentation.categories.create') }}
      </button>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
      <div class="space-y-4">
        <div
          v-for="category in categories"
          :key="category.id"
          class="flex items-center justify-between p-4 bg-gray-700/50 rounded-lg"
        >
          <div class="flex-1">
            <h3 class="text-white font-medium">
              {{ getCategoryName(category) }}
            </h3>
            <p v-if="category.description" class="text-sm text-gray-400 mt-1">
              {{ getCategoryDescription(category) }}
            </p>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-400">{{ category.articles_count || 0 }} {{ t('admin.documentation.categories.articles') }}</span>
            <button
              @click="editCategory(category)"
              class="text-indigo-400 hover:text-indigo-300"
            >
              {{ t('admin.documentation.categories.edit') }}
            </button>
            <button
              @click="deleteCategory(category.id)"
              class="text-red-400 hover:text-red-300"
            >
              {{ t('admin.documentation.categories.delete') }}
            </button>
          </div>
        </div>
        <div v-if="categories.length === 0" class="text-center py-8 text-gray-400">
          {{ t('admin.documentation.categories.no_categories') }}
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
      v-if="showCreateModal || editingCategory"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      @click.self="closeModal"
    >
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-white">
            {{ editingCategory ? t('admin.documentation.categories.edit_category') : t('admin.documentation.categories.create_category') }}
          </h2>
          <button
            @click="closeModal"
            class="text-gray-400 hover:text-white"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Slug -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
              {{ t('admin.documentation.categories.slug') }}
            </label>
            <input
              v-model="form.slug"
              type="text"
              class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :placeholder="t('admin.documentation.categories.slug_placeholder')"
            />
            <p class="mt-1 text-sm text-gray-400">{{ t('admin.documentation.categories.slug_hint') }}</p>
          </div>

          <!-- Name (Multilanguage) -->
          <MultilanguageEditor
            v-model="form.name"
            label="Name"
            placeholder="Category name"
            required
            type="text"
          />

          <!-- Description (Multilanguage) -->
          <MultilanguageEditor
            v-model="form.description"
            label="Description"
            placeholder="Category description"
            type="textarea"
            :rows="3"
          />

          <!-- Order -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
              {{ t('admin.documentation.categories.order') }}
            </label>
            <input
              v-model.number="form.order"
              type="number"
              min="0"
              class="block w-full px-4 py-2 border border-gray-600 rounded-lg bg-gray-700/50 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <!-- Active -->
          <div class="flex items-center">
            <input
              v-model="form.is_active"
              type="checkbox"
              id="is_active"
              class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500"
            />
            <label for="is_active" class="ml-2 text-sm text-gray-300">
              {{ t('admin.documentation.categories.active') }}
            </label>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-4 pt-4 border-t border-gray-700">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700"
            >
              {{ t('admin.documentation.categories.cancel') }}
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
            >
              {{ saving ? t('admin.documentation.categories.saving') : (editingCategory ? t('admin.documentation.categories.update') : t('admin.documentation.categories.create')) }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminDocumentationApi } from '../../../services/api';
import MultilanguageEditor from '../../../components/admin/MultilanguageEditor.vue';

const { t } = useI18n();

const categories = ref<any[]>([]);
const showCreateModal = ref(false);
const editingCategory = ref<any>(null);
const saving = ref(false);

const form = ref({
  slug: '',
  name: {} as Record<string, string>,
  description: {} as Record<string, string>,
  order: 0,
  is_active: true,
});

const getCategoryName = (category: any): string => {
  if (typeof category.name === 'string') return category.name;
  if (typeof category.name === 'object' && category.name !== null) {
    return category.name.en || category.name[Object.keys(category.name)[0]] || '';
  }
  return '';
};

const getCategoryDescription = (category: any): string => {
  if (!category.description) return '';
  if (typeof category.description === 'string') return category.description;
  if (typeof category.description === 'object' && category.description !== null) {
    return category.description.en || category.description[Object.keys(category.description)[0]] || '';
  }
  return '';
};

const loadCategories = async () => {
  try {
    const response = await adminDocumentationApi.categories.index();
    categories.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load categories:', error);
  }
};

const editCategory = (category: any) => {
  editingCategory.value = category;
  form.value = {
    slug: category.slug || '',
    name: category.name || {},
    description: category.description || {},
    order: category.order || 0,
    is_active: category.is_active !== undefined ? category.is_active : true,
  };
};

const closeModal = () => {
  showCreateModal.value = false;
  editingCategory.value = null;
  form.value = {
    slug: '',
    name: {},
    description: {},
    order: 0,
    is_active: true,
  };
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    if (editingCategory.value) {
      await adminDocumentationApi.categories.update(editingCategory.value.id, form.value);
    } else {
      await adminDocumentationApi.categories.create(form.value);
    }
    closeModal();
    // Wait a bit to ensure DB transaction is committed
    await new Promise(resolve => setTimeout(resolve, 100));
    await loadCategories();
  } catch (error: any) {
    console.error('Failed to save category:', error);
    alert(error.response?.data?.message || t('admin.documentation.categories.save_error'));
  } finally {
    saving.value = false;
  }
};

const deleteCategory = async (id: string) => {
  if (!confirm(t('admin.documentation.categories.confirm_delete'))) return;
  try {
    await adminDocumentationApi.categories.delete(id);
    loadCategories();
  } catch (error: any) {
    console.error('Failed to delete category:', error);
    alert(error.response?.data?.message || t('admin.documentation.categories.delete_error'));
  }
};

onMounted(() => {
  loadCategories();
});
</script>


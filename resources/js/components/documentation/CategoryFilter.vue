<template>
  <select
    :value="modelValue || ''"
    class="block w-full sm:w-auto px-4 py-2.5 border border-gray-700 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
    @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value || null); $emit('filter', ($event.target as HTMLSelectElement).value || null)"
  >
    <option value="">{{ t('documentation.all_categories') }}</option>
    <option
      v-for="category in categories"
      :key="category.id"
      :value="category.id"
    >
      {{ category.name }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
  modelValue: string | null;
  categories: Array<{ id: string; name: string }>;
}>();

defineEmits<{
  'update:modelValue': [value: string | null];
  filter: [categoryId: string | null];
}>();
</script>


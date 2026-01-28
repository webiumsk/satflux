<template>
  <Select
    :model-value="modelValue"
    :options="categoryOptions"
    :placeholder="t('documentation.all_categories')"
    @update:model-value="$emit('update:modelValue', $event); $emit('filter', $event)"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Select from '../ui/Select.vue';

const { t } = useI18n();

const props = defineProps<{
  modelValue: string | null;
  categories: Array<{ id: string; name: string }>;
}>();

defineEmits<{
  'update:modelValue': [value: string | null];
  filter: [categoryId: string | null];
}>();

const categoryOptions = computed(() => [
  { label: t('documentation.all_categories'), value: '' },
  ...props.categories.map(cat => ({ label: cat.name, value: cat.id }))
]);
</script>


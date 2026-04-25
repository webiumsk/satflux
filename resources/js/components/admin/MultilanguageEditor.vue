<template>
  <div class="space-y-4">
    <!-- Language Tabs -->
    <div class="border-b border-gray-700">
      <nav class="-mb-px flex space-x-4">
        <button
          type="button"
          v-for="locale in supportedLocales"
          :key="locale.code"
          :class="[
            'py-2 px-4 border-b-2 font-medium text-sm transition-colors',
            activeLocale === locale.code
              ? 'border-indigo-500 text-indigo-400'
              : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-600'
          ]"
          @click="activeLocale = locale.code"
        >
          {{ locale.name }}
        </button>
      </nav>
    </div>

    <!-- Editor for Active Locale -->
    <div v-for="locale in supportedLocales" :key="locale.code">
      <div v-show="activeLocale === locale.code" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ label }} ({{ locale.name }})
            <span v-if="required" class="text-red-400">*</span>
          </label>
          <textarea
            v-if="type === 'textarea'"
            :value="modelValue[locale.code] || ''"
            :rows="rows"
            :placeholder="`${placeholder} (${locale.name})`"
            class="block w-full px-3 py-2 border border-gray-700 bg-gray-800 text-white placeholder-gray-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            @input="updateValue(locale.code, ($event.target as HTMLTextAreaElement).value)"
          ></textarea>
          <input
            v-else
            :value="modelValue[locale.code] || ''"
            type="text"
            :placeholder="`${placeholder} (${locale.name})`"
            class="block w-full px-3 py-2 border border-gray-700 bg-gray-800 text-white placeholder-gray-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            @input="updateValue(locale.code, ($event.target as HTMLInputElement).value)"
          />
          <p v-if="hint" class="mt-1 text-sm text-gray-400">
            {{ hint }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const supportedLocales = [
  { code: 'en', name: 'English' },
  { code: 'sk', name: 'Slovenčina' },
  { code: 'es', name: 'Español' },
  { code: 'cz', name: 'Čeština' },
  { code: 'de', name: 'Deutsch' },
  { code: 'fr', name: 'Français' },
  { code: 'hu', name: 'Magyar' },
  { code: 'pl', name: 'Polski' },
];

const props = defineProps<{
  modelValue: Record<string, string>;
  label: string;
  placeholder?: string;
  hint?: string;
  required?: boolean;
  type?: 'text' | 'textarea';
  rows?: number;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, string>];
}>();

const activeLocale = ref('en');

const updateValue = (locale: string, value: string) => {
  const updated = { ...props.modelValue };
  updated[locale] = value;
  emit('update:modelValue', updated);
};
</script>


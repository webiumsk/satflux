<template>
  <div class="relative" v-click-outside="closeDropdown">
    <button
      @click="toggleDropdown"
      class="flex items-center space-x-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
      :aria-label="t('common.language')"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
      </svg>
      <span class="hidden md:inline">{{ currentLocaleName }}</span>
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95 translate-y-2"
      enter-to-class="transform opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100 translate-y-0"
      leave-to-class="transform opacity-0 scale-95 translate-y-2"
    >
      <div
        v-if="showDropdown"
        class="absolute right-0 mt-2 w-48 rounded-xl shadow-2xl bg-gray-800 border border-gray-700 ring-1 ring-black ring-opacity-5 z-50 overflow-hidden"
      >
        <div class="py-1">
          <button
            v-for="locale in availableLocales"
            :key="locale.code"
            @click="changeLocale(locale.code)"
            class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors flex items-center justify-between"
            :class="{ 'bg-gray-700 text-white': currentLocale === locale.code }"
          >
            <span>{{ locale.name }}</span>
            <svg
              v-if="currentLocale === locale.code"
              class="w-4 h-4 text-indigo-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale as setI18nLocale, supportedLocales, type SupportedLocale } from '../i18n';
import api, { setLocale as setApiLocale } from '../services/api';

const { t, locale } = useI18n();
const showDropdown = ref(false);

const availableLocales = [
  { code: 'en', name: 'English' },
  { code: 'sk', name: 'Slovenčina' },
  { code: 'es', name: 'Español' },
].sort((a, b) => a.name.localeCompare(b.name));

const currentLocale = computed(() => locale.value as SupportedLocale);

const currentLocaleName = computed(() => {
  const localeObj = availableLocales.find(l => l.code === currentLocale.value);
  return localeObj?.name || 'English';
});

function toggleDropdown() {
  showDropdown.value = !showDropdown.value;
}

function closeDropdown() {
  showDropdown.value = false;
}

async function changeLocale(localeCode: SupportedLocale) {
  if (!supportedLocales.includes(localeCode)) {
    console.warn(`Locale ${localeCode} is not supported`);
    return;
  }

  try {
    // Update localStorage first (immediate UI update)
    localStorage.setItem('locale', localeCode);
    
    // Update frontend locale immediately
    await setI18nLocale(localeCode);
    
    // Update backend session
    await setApiLocale(localeCode);
    
    closeDropdown();
    
    // Small delay to ensure session is saved, then reload
    // This ensures backend middleware will use the correct locale
    setTimeout(() => {
      window.location.reload();
    }, 200);
  } catch (error) {
    console.error('Failed to change locale:', error);
    // Even if backend fails, keep the frontend change
  }
}

// Click outside directive
const vClickOutside = {
  mounted(el: HTMLElement, binding: any) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target as Node))) {
        binding.value();
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el: HTMLElement) {
    if (el.clickOutsideEvent) {
      document.removeEventListener('click', el.clickOutsideEvent);
    }
  },
};
</script>


<template>
  <div class="relative select-container" ref="containerRef">
    <!-- Trigger -->
    <div 
      @click="toggleDropdown"
      class="relative group cursor-pointer"
    >
      <div
        :class="[
          'flex items-center justify-between w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white transition-all cursor-pointer select-none min-h-[42px]',
          isOpen ? 'ring-2 ring-indigo-500/50 border-indigo-500' : 'hover:border-gray-500',
          error ? 'border-red-500/50 ring-1 ring-red-500/20' : ''
        ]"
      >
        <span :class="selectedOption ? 'text-white' : 'text-gray-500'">
          {{ selectedOption ? selectedOption.label : placeholder }}
        </span>
        <svg 
          class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition-transform duration-200"
          :class="{ 'rotate-180': isOpen }"
          fill="none" viewBox="0 0 24 24" stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95 -translate-y-2"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 scale-100 translate-y-0"
      leave-to-class="opacity-0 scale-95 -translate-y-2"
    >
      <div 
        v-if="isOpen"
        class="absolute z-[110] mt-2 w-full bg-gray-800/90 backdrop-blur-xl border border-gray-700 shadow-2xl rounded-2xl overflow-hidden py-2 max-h-60 overflow-y-auto custom-scrollbar"
      >
        <div v-if="options.length === 0" class="px-4 py-3 text-sm text-gray-500 text-center">
          No options available
        </div>
        <template v-else>
          <div
            v-for="option in options"
            :key="option.value"
            @click="selectOption(option)"
            :class="[
              'px-4 py-2.5 text-sm cursor-pointer transition-colors flex items-center justify-between',
              modelValue === option.value 
                ? 'bg-indigo-600/20 text-indigo-400 font-bold' 
                : 'text-gray-300 hover:bg-white/5 hover:text-white'
            ]"
          >
            <span>{{ option.label }}</span>
            <svg v-if="modelValue === option.value" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </template>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

interface Option {
  label: string;
  value: any;
}

interface Props {
  modelValue: any;
  options: Option[];
  placeholder?: string;
  error?: string | boolean;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select an option...',
  error: false
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);

const selectedOption = computed(() => {
  return props.options.find(opt => opt.value === props.modelValue);
});

function toggleDropdown() {
  isOpen.value = !isOpen.value;
}

function selectOption(option: Option) {
  emit('update:modelValue', option.value);
  emit('change', option.value);
  isOpen.value = false;
}

// Close on outside click
const clickOutsideHandler = (event: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', clickOutsideHandler);
});

onUnmounted(() => {
  document.removeEventListener('click', clickOutsideHandler);
});
</script>

<style scoped>
.select-container {
  font-family: inherit;
}
</style>

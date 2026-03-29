<template>
  <div class="relative select-container" ref="containerRef">
    <!-- Trigger -->
    <div
      ref="triggerRef"
      @click="!disabled && toggleDropdown()"
      :class="['relative group', disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer']"
    >
      <div
        :class="[
          'flex items-center justify-between w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white transition-all select-none min-h-[42px]',
          !disabled && 'cursor-pointer',
          isOpen ? 'ring-2 ring-indigo-500/50 border-indigo-500' : !disabled && 'hover:border-gray-500',
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

    <!-- Portal avoids clipping by overflow-hidden / overflow-y-auto ancestors (e.g. layout + scroll areas). -->
    <Teleport to="body">
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
          ref="dropdownRef"
          class="fixed z-[200] bg-gray-800/90 backdrop-blur-xl border border-gray-700 shadow-2xl rounded-2xl overflow-hidden py-2 max-h-60 overflow-y-auto custom-scrollbar"
          :style="dropdownStyle"
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
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';

interface Option {
  label: string;
  value: any;
}

interface Props {
  modelValue: any;
  options: Option[];
  placeholder?: string;
  error?: string | boolean;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select an option...',
  error: false,
  disabled: false
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);

const dropdownPlacement = ref({ top: 0, left: 0, width: 0 });
const dropdownStyle = computed(() => ({
  top: `${dropdownPlacement.value.top}px`,
  left: `${dropdownPlacement.value.left}px`,
  width: `${dropdownPlacement.value.width}px`,
}));

const DROPDOWN_GAP_PX = 8;

function updateDropdownPosition() {
  const el = triggerRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  dropdownPlacement.value = {
    top: rect.bottom + DROPDOWN_GAP_PX,
    left: rect.left,
    width: rect.width,
  };
}

const selectedOption = computed(() => {
  return props.options.find(opt => opt.value === props.modelValue);
});

function toggleDropdown() {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
}

function selectOption(option: Option) {
  emit('update:modelValue', option.value);
  emit('change', option.value);
  isOpen.value = false;
}

// Close on outside click (dropdown is teleported, so check both roots)
const clickOutsideHandler = (event: MouseEvent) => {
  const target = event.target as Node;
  if (containerRef.value?.contains(target)) return;
  if (dropdownRef.value?.contains(target)) return;
  isOpen.value = false;
};

watch(isOpen, async (open: boolean) => {
  if (!open) return;
  await nextTick();
  updateDropdownPosition();
});

onMounted(() => {
  document.addEventListener('click', clickOutsideHandler);
  window.addEventListener('resize', updateDropdownPosition);
  document.addEventListener('scroll', updateDropdownPosition, true);
});

onUnmounted(() => {
  document.removeEventListener('click', clickOutsideHandler);
  window.removeEventListener('resize', updateDropdownPosition);
  document.removeEventListener('scroll', updateDropdownPosition, true);
});
</script>

<style scoped>
.select-container {
  font-family: inherit;
}
</style>

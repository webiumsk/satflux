<template>
  <div class="relative inline-flex items-center">
    <button
      type="button"
      @click="handleClick"
      @mouseenter="show()"
      @mouseleave="hide()"
      @touchstart="handleTouch"
      class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 bg-white text-gray-500 hover:text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 transition-colors"
      :aria-label="ariaLabel || 'Information'"
      :aria-expanded="isVisible"
    >
      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Tooltip -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isVisible"
        ref="tooltipRef"
        :class="[
          'absolute z-50 px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-md shadow-lg',
          'max-w-xs w-max',
          position === 'top' ? 'bottom-full left-1/2 -translate-x-1/2 mb-2' : '',
          position === 'bottom' ? 'top-full left-1/2 -translate-x-1/2 mt-2' : '',
          position === 'left' ? 'right-full top-1/2 -translate-y-1/2 mr-2' : '',
          position === 'right' ? 'left-full top-1/2 -translate-y-1/2 ml-2' : '',
        ]"
        role="tooltip"
      >
        <slot>
          {{ text }}
        </slot>
        <!-- Arrow -->
        <div
          :class="[
            'absolute w-2 h-2 bg-white border border-gray-200 transform rotate-45',
            position === 'top' ? 'bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2 border-r-0 border-b-0' : '',
            position === 'bottom' ? 'top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 border-l-0 border-t-0' : '',
            position === 'left' ? 'right-0 top-1/2 -translate-y-1/2 translate-x-1/2 border-t-0 border-l-0' : '',
            position === 'right' ? 'left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 border-b-0 border-r-0' : '',
          ]"
        ></div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
  text?: string;
  position?: 'top' | 'bottom' | 'left' | 'right';
  showOnHover?: boolean;
  ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
  text: '',
  position: 'top',
  showOnHover: false,
});

const isVisible = ref(false);
const tooltipRef = ref<HTMLElement | null>(null);

function show() {
  isVisible.value = true;
}

function hide() {
  isVisible.value = false;
}

function handleClick() {
  // Toggle on click for touch devices
  isVisible.value = !isVisible.value;
}

let touchTimer: number | null = null;

function handleTouch() {
  // On touch devices, show on touch and hide after delay
  show();
  if (touchTimer) {
    clearTimeout(touchTimer);
  }
  touchTimer = window.setTimeout(() => {
    hide();
  }, 5000); // Hide after 5 seconds on touch devices
}

function handleClickOutside(event: MouseEvent) {
  if (tooltipRef.value && !tooltipRef.value.contains(event.target as Node)) {
    const button = (event.target as HTMLElement).closest('button');
    if (!button || !button.contains(event.target as Node)) {
      hide();
    }
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('touchstart', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('touchstart', handleClickOutside);
  if (touchTimer) {
    clearTimeout(touchTimer);
  }
});
</script>


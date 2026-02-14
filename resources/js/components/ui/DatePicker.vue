<template>
  <div class="relative date-picker-container" ref="containerRef">
    <!-- Input Trigger -->
    <div 
      @click="togglePicker"
      class="relative group cursor-pointer"
    >
      <input
        type="text"
        :value="formattedValue"
        readonly
        :placeholder="placeholder"
        :class="[
          'block w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all cursor-pointer select-none',
          error ? 'border-red-500/50 ring-1 ring-red-500/20' : ''
        ]"
      />
      <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 group-hover:text-gray-400 transition-colors pointer-events-none">
        <svg v-if="type === 'date'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
    </div>

    <!-- Dropdown Picker -->
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
        class="absolute z-[100] mt-2 p-4 bg-gray-800/90 backdrop-blur-xl border border-gray-700 shadow-2xl rounded-2xl min-w-[280px]"
        :class="position === 'right' ? 'right-0' : 'left-0'"
      >
        <!-- Calendar Header -->
        <div class="flex items-center justify-between mb-4">
          <button @click="prevMonth" type="button" :disabled="isPrevMonthDisabled" :class="['p-1 rounded-lg transition-colors', isPrevMonthDisabled ? 'text-gray-600 cursor-not-allowed opacity-50' : 'hover:bg-white/5 text-gray-400 hover:text-white']">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          
          <div class="text-sm font-bold text-white uppercase tracking-wider">
            {{ monthNames[viewDate.getMonth()] }} {{ viewDate.getFullYear() }}
          </div>

          <button @click="nextMonth" type="button" class="p-1 hover:bg-white/5 rounded-lg text-gray-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>

        <!-- Days Selection -->
        <div class="grid grid-cols-7 gap-1 mb-4">
          <template v-for="day in weekDays" :key="day">
            <div class="h-8 flex items-center justify-center text-[10px] font-black text-gray-500 uppercase tracking-tighter">
              {{ day }}
            </div>
          </template>

          <template v-for="(day, idx) in calendarDays" :key="idx">
            <div 
              @click="day.current && !isDayDisabled(day.date) ? selectDate(day.date) : null"
              :class="[
                'h-8 w-8 flex items-center justify-center rounded-lg text-xs font-medium transition-all duration-200',
                day.current && !isDayDisabled(day.date) ? 'text-gray-300 hover:bg-indigo-600/20 hover:text-white cursor-pointer' : 'text-gray-600 cursor-default opacity-20',
                day.current && isDayDisabled(day.date) ? 'opacity-40 cursor-not-allowed' : '',
                isToday(day.date) && !isDayDisabled(day.date) ? 'border border-indigo-500/50' : '',
                isSelected(day.date) ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : ''
              ]"
            >
              {{ day.date.getDate() }}
            </div>
          </template>
        </div>

        <!-- Time Picker (if enabled): editable inputs + arrow buttons -->
        <div v-if="enableTime" class="border-t border-gray-700/50 pt-4 mt-2">
            <div class="flex items-center justify-center gap-2">
                <div class="flex flex-col items-center">
                    <button @click="adjustTime('h', 1)" type="button" class="p-1 text-gray-500 hover:text-white" aria-label="Hour up"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg></button>
                    <input
                      type="number"
                      min="0"
                      max="23"
                      :value="timeData.h"
                      @input="onHourInput"
                      class="w-12 text-center bg-gray-900 border border-gray-700 px-2 py-1.5 rounded-lg font-mono text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button @click="adjustTime('h', -1)" type="button" class="p-1 text-gray-500 hover:text-white" aria-label="Hour down"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg></button>
                </div>
                <div class="text-gray-600 font-bold pt-5">:</div>
                <div class="flex flex-col items-center">
                    <button @click="adjustTime('m', 1)" type="button" class="p-1 text-gray-500 hover:text-white" aria-label="Minute up"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg></button>
                    <input
                      type="number"
                      min="0"
                      max="59"
                      :value="timeData.m"
                      @input="onMinuteInput"
                      class="w-12 text-center bg-gray-900 border border-gray-700 px-2 py-1.5 rounded-lg font-mono text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button @click="adjustTime('m', -1)" type="button" class="p-1 text-gray-500 hover:text-white" aria-label="Minute down"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg></button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between gap-4 mt-4 pt-4 border-t border-gray-700/50">
             <button 
                @click="clearValue" 
                type="button"
                class="text-[10px] font-bold text-gray-500 hover:text-red-400 uppercase tracking-widest transition-colors"
            >
                Clear
            </button>
            <button 
                @click="isOpen = false" 
                type="button"
                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow-lg shadow-indigo-600/20 transition-all active:scale-95"
            >
                Apply
            </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, reactive } from 'vue';

interface Props {
  modelValue: string | null | number;
  placeholder?: string;
  type?: 'date' | 'datetime';
  error?: string | boolean;
  position?: 'left' | 'right';
  /** Disallow selecting dates before this. Use "today" or ISO date string. */
  minDate?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select date...',
  type: 'date',
  error: false,
  position: 'left',
  minDate: null
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const viewDate = ref(new Date());
const enableTime = computed(() => props.type === 'datetime');

const minDateValue = computed(() => {
  if (!props.minDate) return null;
  const d = props.minDate === 'today' ? new Date() : new Date(props.minDate);
  if (isNaN(d.getTime())) return null;
  d.setHours(0, 0, 0, 0);
  return d;
});

const timeData = reactive({
    h: 0,
    m: 0
});

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

// Selected internal date
const selectedDate = computed(() => {
    if (!props.modelValue) return null;
    let d: Date;
    if (typeof props.modelValue === 'number') {
        // Assume seconds for our fintech app often handling UNIX TS
        d = new Date(props.modelValue < 10000000000 ? props.modelValue * 1000 : props.modelValue);
    } else {
        d = new Date(props.modelValue);
    }
    return isNaN(d.getTime()) ? null : d;
});

// Update time data when value changes
watch(() => selectedDate.value, (newVal) => {
    if (newVal) {
        timeData.h = newVal.getHours();
        timeData.m = newVal.getMinutes();
        viewDate.value = new Date(newVal);
    } else if (minDateValue.value && enableTime.value) {
        const now = new Date();
        timeData.h = now.getHours();
        timeData.m = now.getMinutes();
    }
}, { immediate: true });

const formattedValue = computed(() => {
  if (!selectedDate.value) return '';
  
  const d = selectedDate.value;
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  
  if (props.type === 'datetime') {
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
  }
  
  return `${day}.${month}.${year}`;
});

const calendarDays = computed(() => {
  const days = [];
  const year = viewDate.value.getFullYear();
  const month = viewDate.value.getMonth();
  
  const firstDayOfMonth = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  
  // Previous month padding
  const prevMonthLastDay = new Date(year, month, 0).getDate();
  for (let i = firstDayOfMonth - 1; i >= 0; i--) {
    days.push({
      date: new Date(year, month - 1, prevMonthLastDay - i),
      current: false
    });
  }
  
  // Current month
  for (let i = 1; i <= daysInMonth; i++) {
    days.push({
      date: new Date(year, month, i),
      current: true
    });
  }
  
  // Next month padding
  const totalCells = 42;
  const nextMonthPadding = totalCells - days.length;
  for (let i = 1; i <= nextMonthPadding; i++) {
    days.push({
      date: new Date(year, month + 1, i),
      current: false
    });
  }
  
  return days;
});

function togglePicker() {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    if (selectedDate.value) {
      viewDate.value = new Date(selectedDate.value);
    } else if (minDateValue.value) {
      const d = enableTime.value ? new Date() : new Date(minDateValue.value);
      viewDate.value = new Date(d.getFullYear(), d.getMonth(), d.getDate());
      if (enableTime.value) {
        timeData.h = d.getHours();
        timeData.m = d.getMinutes();
      }
    }
  }
}

function prevMonth() {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1);
}

function nextMonth() {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1);
}

const isPrevMonthDisabled = computed(() => {
  if (!minDateValue.value) return false;
  const v = viewDate.value;
  return v.getFullYear() < minDateValue.value.getFullYear() ||
    (v.getFullYear() === minDateValue.value.getFullYear() && v.getMonth() <= minDateValue.value.getMonth());
});

function isDayDisabled(date: Date) {
  if (!minDateValue.value) return false;
  const dayStart = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  return dayStart < minDateValue.value;
}

function selectDate(date: Date) {
  if (isDayDisabled(date)) return;
  const newDate = new Date(date);
  if (enableTime.value) {
      newDate.setHours(timeData.h);
      newDate.setMinutes(timeData.m);
      newDate.setSeconds(0);
  } else {
      newDate.setHours(0, 0, 0, 0);
  }
  if (minDateValue.value && newDate < minDateValue.value && enableTime.value) {
    newDate.setTime(minDateValue.value.getTime());
    timeData.h = newDate.getHours();
    timeData.m = newDate.getMinutes();
  }
  updateValue(newDate);
  if (!enableTime.value) {
      isOpen.value = false;
  }
}

function adjustTime(type: 'h' | 'm', amount: number) {
    if (type === 'h') {
        timeData.h = (timeData.h + amount + 24) % 24;
    } else {
        timeData.m = (timeData.m + amount + 60) % 60;
    }
    emitTimeFromTimeData();
}

function onHourInput(e: Event) {
  const v = parseInt((e.target as HTMLInputElement).value, 10);
  if (!Number.isNaN(v)) {
    timeData.h = Math.max(0, Math.min(23, v));
    emitTimeFromTimeData();
  }
}

function onMinuteInput(e: Event) {
  const v = parseInt((e.target as HTMLInputElement).value, 10);
  if (!Number.isNaN(v)) {
    timeData.m = Math.max(0, Math.min(59, v));
    emitTimeFromTimeData();
  }
}

function emitTimeFromTimeData() {
  const base = selectedDate.value || minDateValue.value || new Date();
  const newDate = new Date(base);
  newDate.setHours(timeData.h);
  newDate.setMinutes(timeData.m);
  newDate.setSeconds(0, 0);
  if (minDateValue.value && newDate < minDateValue.value) {
    newDate.setTime(minDateValue.value.getTime());
    timeData.h = newDate.getHours();
    timeData.m = newDate.getMinutes();
  }
  updateValue(newDate);
}

function isToday(date: Date) {
  const today = new Date();
  return date.toDateString() === today.toDateString();
}

function isSelected(date: Date) {
  return selectedDate.value?.toDateString() === date.toDateString();
}

function clearValue() {
  emit('update:modelValue', '');
  emit('change', '');
  isOpen.value = false;
}

function updateValue(date: Date) {
    let val: string;
    if (props.type === 'datetime') {
        // Return ISO string or similar for backend compatibility
        // Let's use format YYYY-MM-DDTHH:mm to match native datetime-local for least breaking change
        const pad = (n: number) => String(n).padStart(2, '0');
        val = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    } else {
        // YYYY-MM-DD for native date compatibility
        const pad = (n: number) => String(n).padStart(2, '0');
        val = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }
    
    emit('update:modelValue', val);
    emit('change', val);
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
.date-picker-container {
  font-family: inherit;
}
</style>

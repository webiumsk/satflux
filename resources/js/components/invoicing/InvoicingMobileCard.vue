<template>
  <article
    class="invoicing-mobile-card"
    :class="{ 'invoicing-mobile-card--selected': selected }"
    @click="emit('open')"
  >
    <div v-if="selectable" class="flex items-start gap-3">
      <input
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
        :checked="selected"
        @click.stop
        @change="emit('toggle-select')"
      />
      <div class="min-w-0 flex-1">
        <slot />
      </div>
      <div v-if="$slots.actions" class="shrink-0" @click.stop>
        <slot name="actions" />
      </div>
    </div>
    <template v-else>
      <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
          <slot />
        </div>
        <div v-if="$slots.actions" class="shrink-0" @click.stop>
          <slot name="actions" />
        </div>
      </div>
    </template>
  </article>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    selectable?: boolean;
    selected?: boolean;
  }>(),
  { selectable: false, selected: false }
);

const emit = defineEmits<{
  open: [];
  'toggle-select': [];
}>();
</script>

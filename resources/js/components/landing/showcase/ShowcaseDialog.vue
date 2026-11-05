<template>
  <dialog
    ref="dialog"
    :id="id"
    :aria-labelledby="labelledby"
    :aria-describedby="describedby"
    @click="onBackdropClick"
    @close="onClose"
  >
    <slot :close="() => close()" />
  </dialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
defineProps<{ id: string; labelledby: string; describedby?: string }>();
const emit = defineEmits<{ closed: [] }>();
const dialog = ref<HTMLDialogElement>();
let opener: HTMLElement | null = null;
let originalOverflow: string | null = null;
function open(origin: HTMLElement | null): void {
  if (!dialog.value || dialog.value.open)
    return;
  opener = origin;
  originalOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  dialog.value.showModal();
}
function restore(): void {
  if (originalOverflow !== null) {
    document.body.style.overflow = originalOverflow;
    originalOverflow = null;
  }
  if (opener?.isConnected)
    opener.focus({ preventScroll: true });
  opener = null;
}
function close(restoreFocus = true): void {
  if (!restoreFocus)
    opener = null;
  dialog.value?.close();
}
function onClose(): void { restore(); emit('closed'); }
function onBackdropClick(event: MouseEvent): void {
  if (event.target !== dialog.value || !dialog.value)
    return;
  const rect = dialog.value.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)
    close();
}
onBeforeUnmount(() => { dialog.value?.close(); restore(); });
defineExpose({ open, close });
</script>

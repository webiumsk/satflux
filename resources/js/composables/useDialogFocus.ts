import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue';
/** Keep keyboard focus inside an open dialog and return it to its trigger. */
export function useDialogFocus(root: Ref<HTMLElement | null>, isOpen: () => boolean, close: () => void) {
  let trigger: HTMLElement | null = null;
  const focusable = () => Array.from(root.value?.querySelectorAll<HTMLElement>(
    'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]',
  ) ?? []).filter((element) => !element.closest('[hidden], [aria-hidden="true"]'));
  const restoreFocus = () => { trigger?.focus(); trigger = null; };
  watch(isOpen, async (open) => {
    if (!open) { restoreFocus(); return; }
    trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    if (isOpen()) (focusable()[0] ?? root.value)?.focus();
  }, { immediate: true });
  function onDialogKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    if (event.key !== 'Tab') return;
    const elements = focusable();
    const first = elements[0]; const last = elements[elements.length - 1];
    if (!first) { event.preventDefault(); root.value?.focus(); return; }
    if (event.shiftKey && (document.activeElement === first || document.activeElement === root.value)) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  }
  onBeforeUnmount(restoreFocus);
  return { onDialogKeydown };
}

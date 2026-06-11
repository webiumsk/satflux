<template>
  <div ref="rootRef" class="relative">
    <button
      ref="buttonRef"
      type="button"
      class="invoicing-mobile-icon-btn"
      :title="t('invoicing.mobile_more_actions')"
      :aria-expanded="open"
      @click.stop="toggle"
    >
      <InvoicingIcons name="more" />
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="menuRef"
        class="invoicing-dropdown invoicing-row-actions-menu fixed z-50 min-w-[11rem] max-w-[min(16rem,calc(100vw-1rem))]"
        :style="menuStyle"
        @click.stop
      >
        <slot />
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InvoicingIcons from './icons/InvoicingIcons.vue';

const { t } = useI18n();
const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const buttonRef = ref<HTMLButtonElement | null>(null);
const menuRef = ref<HTMLElement | null>(null);
const menuStyle = ref<{ top: string; left: string }>({ top: '0px', left: '0px' });

function updateMenuPosition() {
  const button = buttonRef.value;
  if (!button) {
    return;
  }

  const rect = button.getBoundingClientRect();
  const viewportPadding = 8;
  const menuWidth = menuRef.value?.offsetWidth ?? 176;
  let left = rect.right - menuWidth;

  if (left < viewportPadding) {
    left = viewportPadding;
  }
  if (left + menuWidth > window.innerWidth - viewportPadding) {
    left = window.innerWidth - viewportPadding - menuWidth;
  }

  menuStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${Math.max(viewportPadding, left)}px`,
  };
}

async function toggle() {
  open.value = !open.value;
  if (open.value) {
    await nextTick();
    updateMenuPosition();
  }
}

function close() {
  open.value = false;
}

function onDocumentClick(event: MouseEvent) {
  if (!open.value) {
    return;
  }
  const target = event.target;
  if (target instanceof Node) {
    if (rootRef.value?.contains(target) || menuRef.value?.contains(target)) {
      return;
    }
  }
  close();
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    close();
  }
}

function onViewportChange() {
  if (open.value) {
    updateMenuPosition();
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('resize', onViewportChange);
  window.addEventListener('scroll', onViewportChange, true);
});

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('resize', onViewportChange);
  window.removeEventListener('scroll', onViewportChange, true);
});

defineExpose({ close });
</script>

<style scoped>
.invoicing-row-actions-menu {
  left: auto;
  right: auto;
  top: auto;
  margin-top: 0;
}
</style>

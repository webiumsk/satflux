import { defineStore } from 'pinia';
import { ref } from 'vue';

export type FlashType = 'success' | 'error' | 'warning';

const HIDE_DELAY_MS = 8000;

export const useFlashStore = defineStore('flash', () => {
    const message = ref('');
    const type = ref<FlashType>('success');
    const show = ref(false);
    let timeout: ReturnType<typeof setTimeout> | null = null;

    function success(msg: string) {
        message.value = msg;
        type.value = 'success';
        show.value = true;
        scheduleHide();
    }

    function error(msg: string) {
        message.value = msg;
        type.value = 'error';
        show.value = true;
        scheduleHide();
    }

    function warning(msg: string) {
        message.value = msg;
        type.value = 'warning';
        show.value = true;
        scheduleHide();
    }

    function clear() {
        show.value = false;
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
    }

    function scheduleHide() {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => {
            show.value = false;
            timeout = null;
        }, HIDE_DELAY_MS);
    }

    function pauseAutoHide() {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
    }

    function resumeAutoHide() {
        if (show.value && !timeout) {
            scheduleHide();
        }
    }

    return {
        message,
        type,
        show,
        success,
        error,
        warning,
        clear,
        pauseAutoHide,
        resumeAutoHide,
    };
});

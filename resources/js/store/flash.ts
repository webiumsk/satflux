import { defineStore } from 'pinia';
import { ref } from 'vue';

export type FlashType = 'success' | 'error' | 'warning';

const HIDE_DELAY_MS = 8000;

export const useFlashStore = defineStore('flash', () => {
    const message = ref('');
    /** i18n key rendered by FlashMessage.vue - lets non-component code (axios
     *  interceptor) flash localized messages without importing i18n. */
    const messageKey = ref<string | null>(null);
    const type = ref<FlashType>('success');
    const show = ref(false);
    let timeout: ReturnType<typeof setTimeout> | null = null;

    function success(msg: string) {
        message.value = msg;
        messageKey.value = null;
        type.value = 'success';
        show.value = true;
        scheduleHide();
    }

    function error(msg: string) {
        message.value = msg;
        messageKey.value = null;
        type.value = 'error';
        show.value = true;
        scheduleHide();
    }

    function errorKey(key: string) {
        message.value = '';
        messageKey.value = key;
        type.value = 'error';
        show.value = true;
        scheduleHide();
    }

    function warning(msg: string) {
        message.value = msg;
        messageKey.value = null;
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
        messageKey,
        type,
        show,
        success,
        error,
        errorKey,
        warning,
        clear,
        pauseAutoHide,
        resumeAutoHide,
    };
});

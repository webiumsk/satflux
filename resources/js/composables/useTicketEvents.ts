import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTicketsStore } from '../store/tickets';
import { getApiErrorMessage } from './useApiError';

export function useTicketEvents(storeId: () => string) {
    const { t } = useI18n();
    const ticketsStore = useTicketsStore();
    const events = ref<Awaited<ReturnType<typeof ticketsStore.fetchEvents>>>([]);
    const loading = ref(false);
    const error = ref('');

    async function loadEvents() {
        loading.value = true;
        error.value = '';
        try {
            events.value = await ticketsStore.fetchEvents(storeId());
        } catch (err: unknown) {
            error.value = getApiErrorMessage(err, t('common.error'));
            events.value = [];
        } finally {
            loading.value = false;
        }
    }

    return {
        events,
        loading,
        error,
        loadEvents,
    };
}

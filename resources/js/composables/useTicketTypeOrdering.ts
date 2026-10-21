import { computed, ref } from 'vue';
import type { TicketType, TicketTypeOrder } from '../store/tickets';

export type TicketOrderError = 'upgrade' | 'load' | 'save' | 'conflict' | null;

/** Order membership includes disabled types. Never derive it from a filtered offer. */
export function isCompleteTicketOrder(ids: string[], types: TicketType[]): boolean {
    return ids.length === types.length && new Set(ids).size === ids.length &&
        ids.every(id => types.some(type => type.id === id));
}

export function useTicketTypeOrdering(
    types: { value: TicketType[] },
    read: (eventId: string) => Promise<{ types: TicketType[]; order: TicketTypeOrder }>,
    write: (eventId: string, order: TicketTypeOrder) => Promise<TicketTypeOrder>,
) {
    const editing = ref(false);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref<TicketOrderError>(null);
    const draft = ref<string[]>([]);
    const saved = ref<string[]>([]);
    const revision = ref(0);
    let eventId: string | null = null;
    const dirty = computed(() => draft.value.some((id, index) => id !== saved.value[index]));
    const locked = computed(() => editing.value || loading.value || saving.value);
    const canSave = computed(() => editing.value && dirty.value && !loading.value && !saving.value &&
        error.value !== 'conflict' && error.value !== 'load' && error.value !== 'upgrade');
    const rows = computed(() => editing.value
        ? draft.value.map(id => types.value.find(type => type.id === id)!).filter(Boolean)
        : types.value);

    function valid(order: TicketTypeOrder, allTypes: TicketType[]) {
        return order && Array.isArray(order.ticketTypeIds) &&
            Number.isSafeInteger(order.revision) && order.revision >= 0 &&
            isCompleteTicketOrder(order.ticketTypeIds, allTypes);
    }

    async function load(id: string) {
        if (loading.value || saving.value) return false;
        loading.value = true;
        error.value = null;
        try {
            const result = await read(id);
            if (!valid(result.order, result.types)) throw new Error('Invalid order snapshot');
            types.value = result.order.ticketTypeIds.map(typeId => result.types.find(type => type.id === typeId)!);
            saved.value = [...result.order.ticketTypeIds];
            draft.value = [...result.order.ticketTypeIds];
            revision.value = result.order.revision;
            eventId = id;
            editing.value = true;
            return true;
        } catch (cause) {
            const status = (cause as { response?: { status?: number } })?.response?.status;
            error.value = status === 404 ? 'upgrade' : 'load';
            return false;
        } finally {
            loading.value = false;
        }
    }

    function move(id: string, destination: number) {
        if (!editing.value || loading.value || saving.value) return false;
        const source = draft.value.indexOf(id);
        if (source < 0 || destination < 0 || destination >= draft.value.length || source === destination) return false;
        const next = [...draft.value];
        next.splice(source, 1);
        next.splice(destination, 0, id);
        draft.value = next;
        return true;
    }

    function cancel() {
        if (loading.value || saving.value) return;
        draft.value = [...saved.value];
        editing.value = false;
        error.value = null;
        eventId = null;
    }

    async function save() {
        if (!eventId || !canSave.value) return false;
        saving.value = true;
        error.value = null;
        try {
            const result = await write(eventId, { ticketTypeIds: [...draft.value], revision: revision.value });
            if (!valid(result, types.value)) throw new Error('Invalid saved order');
            types.value = result.ticketTypeIds.map(id => types.value.find(type => type.id === id)!);
            saved.value = [...result.ticketTypeIds];
            draft.value = [...result.ticketTypeIds];
            revision.value = result.revision;
            editing.value = false;
            eventId = null;
            return true;
        } catch (cause) {
            const status = (cause as { response?: { status?: number } })?.response?.status;
            error.value = status === 409 ? 'conflict' : status === 404 ? 'upgrade' : 'save';
            return false;
        } finally {
            saving.value = false;
        }
    }

    return { editing, loading, saving, error, draft, dirty, locked, canSave, rows, load, move, cancel, save };
}

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { useTicketTypeOrdering } from '../composables/useTicketTypeOrdering';
import { useTicketsStore, type TicketType } from '../store/tickets';

const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }));
vi.mock('../services/api', () => ({ default: api }));
const standard = { id: 'type-a', eventId: 'event-a', name: 'Standard', price: 5, quantitySold: 2, isDefault: true, ticketTypeState: 'Active' } as TicketType;
const disabled = { ...standard, id: 'type-b', name: 'Disabled', price: 8, isDefault: false, ticketTypeState: 'Disabled' } as TicketType;
function setup() {
    const types = ref([standard, disabled]);
    const read = vi.fn().mockResolvedValue({ types: [standard, disabled], order: { ticketTypeIds: ['type-a', 'type-b'], revision: 7 } });
    const write = vi.fn().mockResolvedValue({ ticketTypeIds: ['type-b', 'type-a'], revision: 8 });
    return { types, read, write, editor: useTicketTypeOrdering(types, read, write) };
}
beforeEach(() => { vi.resetAllMocks(); setActivePinia(createPinia()); });
describe('complete ticket order drafts', () => {
    it('retains disabled types and metadata while saving a complete immutable snapshot', async () => {
        const { editor, types, write } = setup();
        await editor.load('event-a');
        expect(editor.dirty.value).toBe(false);
        editor.move('type-b', 0);
        expect(editor.rows.value.map(t => t.id)).toEqual(['type-b', 'type-a']);
        expect(types.value.map(t => t.id)).toEqual(['type-a', 'type-b']);
        expect(await editor.save()).toBe(true);
        expect(write).toHaveBeenCalledWith('event-a', { ticketTypeIds: ['type-b', 'type-a'], revision: 7 });
        expect(types.value).toEqual([disabled, standard]);
        expect(editor.editing.value).toBe(false);
    });
    it('cancels without persisting and never changes counters/default/state', async () => {
        const { editor, types, write } = setup();
        await editor.load('event-a'); editor.move('type-a', 1); editor.cancel();
        expect(editor.rows.value).toEqual([standard, disabled]);
        expect(types.value).toEqual([standard, disabled]);
        expect(write).not.toHaveBeenCalled();
    });
    it('keeps a conflicted draft and forbids blind resubmission until explicit reload', async () => {
        const { editor, read, write } = setup();
        await editor.load('event-a'); editor.move('type-b', 0);
        write.mockRejectedValueOnce({ response: { status: 409 } });
        expect(await editor.save()).toBe(false);
        expect(editor.error.value).toBe('conflict');
        expect(editor.draft.value).toEqual(['type-b', 'type-a']);
        expect(await editor.save()).toBe(false);
        expect(write).toHaveBeenCalledTimes(1);
        read.mockResolvedValueOnce({ types: [standard, disabled], order: { ticketTypeIds: ['type-b', 'type-a'], revision: 10 } });
        await editor.load('event-a');
        expect(editor.dirty.value).toBe(false);
        editor.move('type-a', 0);
        write.mockResolvedValueOnce({ ticketTypeIds: ['type-a', 'type-b'], revision: 11 });
        await editor.save();
        expect(write).toHaveBeenLastCalledWith('event-a', { ticketTypeIds: ['type-a', 'type-b'], revision: 10 });
    });
    it('shows upgrade requirement for legacy providers without enabling local-only order', async () => {
        const { editor, types, read, write } = setup();
        read.mockRejectedValueOnce({ response: { status: 404 } });
        expect(await editor.load('event-a')).toBe(false);
        expect(editor.error.value).toBe('upgrade');
        expect(editor.locked.value).toBe(false);
        expect(editor.move('type-b', 0)).toBe(false);
        expect(await editor.save()).toBe(false);
        expect(types.value).toEqual([standard, disabled]);
        expect(write).not.toHaveBeenCalled();
    });
    it('retains drafts through transient read and save failures', async () => {
        const { editor, read, write } = setup();
        await editor.load('event-a'); editor.move('type-b', 0);
        read.mockRejectedValueOnce({ response: { status: 502 } });
        await editor.load('event-a');
        expect(editor.draft.value).toEqual(['type-b', 'type-a']);
        expect(await editor.save()).toBe(false);
        await editor.load('event-a'); editor.move('type-b', 0);
        write.mockRejectedValueOnce({ response: { status: 502 } });
        expect(await editor.save()).toBe(false);
        expect(editor.error.value).toBe('save');
        expect(editor.dirty.value).toBe(true);
        expect(await editor.save()).toBe(true);
    });
    it.each([
        { ids: ['type-a'] }, { ids: ['type-a', 'type-a'] }, { ids: ['type-a', 'foreign-type'] },
    ])('rejects stale or invalid snapshot membership $ids without altering the list', async ({ ids }) => {
        const { editor, types, read } = setup();
        read.mockResolvedValueOnce({ types: [standard, disabled], order: { ticketTypeIds: ids, revision: 7 } });
        expect(await editor.load('event-a')).toBe(false);
        expect(editor.error.value).toBe('load');
        expect(types.value).toEqual([standard, disabled]);
    });
    it('requires a successful snapshot reload after a conflict, even if the reload fails', async () => {
        const { editor, read, write } = setup();
        await editor.load('event-a'); editor.move('type-b', 0);
        write.mockRejectedValueOnce({ response: { status: 409 } });
        await editor.save();
        read.mockRejectedValueOnce({ response: { status: 502 } });
        await editor.load('event-a');
        expect(editor.draft.value).toEqual(['type-b', 'type-a']);
        expect(editor.canSave.value).toBe(false);
        expect(await editor.save()).toBe(false);
        expect(write).toHaveBeenCalledTimes(1);
    });
    it.each([-1, 1.5, Number.MAX_SAFE_INTEGER + 1])('rejects an invalid revision %s', async revision => {
        const { editor, read, types } = setup();
        read.mockResolvedValueOnce({ types: [standard, disabled], order: { ticketTypeIds: ['type-a', 'type-b'], revision } });
        expect(await editor.load('event-a')).toBe(false);
        expect(editor.error.value).toBe('load');
        expect(types.value).toEqual([standard, disabled]);
    });
    it('does not write an unchanged order or accept out-of-bounds moves', async () => {
        const { editor, write } = setup(); await editor.load('event-a');
        expect(editor.move('type-a', -1)).toBe(false);
        expect(editor.move('type-b', 2)).toBe(false);
        expect(editor.move('unknown', 0)).toBe(false);
        expect(await editor.save()).toBe(false);
        expect(write).not.toHaveBeenCalled();
    });
    it('ignores duplicate submissions and blocks moves and cancel while saving', async () => {
        const { editor, write } = setup(); await editor.load('event-a'); editor.move('type-b', 0);
        let finish!: (value: { ticketTypeIds: string[]; revision: number }) => void;
        write.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const saving = editor.save();
        expect(await editor.save()).toBe(false);
        expect(editor.move('type-a', 0)).toBe(false);
        editor.cancel(); expect(editor.editing.value).toBe(true);
        finish({ ticketTypeIds: ['type-b', 'type-a'], revision: 8 });
        expect(await saving).toBe(true);
        expect(write).toHaveBeenCalledTimes(1);
    });
});
describe('order API boundary', () => {
    it('uses local store/event scope and expectedRevision, with no credential or mutable ticket fields', async () => {
        const store = useTicketsStore();
        api.get.mockResolvedValueOnce({ data: { data: { ticketTypeIds: ['type-a', 'type-b'], revision: 7 } } });
        expect(await store.fetchTicketTypeOrder('local-store', 'event-a')).toEqual({ ticketTypeIds: ['type-a', 'type-b'], revision: 7 });
        api.put.mockResolvedValueOnce({ data: { data: { ticketTypeIds: ['type-b', 'type-a'], revision: 8 } } });
        await store.saveTicketTypeOrder('local-store', 'event-a', { ticketTypeIds: ['type-b', 'type-a'], revision: 7 });
        const path = '/stores/local-store/tickets/events/event-a/ticket-types/order';
        expect(api.get).toHaveBeenCalledWith(path);
        expect(api.put).toHaveBeenCalledWith(path, { ticketTypeIds: ['type-b', 'type-a'], expectedRevision: 7 });
    });
});

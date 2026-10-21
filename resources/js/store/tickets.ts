import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

// ──────────────────────────────────────────────────
//  Types
// ──────────────────────────────────────────────────

export interface TicketEvent {
    id: string;
    storeId: string;
    title: string;
    description?: string;
    eventType: 'Physical' | 'Virtual';
    /** Physical venue IANA zone; null retains the legacy explicit-UTC display. */
    timeZone?: string | null;
    location?: string;
    startDate: string;
    endDate?: string | null;
    currency?: string;
    redirectUrl?: string;
    emailSubject?: string;
    emailBody?: string;
    hasMaximumCapacity: boolean;
    maximumEventCapacity?: number;
    /** Older plugin payloads use logoUrl / carry the upload file id. */
    logoUrl?: string | null;
    eventLogoFileId?: string | null;
    eventState: 'Active' | 'Disabled';
    eventLogoUrl?: string | null;
    purchaseLink?: string;
    ticketsSold?: number;
    createdAt?: string;
    bundledRaffleId?: string | null;
    bundledRaffleTicketsPerAdmission?: number;
}

export type TicketEventWrite = Partial<TicketEvent> & {
    startLocal?: string;
    endLocal?: string | null;
};

export interface TicketType {
    id: string;
    eventId: string;
    name: string;
    price: number;
    description?: string;
    quantity?: number;
    quantitySold?: number;
    quantityAvailable?: number;
    isDefault: boolean;
    ticketTypeState: 'Active' | 'Disabled';
}

export interface TicketTypeOrder {
    ticketTypeIds: string[];
    revision: number;
}

export interface Ticket {
    id: string;
    eventId: string;
    ticketTypeId: string;
    ticketTypeName: string;
    amount: number;
    firstName: string;
    lastName: string;
    email: string;
    ticketNumber: string;
    txnNumber: string;
    paymentStatus: string;
    checkedIn: boolean;
    checkedInAt?: string | null;
    emailSent: boolean;
    createdAt: string;
    /** Populated when loading tickets with orders (required for resend email). */
    orderId?: string;
}

export interface TicketOrder {
    id: string;
    eventId: string;
    totalAmount: number;
    currency: string;
    invoiceId: string;
    paymentStatus: string;
    invoiceStatus: string;
    emailSent: boolean;
    createdAt: string;
    purchaseDate: string;
    tickets: Ticket[];
}

// ──────────────────────────────────────────────────
//  Store
// ──────────────────────────────────────────────────

export const useTicketsStore = defineStore('tickets', () => {
    const events = ref<TicketEvent[]>([]);
    const currentEvent = ref<TicketEvent | null>(null);
    const ticketTypes = ref<TicketType[]>([]);
    const tickets = ref<Ticket[]>([]);
    const orders = ref<TicketOrder[]>([]);
    const loading = ref(false);

    // ── Events ──────────────────────────────────────

    async function fetchEvents(storeId: string, expired = false) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/tickets/events`, {
                params: {
                    expired: expired ? 'true' : undefined,
                    includeInactive: 'true',
                },
            });
            events.value = response.data.data || [];
            return events.value;
        } catch (error) {
            events.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchTimeZones(storeId: string): Promise<string[]> {
        const response = await api.get(`/stores/${storeId}/tickets/time-zones`);
        const zones: unknown = response.data?.data?.timeZones;
        if (!Array.isArray(zones) || zones.length === 0 || zones.some(zone => typeof zone !== 'string' || !zone.trim())) {
            throw new Error('Invalid event time zone catalog');
        }
        return [...new Set(zones as string[])];
    }

    async function fetchEvent(storeId: string, eventId: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/tickets/events/${eventId}`);
            currentEvent.value = response.data.data;
            return currentEvent.value;
        } finally {
            loading.value = false;
        }
    }

    async function createEvent(storeId: string, data: TicketEventWrite) {
        loading.value = true;
        try {
            const response = await api.post(`/stores/${storeId}/tickets/events`, data);
            const event = response.data.data;
            events.value.push(event);
            return event;
        } finally {
            loading.value = false;
        }
    }

    async function updateEvent(storeId: string, eventId: string, data: TicketEventWrite) {
        loading.value = true;
        try {
            const response = await api.put(`/stores/${storeId}/tickets/events/${eventId}`, data);
            const event = response.data.data;
            const index = events.value.findIndex(e => e.id === eventId);
            if (index !== -1) events.value[index] = event;
            if (currentEvent.value?.id === eventId) currentEvent.value = event;
            return event;
        } finally {
            loading.value = false;
        }
    }

    async function deleteEvent(storeId: string, eventId: string) {
        loading.value = true;
        try {
            await api.delete(`/stores/${storeId}/tickets/events/${eventId}`);
            events.value = events.value.filter(e => e.id !== eventId);
            if (currentEvent.value?.id === eventId) currentEvent.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function toggleEvent(storeId: string, eventId: string) {
        loading.value = true;
        try {
            const response = await api.put(`/stores/${storeId}/tickets/events/${eventId}/toggle`, {});
            // Plugin may return 204 No Content (empty body → backend returns []).
            // Only update store state when we get a valid event object back.
            const raw = response.data.data;
            const event = raw && !Array.isArray(raw) && typeof raw === 'object' ? raw : null;
            if (event) {
                const index = events.value.findIndex(e => e.id === eventId);
                if (index !== -1) events.value[index] = event;
                if (currentEvent.value?.id === eventId) currentEvent.value = event;
            }
            return event;
        } finally {
            loading.value = false;
        }
    }

    // ── Ticket Types ────────────────────────────────

    async function fetchTicketTypes(storeId: string, eventId: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/tickets/events/${eventId}/ticket-types`);
            ticketTypes.value = response.data.data || [];
            return ticketTypes.value;
        } catch (error) {
            ticketTypes.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchTicketTypeOrder(storeId: string, eventId: string): Promise<TicketTypeOrder> {
        const response = await api.get(`/stores/${storeId}/tickets/events/${eventId}/ticket-types/order`);
        return response.data.data;
    }

    async function saveTicketTypeOrder(storeId: string, eventId: string, order: TicketTypeOrder): Promise<TicketTypeOrder> {
        const response = await api.put(`/stores/${storeId}/tickets/events/${eventId}/ticket-types/order`, {
            ticketTypeIds: order.ticketTypeIds,
            expectedRevision: order.revision,
        });
        return response.data.data;
    }

    async function createTicketType(storeId: string, eventId: string, data: Partial<TicketType>) {
        loading.value = true;
        try {
            const response = await api.post(`/stores/${storeId}/tickets/events/${eventId}/ticket-types`, data);
            const ticketType = response.data.data;
            ticketTypes.value.push(ticketType);
            return ticketType;
        } finally {
            loading.value = false;
        }
    }

    async function updateTicketType(storeId: string, eventId: string, ticketTypeId: string, data: Partial<TicketType>) {
        loading.value = true;
        try {
            const response = await api.put(`/stores/${storeId}/tickets/events/${eventId}/ticket-types/${ticketTypeId}`, data);
            const ticketType = response.data.data;
            const index = ticketTypes.value.findIndex(tt => tt.id === ticketTypeId);
            if (index !== -1) ticketTypes.value[index] = ticketType;
            return ticketType;
        } finally {
            loading.value = false;
        }
    }

    async function deleteTicketType(storeId: string, eventId: string, ticketTypeId: string) {
        loading.value = true;
        try {
            await api.delete(`/stores/${storeId}/tickets/events/${eventId}/ticket-types/${ticketTypeId}`);
            ticketTypes.value = ticketTypes.value.filter(tt => tt.id !== ticketTypeId);
        } finally {
            loading.value = false;
        }
    }

    async function toggleTicketType(storeId: string, eventId: string, ticketTypeId: string) {
        loading.value = true;
        try {
            const response = await api.put(`/stores/${storeId}/tickets/events/${eventId}/ticket-types/${ticketTypeId}/toggle`, {});
            const ticketType = response.data.data;
            const index = ticketTypes.value.findIndex(tt => tt.id === ticketTypeId);
            if (index !== -1) ticketTypes.value[index] = ticketType;
            return ticketType;
        } finally {
            loading.value = false;
        }
    }

    // ── Tickets ─────────────────────────────────────

    async function fetchTickets(storeId: string, eventId: string, searchText?: string) {
        loading.value = true;
        try {
            const searchParams = searchText ? { searchText } : {};
            const [ticketsResponse, ordersResponse] = await Promise.all([
                api.get(`/stores/${storeId}/tickets/events/${eventId}/tickets`, {
                    params: searchParams,
                }),
                api.get(`/stores/${storeId}/tickets/events/${eventId}/orders`, {
                    params: searchParams,
                }),
            ]);
            const ticketList: Ticket[] = ticketsResponse.data.data || [];
            const orderList: TicketOrder[] = ordersResponse.data.data || [];
            const orderIdByTicketId = new Map<string, string>();
            for (const order of orderList) {
                for (const ticket of order.tickets ?? []) {
                    orderIdByTicketId.set(ticket.id, order.id);
                }
            }
            tickets.value = ticketList.map((ticket) => ({
                ...ticket,
                orderId: orderIdByTicketId.get(ticket.id),
            }));
            return tickets.value;
        } catch (error) {
            tickets.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function checkInTicket(storeId: string, eventId: string, ticketNumber: string) {
        const response = await api.post(`/stores/${storeId}/tickets/events/${eventId}/tickets/${ticketNumber}/check-in`, {});
        return response.data.data;
    }

    // ── Orders ──────────────────────────────────────

    async function fetchOrders(storeId: string, eventId: string, searchText?: string) {
        loading.value = true;
        try {
            const response = await api.get(`/stores/${storeId}/tickets/events/${eventId}/orders`, {
                params: searchText ? { searchText } : {},
            });
            orders.value = response.data.data || [];
            return orders.value;
        } catch (error) {
            orders.value = [];
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function sendReminder(storeId: string, eventId: string, orderId: string, ticketId: string) {
        const response = await api.post(
            `/stores/${storeId}/tickets/events/${eventId}/orders/${orderId}/tickets/${ticketId}/send-reminder`,
            {}
        );
        return response.data.data;
    }

    return {
        // state
        events,
        currentEvent,
        ticketTypes,
        tickets,
        orders,
        loading,
        // events
        fetchEvents,
        fetchTimeZones,
        fetchEvent,
        createEvent,
        updateEvent,
        deleteEvent,
        toggleEvent,
        // ticket types
        fetchTicketTypes,
        fetchTicketTypeOrder,
        saveTicketTypeOrder,
        createTicketType,
        updateTicketType,
        deleteTicketType,
        toggleTicketType,
        // tickets
        fetchTickets,
        checkInTicket,
        // orders
        fetchOrders,
        sendReminder,
    };
});

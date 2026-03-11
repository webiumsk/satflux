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
    location?: string;
    startDate: string;
    endDate?: string;
    currency?: string;
    redirectUrl?: string;
    emailSubject?: string;
    emailBody?: string;
    hasMaximumCapacity: boolean;
    maximumEventCapacity?: number;
    eventState: 'Active' | 'Disabled';
    eventLogoUrl?: string | null;
    purchaseLink?: string;
    ticketsSold?: number;
    ticketTypesCount?: number;
    createdAt?: string;
}

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
                params: { expired: expired ? 'true' : undefined },
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

    async function createEvent(storeId: string, data: Partial<TicketEvent>) {
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

    async function updateEvent(storeId: string, eventId: string, data: Partial<TicketEvent>) {
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
            const event = response.data.data;
            const index = events.value.findIndex(e => e.id === eventId);
            if (index !== -1) events.value[index] = event;
            if (currentEvent.value?.id === eventId) currentEvent.value = event;
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
            const response = await api.get(`/stores/${storeId}/tickets/events/${eventId}/tickets`, {
                params: searchText ? { searchText } : {},
            });
            tickets.value = response.data.data || [];
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
        fetchEvent,
        createEvent,
        updateEvent,
        deleteEvent,
        toggleEvent,
        // ticket types
        fetchTicketTypes,
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

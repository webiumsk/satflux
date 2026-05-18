import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export type RaffleStatus = 'Draft' | 'Open' | 'Closed' | 'Drawing' | 'Completed';

export type RaffleAction = 'open' | 'close' | 'draw' | 'complete';

export interface Raffle {
    id: string;
    name: string;
    description?: string | null;
    storeId: string;
    ticketPriceSats: number;
    maxTickets: number | null;
    status: RaffleStatus;
    ticketsSold: number;
    createdAt: string;
    openedAt?: string | null;
    closedAt?: string | null;
    completedAt?: string | null;
    allowedActions?: RaffleAction[];
    showsPublicLink?: boolean;
}

export interface RaffleTicket {
    ticketNumber: number;
    buyerName?: string | null;
    buyerEmail?: string | null;
    allocatedAt: string;
    receiptUrl?: string;
}

export interface RaffleDrawing {
    drawOrder: number;
    winningTicketNumber: number;
    winnerName?: string | null;
    winnerEmail?: string | null;
    drawnAt: string;
}

export interface CreateRafflePayload {
    name: string;
    description?: string | null;
    ticketPriceSats: number;
    maxTickets?: number | null;
}

export type UpdateRafflePayload = CreateRafflePayload;

export interface PresenterTokenResponse {
    token: string;
    expiresAt: string;
    presenterUrl: string;
}

export const useRafflesStore = defineStore('raffles', () => {
    const raffles = ref<Raffle[]>([]);
    const currentRaffle = ref<Raffle | null>(null);
    const tickets = ref<RaffleTicket[]>([]);
    const drawings = ref<RaffleDrawing[]>([]);
    const loading = ref(false);
    const pluginAvailable = ref<boolean | null>(null);

    async function fetchAvailability(storeId: string, refresh = false): Promise<boolean> {
        const qs = refresh ? '?refresh=1' : '';
        const { data } = await api.get<{ data: { available: boolean } }>(
            `/stores/${storeId}/raffles/status${qs}`,
        );
        pluginAvailable.value = data.data.available;
        return data.data.available;
    }

    async function fetchRaffles(storeId: string): Promise<Raffle[]> {
        loading.value = true;
        try {
            const { data } = await api.get<{ data: Raffle[] }>(`/stores/${storeId}/raffles`);
            raffles.value = data.data ?? [];
            return raffles.value;
        } finally {
            loading.value = false;
        }
    }

    async function createRaffle(storeId: string, payload: CreateRafflePayload): Promise<Raffle> {
        const { data } = await api.post<{ data: Raffle }>(`/stores/${storeId}/raffles`, payload);
        return data.data;
    }

    async function updateRaffle(
        storeId: string,
        raffleId: string,
        payload: UpdateRafflePayload,
    ): Promise<Raffle> {
        const { data } = await api.put<{ data: Raffle }>(
            `/stores/${storeId}/raffles/${raffleId}`,
            payload,
        );
        currentRaffle.value = data.data;
        return data.data;
    }

    async function createPresenterToken(
        storeId: string,
        raffleId: string,
    ): Promise<PresenterTokenResponse> {
        const { data } = await api.post<{ data: PresenterTokenResponse }>(
            `/stores/${storeId}/raffles/${raffleId}/presenter-token`,
        );
        return data.data;
    }

    async function fetchRaffle(storeId: string, raffleId: string): Promise<Raffle> {
        loading.value = true;
        try {
            const { data } = await api.get<{ data: Raffle }>(
                `/stores/${storeId}/raffles/${raffleId}`,
            );
            currentRaffle.value = data.data;
            return data.data;
        } finally {
            loading.value = false;
        }
    }

    async function openRaffle(storeId: string, raffleId: string): Promise<Raffle> {
        const { data } = await api.post<{ data: Raffle }>(
            `/stores/${storeId}/raffles/${raffleId}/open`,
        );
        currentRaffle.value = data.data;
        return data.data;
    }

    async function closeRaffle(storeId: string, raffleId: string): Promise<Raffle> {
        const { data } = await api.post<{ data: Raffle }>(
            `/stores/${storeId}/raffles/${raffleId}/close`,
        );
        currentRaffle.value = data.data;
        return data.data;
    }

    async function drawRaffle(storeId: string, raffleId: string): Promise<RaffleDrawing> {
        const { data } = await api.post<{ data: RaffleDrawing }>(
            `/stores/${storeId}/raffles/${raffleId}/draw`,
        );
        return data.data;
    }

    async function completeRaffle(storeId: string, raffleId: string): Promise<Raffle> {
        const { data } = await api.post<{ data: Raffle }>(
            `/stores/${storeId}/raffles/${raffleId}/complete`,
        );
        currentRaffle.value = data.data;
        return data.data;
    }

    async function fetchTickets(storeId: string, raffleId: string): Promise<RaffleTicket[]> {
        const { data } = await api.get<{ data: RaffleTicket[] }>(
            `/stores/${storeId}/raffles/${raffleId}/tickets`,
        );
        tickets.value = data.data ?? [];
        return tickets.value;
    }

    async function fetchDrawings(storeId: string, raffleId: string): Promise<RaffleDrawing[]> {
        const { data } = await api.get<{ data: RaffleDrawing[] }>(
            `/stores/${storeId}/raffles/${raffleId}/drawings`,
        );
        drawings.value = data.data ?? [];
        return drawings.value;
    }

    return {
        raffles,
        currentRaffle,
        tickets,
        drawings,
        loading,
        pluginAvailable,
        fetchAvailability,
        fetchRaffles,
        createRaffle,
        updateRaffle,
        createPresenterToken,
        fetchRaffle,
        openRaffle,
        closeRaffle,
        drawRaffle,
        completeRaffle,
        fetchTickets,
        fetchDrawings,
    };
});

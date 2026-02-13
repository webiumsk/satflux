<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

class TicketService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    // ──────────────────────────────────────────────────
    //  EVENTS
    // ──────────────────────────────────────────────────

    /**
     * List all events for a store.
     */
    public function listEvents(string $storeId, ?string $userApiKey = null, bool $expired = false): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $expired) {
            $query = $expired ? ['expired' => 'true'] : [];
            return $this->client->get("/api/v1/stores/{$storeId}/satoshi-tickets/events", $query);
        });
    }

    /**
     * Get a single event.
     */
    public function getEvent(string $storeId, string $eventId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId) {
            return $this->client->get("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}");
        });
    }

    /**
     * Create a new event.
     */
    public function createEvent(string $storeId, array $data, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $data) {
            return $this->client->post("/api/v1/stores/{$storeId}/satoshi-tickets/events", $data);
        });
    }

    /**
     * Update an event.
     */
    public function updateEvent(string $storeId, string $eventId, array $data, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $data) {
            return $this->client->put("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}", $data);
        });
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(string $storeId, string $eventId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId) {
            return $this->client->delete("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}");
        });
    }

    /**
     * Toggle event status (Active/Disabled).
     */
    public function toggleEvent(string $storeId, string $eventId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId) {
            return $this->client->put("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/toggle", []);
        });
    }

    // ──────────────────────────────────────────────────
    //  TICKET TYPES
    // ──────────────────────────────────────────────────

    /**
     * List ticket types for an event.
     */
    public function listTicketTypes(string $storeId, string $eventId, ?string $userApiKey = null, string $sortBy = 'Name', string $sortDir = 'asc'): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $sortBy, $sortDir) {
            return $this->client->get(
                "/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types",
                ['sortBy' => $sortBy, 'sortDir' => $sortDir]
            );
        });
    }

    /**
     * Get a single ticket type.
     */
    public function getTicketType(string $storeId, string $eventId, string $ticketTypeId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $ticketTypeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types/{$ticketTypeId}");
        });
    }

    /**
     * Create a ticket type.
     */
    public function createTicketType(string $storeId, string $eventId, array $data, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $data) {
            return $this->client->post("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types", $data);
        });
    }

    /**
     * Update a ticket type.
     */
    public function updateTicketType(string $storeId, string $eventId, string $ticketTypeId, array $data, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $ticketTypeId, $data) {
            return $this->client->put("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types/{$ticketTypeId}", $data);
        });
    }

    /**
     * Delete a ticket type.
     */
    public function deleteTicketType(string $storeId, string $eventId, string $ticketTypeId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $ticketTypeId) {
            return $this->client->delete("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types/{$ticketTypeId}");
        });
    }

    /**
     * Toggle ticket type status (Active/Disabled).
     */
    public function toggleTicketType(string $storeId, string $eventId, string $ticketTypeId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $ticketTypeId) {
            return $this->client->put("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/ticket-types/{$ticketTypeId}/toggle", []);
        });
    }

    // ──────────────────────────────────────────────────
    //  TICKETS
    // ──────────────────────────────────────────────────

    /**
     * List settled tickets for an event.
     */
    public function listTickets(string $storeId, string $eventId, ?string $userApiKey = null, ?string $searchText = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $searchText) {
            $query = $searchText ? ['searchText' => $searchText] : [];
            return $this->client->get("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/tickets", $query);
        });
    }

    /**
     * Check-in a ticket.
     */
    public function checkInTicket(string $storeId, string $eventId, string $ticketNumber, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $ticketNumber) {
            return $this->client->post("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/tickets/{$ticketNumber}/check-in", []);
        });
    }

    // ──────────────────────────────────────────────────
    //  ORDERS
    // ──────────────────────────────────────────────────

    /**
     * List settled orders for an event.
     */
    public function listOrders(string $storeId, string $eventId, ?string $userApiKey = null, ?string $searchText = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $searchText) {
            $query = $searchText ? ['searchText' => $searchText] : [];
            return $this->client->get("/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/orders", $query);
        });
    }

    /**
     * Send ticket reminder email.
     */
    public function sendReminder(string $storeId, string $eventId, string $orderId, string $ticketId, ?string $userApiKey = null): array
    {
        return $this->withApiKey($userApiKey, function () use ($storeId, $eventId, $orderId, $ticketId) {
            return $this->client->post(
                "/api/v1/stores/{$storeId}/satoshi-tickets/events/{$eventId}/orders/{$orderId}/tickets/{$ticketId}/send-reminder",
                []
            );
        });
    }

    // ──────────────────────────────────────────────────
    //  HELPER
    // ──────────────────────────────────────────────────

    /**
     * Execute a callback with an optional user-level API key, restoring the
     * original key afterwards.
     */
    protected function withApiKey(?string $userApiKey, callable $callback): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $callback();
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}

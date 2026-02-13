<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    // ──────────────────────────────────────────────────
    //  EVENTS
    // ──────────────────────────────────────────────────

    /**
     * List all events for a store.
     */
    public function listEvents(Request $request, Store $store)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $expired = $request->boolean('expired', false);

        $events = $this->ticketService->listEvents(
            $store->btcpay_store_id,
            $userApiKey,
            $expired
        );

        $events = array_map([$this, 'normalizeEventLogo'], $events);

        return response()->json(['data' => $events]);
    }

    /**
     * Get a single event.
     */
    public function getEvent(Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $event = $this->ticketService->getEvent(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey
        );

        return response()->json(['data' => $this->normalizeEventLogo($event)]);
    }

    /**
     * Create a new event.
     */
    public function createEvent(Request $request, Store $store)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'startDate' => ['required', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'eventType' => ['sometimes', 'string', 'in:Physical,Virtual'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endDate' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'redirectUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'emailSubject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emailBody' => ['sometimes', 'nullable', 'string'],
            'hasMaximumCapacity' => ['sometimes', 'boolean'],
            'maximumEventCapacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'eventLogoUrl' => ['sometimes', 'nullable', 'string', 'max:500', Rule::when($request->filled('eventLogoUrl'), ['url'])],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Build request data — only include non-null, non-empty logo
        $data = array_filter($request->only([
            'title',
            'description',
            'eventType',
            'location',
            'startDate',
            'endDate',
            'currency',
            'redirectUrl',
            'emailSubject',
            'emailBody',
            'hasMaximumCapacity',
            'maximumEventCapacity',
            'eventLogoUrl',
        ]), (fn ($v, $k) => $v !== null && ($k !== 'eventLogoUrl' || (string) $v !== '')), ARRAY_FILTER_USE_BOTH);

        $event = $this->ticketService->createEvent(
            $store->btcpay_store_id,
            $data,
            $userApiKey
        );

        return response()->json(['data' => $this->normalizeEventLogo($event)], 201);
    }

    /**
     * Update an event.
     */
    public function updateEvent(Request $request, Store $store, string $eventId)
    {
        $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'startDate' => ['sometimes', 'required', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'eventType' => ['sometimes', 'string', 'in:Physical,Virtual'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endDate' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'redirectUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'emailSubject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emailBody' => ['sometimes', 'nullable', 'string'],
            'hasMaximumCapacity' => ['sometimes', 'boolean'],
            'maximumEventCapacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'eventLogoUrl' => ['sometimes', 'nullable', 'string', 'max:500', Rule::when($request->filled('eventLogoUrl'), ['url'])],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $data = array_filter($request->only([
            'title',
            'description',
            'eventType',
            'location',
            'startDate',
            'endDate',
            'currency',
            'redirectUrl',
            'emailSubject',
            'emailBody',
            'hasMaximumCapacity',
            'maximumEventCapacity',
            'eventLogoUrl',
        ]), (fn ($v, $k) => $v !== null && ($k !== 'eventLogoUrl' || (string) $v !== '')), ARRAY_FILTER_USE_BOTH);

        $event = $this->ticketService->updateEvent(
            $store->btcpay_store_id,
            $eventId,
            $data,
            $userApiKey
        );

        return response()->json(['data' => $this->normalizeEventLogo($event)]);
    }

    /**
     * Normalize event response so frontend always gets eventLogoUrl (BTCPay may return logoUrl).
     */
    protected function normalizeEventLogo(array $event): array
    {
        if (! isset($event['eventLogoUrl']) && ! empty($event['logoUrl'])) {
            $event['eventLogoUrl'] = $event['logoUrl'];
        }
        return $event;
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $this->ticketService->deleteEvent(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey
        );

        return response()->json(['message' => 'Event deleted successfully']);
    }

    /**
     * Toggle event status (Active/Disabled).
     */
    public function toggleEvent(Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $event = $this->ticketService->toggleEvent(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey
        );

        return response()->json(['data' => $event]);
    }

    // ──────────────────────────────────────────────────
    //  TICKET TYPES
    // ──────────────────────────────────────────────────

    /**
     * List ticket types for an event.
     */
    public function listTicketTypes(Request $request, Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $ticketTypes = $this->ticketService->listTicketTypes(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey,
            $request->input('sortBy', 'Name'),
            $request->input('sortDir', 'asc')
        );

        return response()->json(['data' => $ticketTypes]);
    }

    /**
     * Create a ticket type.
     */
    public function createTicketType(Request $request, Store $store, string $eventId)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $data = array_filter($request->only([
            'name',
            'price',
            'description',
            'quantity',
            'isDefault',
        ]), fn ($v) => $v !== null);

        $ticketType = $this->ticketService->createTicketType(
            $store->btcpay_store_id,
            $eventId,
            $data,
            $userApiKey
        );

        return response()->json(['data' => $ticketType], 201);
    }

    /**
     * Update a ticket type.
     */
    public function updateTicketType(Request $request, Store $store, string $eventId, string $ticketTypeId)
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $data = array_filter($request->only([
            'name',
            'price',
            'description',
            'quantity',
            'isDefault',
        ]), fn ($v) => $v !== null);

        $ticketType = $this->ticketService->updateTicketType(
            $store->btcpay_store_id,
            $eventId,
            $ticketTypeId,
            $data,
            $userApiKey
        );

        return response()->json(['data' => $ticketType]);
    }

    /**
     * Delete a ticket type.
     */
    public function deleteTicketType(Store $store, string $eventId, string $ticketTypeId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $this->ticketService->deleteTicketType(
            $store->btcpay_store_id,
            $eventId,
            $ticketTypeId,
            $userApiKey
        );

        return response()->json(['message' => 'Ticket type deleted successfully']);
    }

    /**
     * Toggle ticket type status (Active/Disabled).
     */
    public function toggleTicketType(Store $store, string $eventId, string $ticketTypeId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $ticketType = $this->ticketService->toggleTicketType(
            $store->btcpay_store_id,
            $eventId,
            $ticketTypeId,
            $userApiKey
        );

        return response()->json(['data' => $ticketType]);
    }

    // ──────────────────────────────────────────────────
    //  TICKETS
    // ──────────────────────────────────────────────────

    /**
     * List settled tickets for an event.
     */
    public function listTickets(Request $request, Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $tickets = $this->ticketService->listTickets(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey,
            $request->input('searchText')
        );

        return response()->json(['data' => $tickets]);
    }

    /**
     * Check-in a ticket.
     */
    public function checkInTicket(Store $store, string $eventId, string $ticketNumber)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $result = $this->ticketService->checkInTicket(
            $store->btcpay_store_id,
            $eventId,
            $ticketNumber,
            $userApiKey
        );

        return response()->json(['data' => $result]);
    }

    // ──────────────────────────────────────────────────
    //  ORDERS
    // ──────────────────────────────────────────────────

    /**
     * List settled orders for an event.
     */
    public function listOrders(Request $request, Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $orders = $this->ticketService->listOrders(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey,
            $request->input('searchText')
        );

        return response()->json(['data' => $orders]);
    }

    /**
     * Send ticket reminder email.
     */
    public function sendReminder(Store $store, string $eventId, string $orderId, string $ticketId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $result = $this->ticketService->sendReminder(
            $store->btcpay_store_id,
            $eventId,
            $orderId,
            $ticketId,
            $userApiKey
        );

        return response()->json(['data' => $result]);
    }

    // ──────────────────────────────────────────────────
    //  PUBLIC CHECK-IN (no auth required, uses store owner's API key)
    // ──────────────────────────────────────────────────

    /**
     * Get minimal event info for the public check-in page.
     * Only returns non-sensitive data (title, dates, type, location, capacity).
     */
    public function publicEventInfo(Store $store, string $eventId)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $event = $this->ticketService->getEvent(
            $store->btcpay_store_id,
            $eventId,
            $userApiKey
        );

        // Return only non-sensitive fields
        return response()->json(['data' => [
            'id' => $event['id'] ?? $eventId,
            'title' => $event['title'] ?? '',
            'eventType' => $event['eventType'] ?? 'Physical',
            'location' => $event['location'] ?? null,
            'startDate' => $event['startDate'] ?? null,
            'endDate' => $event['endDate'] ?? null,
            'eventState' => $event['eventState'] ?? 'Disabled',
            'hasMaximumCapacity' => $event['hasMaximumCapacity'] ?? false,
            'maximumEventCapacity' => $event['maximumEventCapacity'] ?? null,
            'ticketsSold' => $event['ticketsSold'] ?? 0,
        ]]);
    }

    /**
     * Public check-in endpoint. No auth required.
     */
    public function publicCheckIn(Store $store, string $eventId, string $ticketNumber)
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $result = $this->ticketService->checkInTicket(
            $store->btcpay_store_id,
            $eventId,
            $ticketNumber,
            $userApiKey
        );

        return response()->json(['data' => $result]);
    }
}

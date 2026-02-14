<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected BtcPayClient $btcPayClient
    ) {}

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
     * Free plan: max 1 event per store. Pro / admin / support: unlimited.
     */
    public function createEvent(Request $request, Store $store)
    {
        $user = $store->user;
        $maxEvents = $user->getMaxEventsPerStore();
        if ($maxEvents !== null) {
            $userApiKey = $user->getBtcPayApiKeyOrFail();
            $existing = $this->ticketService->listEvents($store->btcpay_store_id, $userApiKey, false);
            $count = is_array($existing) ? count($existing) : 0;
            if ($count >= $maxEvents) {
                return response()->json([
                    'message' => __('messages.tickets_event_limit_free', ['max' => $maxEvents]),
                ], 403);
            }
        }

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
            'eventLogoFileId' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Build request data — include eventLogoFileId (plugin 1.5) and optional eventLogoUrl
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
            'eventLogoFileId',
        ]), (fn ($v, $k) => $v !== null && ($k !== 'eventLogoUrl' || (string) $v !== '')), ARRAY_FILTER_USE_BOTH);
        if ($request->has('eventLogoFileId')) {
            $data['eventLogoFileId'] = $request->input('eventLogoFileId');
        }
        $this->ensureEventLogoUrl($data);

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
            'eventLogoFileId' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'eventLogoFileId',
        ]), (fn ($v, $k) => $v !== null && ($k !== 'eventLogoUrl' || (string) $v !== '')), ARRAY_FILTER_USE_BOTH);
        if ($request->has('eventLogoFileId')) {
            $data['eventLogoFileId'] = $request->input('eventLogoFileId');
        }
        $this->ensureEventLogoUrl($data);

        $event = $this->ticketService->updateEvent(
            $store->btcpay_store_id,
            $eventId,
            $data,
            $userApiKey
        );

        return response()->json(['data' => $this->normalizeEventLogo($event)]);
    }

    /**
     * When payload has eventLogoFileId but no eventLogoUrl, resolve URL from BTCPay Files API (GET file)
     * so the plugin receives a display URL (plugin may not persist eventLogoFileId).
     */
    protected function ensureEventLogoUrl(array &$data): void
    {
        $fileId = trim((string) ($data['eventLogoFileId'] ?? ''));
        if ($fileId === '') {
            return;
        }
        $logoUrl = trim((string) ($data['eventLogoUrl'] ?? ''));
        if ($logoUrl !== '') {
            return;
        }
        $baseUrl = rtrim(config('services.btcpay.base_url', ''), '/');
        try {
            $file = $this->btcPayClient->get('/api/v1/files/' . $fileId);
            Log::debug('Ticket event logo GET file response', ['file_id' => $fileId, 'keys' => array_keys($file)]);
            $url = $file['url'] ?? null;
            $storageName = $file['storageName'] ?? $file['storage_name'] ?? null;
            $uri = $file['uri'] ?? null;
            if (! empty($url) && ! str_starts_with((string) $url, 'fileid:')) {
                $data['eventLogoUrl'] = $url;
                $data['logoUrl'] = $url;
                Log::debug('Ticket event logo URL resolved from file.url', ['eventLogoUrl' => $url]);
                return;
            }
            if (! empty($storageName)) {
                $data['eventLogoUrl'] = $baseUrl . '/LocalStorage/' . $storageName;
                $data['logoUrl'] = $data['eventLogoUrl'];
                Log::debug('Ticket event logo URL built from storageName', ['eventLogoUrl' => $data['eventLogoUrl']]);
                return;
            }
            if (! empty($uri)) {
                $resolved = str_starts_with((string) $uri, 'http') ? $uri : $baseUrl . (str_starts_with((string) $uri, '/') ? '' : '/') . $uri;
                $data['eventLogoUrl'] = $resolved;
                $data['logoUrl'] = $resolved;
                Log::debug('Ticket event logo URL from file.uri', ['eventLogoUrl' => $resolved]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not resolve event logo URL from file id', ['file_id' => $fileId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Normalize event response: eventLogoUrl (and logoUrl) and eventLogoFileId for frontend.
     * If plugin returns only eventLogoFileId, build eventLogoUrl from BTCPay base URL so image displays.
     */
    protected function normalizeEventLogo(array $event): array
    {
        if (! isset($event['eventLogoUrl']) && ! empty($event['logoUrl'])) {
            $event['eventLogoUrl'] = $event['logoUrl'];
        }
        $event['eventLogoFileId'] = $event['eventLogoFileId'] ?? '';
        $fileId = trim((string) ($event['eventLogoFileId'] ?? ''));
        if (($event['eventLogoUrl'] ?? '') === '' && $fileId !== '') {
            $baseUrl = rtrim(config('services.btcpay.base_url', ''), '/');
            $event['eventLogoUrl'] = $baseUrl . '/LocalStorage/' . $fileId;
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
     * Unlimited capacity is at event level (hasMaximumCapacity = false). When event has no max capacity
     * and quantity is omitted or < 1, we send a high quantity so QuantityAvailable stays correct.
     */
    public function createTicketType(Request $request, Store $store, string $eventId)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $event = $this->ticketService->getEvent($store->btcpay_store_id, $eventId, $userApiKey);
        $hasMaxCapacity = $event['hasMaximumCapacity'] ?? false;

        $data = array_filter($request->only([
            'name',
            'price',
            'description',
            'quantity',
        ]), fn ($v) => $v !== null && $v !== '');
        $data['isDefault'] = $request->boolean('isDefault');
        $q = array_key_exists('quantity', $data) ? (int) $data['quantity'] : null;
        if ($hasMaxCapacity && ($q === null || $q < 1)) {
            return response()->json(['message' => __('messages.tickets_quantity_required_when_capacity')], 422);
        }
        if (! array_key_exists('quantity', $data) || $q < 1) {
            $data['quantity'] = TicketService::UNLIMITED_QUANTITY;
        }

        if ($data['isDefault']) {
            $existing = $this->ticketService->listTicketTypes($store->btcpay_store_id, $eventId, $userApiKey);
            foreach ($existing as $tt) {
                $this->ticketService->updateTicketType($store->btcpay_store_id, $eventId, $tt['id'], ['isDefault' => false], $userApiKey);
            }
        }

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
     * Unlimited capacity is at event level; when event has no max capacity and quantity omitted or < 1,
     * we send UNLIMITED_QUANTITY so QuantityAvailable stays correct.
     */
    public function updateTicketType(Request $request, Store $store, string $eventId, string $ticketTypeId)
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $event = $this->ticketService->getEvent($store->btcpay_store_id, $eventId, $userApiKey);
        $hasMaxCapacity = $event['hasMaximumCapacity'] ?? false;

        $data = array_filter($request->only([
            'name',
            'price',
            'description',
            'quantity',
        ]), fn ($v) => $v !== null && $v !== '');
        $data['isDefault'] = $request->boolean('isDefault');
        $q = array_key_exists('quantity', $data) ? (int) $data['quantity'] : null;
        if ($hasMaxCapacity && ($q === null || $q < 1)) {
            return response()->json(['message' => __('messages.tickets_quantity_required_when_capacity')], 422);
        }
        if (! array_key_exists('quantity', $data) || $q < 1) {
            $data['quantity'] = TicketService::UNLIMITED_QUANTITY;
        }

        if ($data['isDefault']) {
            $existing = $this->ticketService->listTicketTypes($store->btcpay_store_id, $eventId, $userApiKey);
            foreach ($existing as $tt) {
                if (($tt['id'] ?? '') !== $ticketTypeId) {
                    $this->ticketService->updateTicketType($store->btcpay_store_id, $eventId, $tt['id'], ['isDefault' => false], $userApiKey);
                }
            }
        }

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

<?php

namespace App\Services\Tickets;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\TicketService;
use Illuminate\Validation\ValidationException;

/** The plugin owns the timezone catalog and civil-time conversion. */
class EventTimeZoneService
{
    private const EVENT_TIME_ERROR_FIELDS = [
        'event-time-zone-invalid' => 'timeZone',
        'event-start-local-invalid' => 'startLocal',
        'event-start-local-nonexistent' => 'startLocal',
        'event-start-local-ambiguous' => 'startLocal',
        'event-end-local-invalid' => 'endLocal',
        'event-end-local-nonexistent' => 'endLocal',
        'event-end-local-ambiguous' => 'endLocal',
        'event-date-conflict' => 'startLocal',
        'event-date-range-invalid' => 'endLocal',
    ];

    public function __construct(protected TicketService $tickets) {}

    /** @return array{timeZones: list<string>} */
    public function catalog(Store $store): array
    {
        /** @var User $owner */
        $owner = $store->user;
        $result = $this->tickets->getEventTimeZones($store->btcpay_store_id, $owner->getBtcPayApiKeyOrFail());
        $zones = $result['timeZones'] ?? null;
        if (! is_array($zones) || ! array_is_list($zones) || count($zones) < 1 || count($zones) > 2000
            || collect($zones)->contains(fn ($zone) => ! is_string($zone) || strlen($zone) > 100
                || ! preg_match('~\A(?:UTC|[A-Za-z0-9_+\-]+(?:/[A-Za-z0-9_+\-]+)+)\z~D', $zone))
            || count(array_unique($zones, SORT_REGULAR)) !== count($zones)) {
            throw new BtcPayException('The event timezone catalog is unavailable.', 502, null, 'ticket_event_timezone_unavailable');
        }

        return ['timeZones' => $zones];
    }

    public function usesTimeZoneFields(array $payload): bool
    {
        return isset($payload['timeZone']) || array_key_exists('startLocal', $payload) || array_key_exists('endLocal', $payload);
    }

    /** Positive capability validation prevents old plugins silently ignoring new fields. */
    public function validatePayload(Store $store, array $payload): void
    {
        foreach (['start', 'end'] as $field) {
            if (array_key_exists($field.'Local', $payload) && array_key_exists($field.'Date', $payload)) {
                throw ValidationException::withMessages([$field.'Local' => ['event-date-conflict']]);
            }
        }
        if (! $this->usesTimeZoneFields($payload)) {
            return;
        }

        $zones = $this->catalog($store)['timeZones'];
        $zone = $payload['timeZone'] ?? null;
        if ($zone !== null && ! in_array($zone, $zones, true)) {
            throw ValidationException::withMessages(['timeZone' => ['event-time-zone-invalid']]);
        }
        if ((isset($payload['startLocal']) || isset($payload['endLocal'])) && $zone === null) {
            throw ValidationException::withMessages(['timeZone' => ['event-time-zone-invalid']]);
        }
    }

    public function isEventTimeError(BtcPayException $exception): bool
    {
        $code = $exception->getErrorCode();

        return $code !== null && isset(self::EVENT_TIME_ERROR_FIELDS[$code]);
    }

    /** @return array{code: string, errors?: array<string, list<string>>} */
    public function error(BtcPayException $exception): array
    {
        $code = $exception->getErrorCode();

        if ($code !== null && isset(self::EVENT_TIME_ERROR_FIELDS[$code])) {
            return ['code' => $code, 'errors' => [self::EVENT_TIME_ERROR_FIELDS[$code] => [$code]]];
        }

        return ['code' => match ($exception->getStatusCode()) {
            404 => 'ticket_event_timezone_unsupported',
            400, 422 => 'ticket_event_time_invalid',
            default => 'ticket_event_timezone_unavailable',
        }];
    }
}

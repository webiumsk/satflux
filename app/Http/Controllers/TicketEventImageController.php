<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketEventImageController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    /** Legacy pre-event uploads cannot safely associate a file with an owned event. */
    public function upload(Request $request, Store $store)
    {
        return response()->json([
            'message' => 'Create the event before uploading its image.',
            'code' => 'ticket_event_required',
        ], 410);
    }

    /** Upload a logo with the store owner's scoped merchant key, like a store logo. */
    public function uploadLogo(Request $request, Store $store, string $eventId)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        try {
            $event = $this->ticketService->uploadEventLogo(
                $store->btcpay_store_id,
                $eventId,
                $request->file('file'),
                $store->user->getBtcPayApiKeyOrFail()
            );

            $event = $this->normalizeEventLogo($event);
            if (trim((string) $event['eventLogoUrl']) === '') {
                return response()->json([
                    'message' => 'The ticket plugin must return the saved image URL. Refresh the event before retrying.',
                    'code' => 'ticket_event_logo_response_incomplete',
                ], 502);
            }

            return response()->json(['data' => $event]);
        } catch (\Exception $e) {
            return $this->logoFailure($e, $store, 'upload');
        }
    }

    /** Delete only through the event-scoped endpoint; never accept an arbitrary file ID. */
    public function deleteLogo(Request $request, Store $store, string $eventId)
    {
        try {
            $event = $this->ticketService->deleteEventLogo(
                $store->btcpay_store_id,
                $eventId,
                $store->user->getBtcPayApiKeyOrFail()
            );

            return response()->json(['data' => $this->normalizeEventLogo($event)]);
        } catch (\Exception $e) {
            return $this->logoFailure($e, $store, 'delete');
        }
    }

    protected function logoFailure(\Exception $exception, Store $store, string $operation)
    {
        $upstreamStatus = $exception instanceof BtcPayException ? $exception->getStatusCode() : null;
        Log::warning('Ticket event logo operation failed', [
            'store_id' => $store->id,
            'operation' => $operation,
            'upstream_status' => $upstreamStatus,
        ]);

        return response()->json([
            'message' => 'Unable to '.$operation.' the event image. Please try again.',
            'code' => 'ticket_event_logo_'.$operation.'_failed',
        ], in_array($upstreamStatus, [403, 404, 422], true) ? $upstreamStatus : 502);
    }

    protected function normalizeEventLogo(array $event): array
    {
        unset($event['storeId']);
        $event['eventLogoUrl'] = $event['eventLogoUrl'] ?? $event['logoUrl'] ?? '';
        $event['eventLogoFileId'] = $event['eventLogoFileId'] ?? '';

        return $event;
    }
}

<?php

namespace App\Support\Invoicing\Efaktura;

use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use Carbon\Carbon;

final class SapiSkSendPayload
{
    public const PROCESS_BILLING = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * @return array<string, mixed>
     */
    public static function build(
        BusinessDocument $document,
        string $senderParticipantId,
        string $receiverParticipantId,
        string $ublXml,
    ): array {
        $document->loadMissing(['company', 'contact']);

        return [
            'metadata' => [
                'documentId' => (string) $document->id,
                'documentTypeId' => self::documentTypeId($document->type),
                'processId' => self::PROCESS_BILLING,
                'senderParticipantId' => $senderParticipantId,
                'receiverParticipantId' => $receiverParticipantId,
                'creationDateTime' => $document->issue_date
                    ? Carbon::parse($document->issue_date)->toIso8601String()
                    : now()->toIso8601String(),
            ],
            'payload' => $ublXml,
            'payloadFormat' => 'XML',
            'payloadEncoding' => 'UTF-8',
        ];
    }

    public static function documentTypeId(BusinessDocumentType $type): string
    {
        return match ($type) {
            BusinessDocumentType::CreditNote => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote',
            default => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice',
        };
    }
}

<?php

namespace App\Services\Invoicing\Efaktura;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Fixed-origin, read-only Enterprise access. Never report provider exceptions. */
class EpostmanEnterpriseClient
{
    public const ORIGINS = ['https://postman.slovakodata.com', 'https://testpostman.slovakodata.com'];

    public const STATUSES = ['PENDING', 'VALIDATING', 'VALIDATED', 'VALIDATION_FAILED', 'READY', 'SENDING', 'DELIVERED', 'DELIVERED_NON_PEPPOL', 'ACCEPTED', 'REJECTED', 'CANCELLED', 'UNDELIVERABLE', 'UNCONFIRMED', 'FAILED', 'RECEIVED', 'ACKNOWLEDGED'];

    public const MLS = ['AP', 'RE', 'AB', 'CA', 'IP', 'OK', 'NOK', 'ERROR', 'PENDING', 'ACCEPTED', 'REJECTED', 'DELIVERED', 'FAILED', 'SUCCESS', 'PROCESSED', 'TIMEOUT'];

    /** @return array{observation: ?array, error_code: ?string} */
    public function invoice(string $origin, string $key, string $id, array $binding): array
    {
        if (! in_array($origin, self::ORIGINS, true) || ! Str::isUuid($id)
            || preg_match('/^[\x21-\x7E]{1,4096}$/D', $key) !== 1) {
            return ['observation' => null, 'error_code' => 'configuration_error'];
        }
        try {
            $memory = Utils::streamFor(fopen('php://memory', 'r+'));
            $sink = FnStream::decorate($memory, ['write' => static function (string $bytes) use ($memory): int {
                if ($memory->getSize() + strlen($bytes) > 1048576) {
                    throw new \RuntimeException('response_invalid');
                }

                return $memory->write($bytes);
            }]);
            $response = Http::acceptJson()->withHeaders(['X-Api-Key' => $key])
                ->withUserAgent('Satflux-Epostman-Status/1.0')->connectTimeout(5)->timeout(10)
                ->withoutRedirecting()->withOptions(['sink' => $sink, 'proxy' => '', 'verify' => true])
                ->get($origin.'/api/v1/invoices/'.$id);
            if ($response->status() !== 200) {
                $code = match ($response->status()) {
                    401, 403 => 'authentication_failed', 404 => 'not_found', 429 => 'rate_limited',
                    default => 'provider_unavailable',
                };

                return ['observation' => null, 'error_code' => $code];
            }
            if (strlen($response->body()) > 1048576) {
                return ['observation' => null, 'error_code' => 'response_invalid'];
            }
            $object = json_decode($response->body(), false, 32, JSON_THROW_ON_ERROR);
            if (! $object instanceof \stdClass) {
                return ['observation' => null, 'error_code' => 'response_invalid'];
            }
            $data = (array) $object;
            $required = ['id', 'organizationId', 'invoiceUuid', 'direction', 'documentType', 'status', 's3Key', 'retentionUntil', 'archiveStatus', 'createdAt', 'updatedAt'];
            $nullable = ['mlsC3Status', 'mlsC5Status', 'senderPeppolId', 'receiverPeppolId', 'processId', 'documentTypeId', 'creationDateTime', 'clientDocumentId', 'acknowledgedAt', 'supplierName', 'supplierId', 'buyerName', 'buyerId', 'invoiceNumber', 'issueDate', 'dueDate', 'totalAmount', 'currency', 'source'];
            foreach (array_merge($required, $nullable) as $field) {
                if (! array_key_exists($field, $data) || (! is_string($data[$field]) && ! ($data[$field] === null && in_array($field, $nullable, true)))) {
                    return ['observation' => null, 'error_code' => 'response_invalid'];
                }
            }
            $expected = ['id' => $id, 'clientDocumentId' => $binding['documentId'] ?? null,
                'senderPeppolId' => $binding['senderParticipantId'] ?? null,
                'receiverPeppolId' => $binding['receiverParticipantId'] ?? null,
                'documentTypeId' => $binding['documentTypeId'] ?? null, 'processId' => $binding['processId'] ?? null];
            foreach ($expected as $field => $value) {
                if (! is_string($value) || $value === '' || $data[$field] !== $value) {
                    return ['observation' => null, 'error_code' => 'identity_mismatch'];
                }
            }

            return ['observation' => ['status' => $this->token($data['status'], self::STATUSES),
                'mls_c3_status' => $this->token($data['mlsC3Status'], self::MLS),
                'mls_c5_status' => $this->token($data['mlsC5Status'], self::MLS)], 'error_code' => null];
        } catch (\JsonException) {
            return ['observation' => null, 'error_code' => 'response_invalid'];
        } catch (\Throwable) {
            return ['observation' => null, 'error_code' => 'network_error'];
        }
    }

    protected function token(?string $value, array $allowed): ?string
    {
        return $value === null ? null : (in_array($value, $allowed, true) ? $value : 'OTHER');
    }
}

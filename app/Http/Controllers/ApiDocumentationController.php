<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiDocumentationController extends Controller
{
    public function catalog(): JsonResponse
    {
        $descriptions = [
            'satflux' => ['Satflux', 'Session and CSRF authentication; representative account, store and raffle contracts.'],
            'woocommerce' => ['Satflux for WooCommerce', 'Connect code exchange and six scoped integration-token endpoints.'],
            'webhooks' => ['Inbound webhooks', 'BTCPay raw-body HMAC and bank inbound native/Mailgun authentication.'],
        ];
        $documents = [];
        foreach ($descriptions as $id => [$title, $description]) {
            $documents[] = compact('id', 'title', 'description') + ['url' => '/api/admin/api-docs/specs/'.$id];
        }

        return $this->privateJson([
            'documents' => $documents,
            'providers' => [
                ['id' => 'chorala', 'title' => 'Chorala OpenAPI', 'url' => 'https://github.com/webiumsk/chorala/blob/main/packages/sdk/openapi.json', 'description' => 'Provider-maintained specification; independent version and authentication.'],
                ['id' => 'btcpay', 'title' => 'BTCPay Greenfield', 'url' => 'https://docs.btcpayserver.org/API/Greenfield/v1/', 'description' => 'Official Greenfield API reference; check the installed host and plugin versions.'],
                ['id' => 'woocommerce-core', 'title' => 'WooCommerce REST API', 'url' => 'https://developer.woocommerce.com/docs/apis/rest-api/', 'description' => 'WooCommerce core API; separate from the Satflux integration contract.'],
            ],
        ]);
    }

    public function specification(string $document): JsonResponse
    {
        abort_unless(in_array($document, ['satflux', 'woocommerce', 'webhooks'], true), 404);
        $path = base_path('docs/api/'.$document.'.json');
        abort_unless(is_file($path), 503);

        return $this->privateJson(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    private function privateJson(array $data): JsonResponse
    {
        return response()->json($data, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

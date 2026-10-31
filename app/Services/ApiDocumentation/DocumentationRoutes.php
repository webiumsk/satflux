<?php

namespace App\Services\ApiDocumentation;

use Illuminate\Routing\Route;

final class DocumentationRoutes
{
    /** @return array<string, string> */
    public static function selected(): array
    {
        return [
            'GET api/user' => 'satflux',
            'GET api/user/limits' => 'satflux',
            'GET api/stores' => 'satflux',
            'GET api/stores/{store}/raffles' => 'satflux',
            'POST api/stores/{store}/raffles' => 'satflux',
            'GET api/integrations/woocommerce/oauth-exchange' => 'woocommerce',
            'GET api/integrations/woocommerce/connection' => 'woocommerce',
            'POST api/integrations/woocommerce/contacts/upsert' => 'woocommerce',
            'POST api/integrations/woocommerce/documents' => 'woocommerce',
            'GET api/integrations/woocommerce/documents/{documentId}' => 'woocommerce',
            'GET api/integrations/woocommerce/documents/{documentId}/pdf' => 'woocommerce',
            'POST api/integrations/woocommerce/documents/{documentId}/issue' => 'woocommerce',
            'POST api/webhooks/btcpay' => 'webhooks',
            'POST api/webhooks/bank-inbound' => 'webhooks',
            'POST api/webhooks/bank-inbound.mime' => 'webhooks',
        ];
    }

    public static function includes(Route $route): bool
    {
        foreach ($route->methods() as $method) {
            if (isset(self::selected()[$method.' '.trim($route->uri(), '/')])) {
                return true;
            }
        }

        return false;
    }
}

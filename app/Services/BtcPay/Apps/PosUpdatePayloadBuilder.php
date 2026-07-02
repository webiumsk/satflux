<?php

namespace App\Services\BtcPay\Apps;

use Illuminate\Support\Facades\Log;

/**
 * Maps satflux PoS (and other non-crowdfund) app config to the Greenfield
 * PUT body. Extracted verbatim from AppService::updateApp.
 */
class PosUpdatePayloadBuilder
{
    /**
     * @return array PUT body for /api/v1/apps/pos/{appId}
     */
    public function build(array $config): array
    {
        $filteredConfig = [];

        $fieldMapping = [
            'appName' => 'appName',
            'archived' => 'archived',
            'title' => 'title',
            'description' => 'description',
            'defaultView' => 'defaultView',
            'currency' => 'currency',
            'showItems' => 'showItems',
            'showCustomAmount' => 'showCustomAmount',
            'showDiscount' => 'showDiscount',
            'showSearch' => 'showSearch',
            'showCategories' => 'showCategories',
            'enableTips' => 'enableTips',
            'tipsMessage' => 'tipText', // BTCPay uses 'tipText'
            'defaultTaxRate' => 'defaultTaxRate',
            'fixedAmountPayButtonText' => 'fixedAmountPayButtonText',
            'customAmountPayButtonText' => 'customAmountPayButtonText',
            'htmlLang' => 'htmlLang',
            'htmlMetaTags' => 'htmlMetaTags',
            'redirectUrl' => 'redirectUrl',
            'redirectAutomatically' => 'redirectAutomatically',
            'notificationUrl' => 'notificationUrl',
        ];

        foreach ($fieldMapping as $ourField => $btcpayField) {
            if (array_key_exists($ourField, $config)) {
                $value = $config[$ourField];
                // Skip null values (but keep empty strings, 0, false, and empty arrays)
                if ($value !== null) {
                    $filteredConfig[$btcpayField] = $value;
                }
            }
        }

        // Map requestCustomerData to request field.
        // BTCPay expects: "email", "name", or "email,name" (comma-separated);
        // the 'request' field is required, so always include it.
        if (isset($config['requestCustomerData'])) {
            $requestValue = $config['requestCustomerData'];
            $requestMapping = [
                'email' => 'email',
                'name' => 'name',
                'email_name' => 'email,name',
                '' => '',
            ];
            $filteredConfig['request'] = $requestMapping[$requestValue] ?? ($requestValue ?: '');
        } else {
            $filteredConfig['request'] = '';
        }

        // Template must be a valid JSON string or array (not double-encoded)
        if (isset($config['template'])) {
            $template = $config['template'];
            if ($template !== null && $template !== '') {
                if (is_string($template)) {
                    $decoded = json_decode($template, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $filteredConfig['template'] = json_encode(AppItemTemplateNormalizer::normalizePriceTypes($decoded));
                    } elseif (json_last_error() === JSON_ERROR_NONE) {
                        $filteredConfig['template'] = $template;
                    } else {
                        Log::warning('Invalid JSON in template field, attempting to fix', [
                            'template' => substr($template, 0, 100),
                        ]);
                        $filteredConfig['template'] = json_encode([$template]);
                    }
                } elseif (is_array($template)) {
                    $filteredConfig['template'] = json_encode(AppItemTemplateNormalizer::normalizePriceTypes($template));
                }
            }
        }

        return $filteredConfig;
    }
}

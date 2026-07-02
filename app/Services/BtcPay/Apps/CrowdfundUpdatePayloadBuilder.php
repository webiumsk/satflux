<?php

namespace App\Services\BtcPay\Apps;

use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

/**
 * Maps satflux crowdfund config to the Greenfield CrowdfundAppRequest PUT body.
 * Extracted verbatim from AppService::updateApp - the mapping quirks
 * (id enforcement, sounds default, resetEvery consistency, formId semantics)
 * mirror BTCPay server behavior and are covered by AppTest.
 */
class CrowdfundUpdatePayloadBuilder
{
    /**
     * @param  array  $config  Merged config (existing app + incoming delta)
     * @return array PUT body for /api/v1/apps/crowdfund/{appId}
     *
     * @throws BtcPayException when $appId is empty (PUT without id would create a new app)
     */
    public function build(array $config, string $appId, string $storeId): array
    {
        $filteredConfig = [];

        // For Crowdfund PUT body (CrowdfundAppRequest / AppBaseData), include id, storeId, appType.
        // IMPORTANT: id must match the app being updated - never trust config['id'],
        // it may carry an old/wrong id from previous BTCPay responses or DB merge.
        if (isset($config['id'])) {
            Log::warning('Found id in config for Crowdfund update - removing it, will use appId parameter', [
                'appId_parameter' => $appId,
                'config_id' => $config['id'],
            ]);
            unset($config['id']);
        }

        if ($appId === '') {
            throw new BtcPayException('Cannot update Crowdfund app: appId parameter is required. Without it, a new app would be created instead.', 400);
        }

        $filteredConfig['id'] = $appId;
        $filteredConfig['storeId'] = $storeId;
        // appType must be 'Crowdfund', not 'PointOfSale' (BTCPay documentation has a typo)
        $filteredConfig['appType'] = 'Crowdfund';

        // Direct mapping for fields that match BTCPay API exactly
        $directFields = [
            'appName',
            'title',
            'tagline',
            'description',
            'targetAmount',
            'targetCurrency',
            'mainImageUrl',
            'htmlLang',
            'htmlMetaTags',
            'notificationUrl',
            'disqusShortname',
            'resetEvery',
            'displayPerksValue',
            'displayPerksRanking',
            'sortPerksByPopularity',
            'animationColors',
            'formId',
        ];

        // Handle sounds separately - only keep one sound URL
        if (isset($config['sounds'])) {
            $sounds = $config['sounds'];
            if (is_array($sounds) && ! empty($sounds)) {
                // Keep only the first sound (doublekill.wav)
                $filteredSounds = array_filter($sounds, function ($sound) {
                    return strpos($sound, 'doublekill.wav') !== false;
                });

                if (empty($filteredSounds)) {
                    $filteredConfig['sounds'] = [reset($sounds)];
                } else {
                    $filteredConfig['sounds'] = [reset($filteredSounds)];
                }
            } elseif (is_array($sounds) && $sounds === []) {
                // Explicit empty list (e.g. sounds disabled in UI) - do not inject default
                $filteredConfig['sounds'] = [];
            } else {
                $filteredConfig['sounds'] = ['https://github.com/ClaudiuHKS/AdvancedQuakeSounds/tree/master/sound/AQS/doublekill.wav'];
            }
        } else {
            $filteredConfig['sounds'] = ['https://github.com/ClaudiuHKS/AdvancedQuakeSounds/tree/master/sound/AQS/doublekill.wav'];
        }

        // Date fields must be integers (UNIX timestamps)
        if (isset($config['startDate']) && $config['startDate'] !== null && $config['startDate'] !== '') {
            $startDate = $config['startDate'];
            if (is_string($startDate)) {
                $timestamp = strtotime($startDate);
                if ($timestamp !== false) {
                    $filteredConfig['startDate'] = (int) $timestamp;
                }
            } elseif (is_numeric($startDate)) {
                $filteredConfig['startDate'] = (int) $startDate;
            }
        }

        if (isset($config['endDate']) && $config['endDate'] !== null && $config['endDate'] !== '') {
            $endDate = $config['endDate'];
            if (is_string($endDate)) {
                $timestamp = strtotime($endDate);
                if ($timestamp !== false) {
                    $filteredConfig['endDate'] = (int) $timestamp;
                }
            } elseif (is_numeric($endDate)) {
                $filteredConfig['endDate'] = (int) $endDate;
            }
        }

        // If resetEveryAmount is set (not 0), startDate is required by BTCPay API
        $hasResetEveryAmount = isset($filteredConfig['resetEveryAmount']) && $filteredConfig['resetEveryAmount'] != 0;
        if ($hasResetEveryAmount && ! isset($filteredConfig['startDate'])) {
            $filteredConfig['startDate'] = (int) time();
            Log::warning('startDate was required but missing, using current timestamp', [
                'resetEveryAmount' => $filteredConfig['resetEveryAmount'] ?? null,
            ]);
        }

        // Boolean fields that need explicit conversion
        $booleanFields = [
            'enabled',
            'enforceTargetAmount',
            'soundsEnabled',
            'animationsEnabled',
            'disqusEnabled',
            'resetEveryAmount',
            'displayPerksValue',
            'displayPerksRanking',
            'sortPerksByPopularity',
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $config)) {
                $value = $config[$field];
                if ($value !== null) {
                    $filteredConfig[$field] = $value;
                }
            }
        }

        // SPA uses displayTitle; Greenfield Crowdfund expects title (CrowdfundBaseData).
        if (array_key_exists('displayTitle', $config)) {
            $filteredConfig['title'] = $config['displayTitle'];
        }

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $config)) {
                $value = $config[$field];
                if ($value !== null) {
                    $filteredConfig[$field] = (bool) $value;
                }
            }
        }

        // Map our internal field names to BTCPay API field names
        $fieldMapping = [
            'makePublic' => 'enabled',
            'currency' => 'targetCurrency',
            'enableSounds' => 'soundsEnabled',
            'enableAnimations' => 'animationsEnabled',
            'enableDiscussion' => 'disqusEnabled',
            'callbackNotificationUrl' => 'notificationUrl',
        ];

        foreach ($fieldMapping as $ourField => $btcpayField) {
            if (array_key_exists($ourField, $config) && ! isset($filteredConfig[$btcpayField])) {
                $value = $config[$ourField];
                if ($value !== null) {
                    if ($ourField === 'makePublic') {
                        $filteredConfig[$btcpayField] = (bool) $value;
                    } else {
                        $filteredConfig[$btcpayField] = $value;
                    }
                }
            }
        }

        // featuredImageUrl is client-only; merged config may still carry mainImageUrl from BTCPay.
        if (array_key_exists('featuredImageUrl', $config)) {
            $v = $config['featuredImageUrl'];
            $filteredConfig['mainImageUrl'] = ($v === null || $v === '') ? '' : (string) $v;
        }

        // BTCPay expects 'perksTemplate' as JSON string (not array).
        // AppItemPriceType is only Fixed, Topup, Minimum - normalize UI "Free" to Fixed + 0.
        $perksSource = $config['perks'] ?? $config['items'] ?? $config['template'] ?? null;
        if ($perksSource !== null && $perksSource !== '') {
            if (is_array($perksSource)) {
                $filteredConfig['perksTemplate'] = json_encode(AppItemTemplateNormalizer::normalizePriceTypes($perksSource));
            } elseif (is_string($perksSource)) {
                $decoded = json_decode($perksSource, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filteredConfig['perksTemplate'] = json_encode(AppItemTemplateNormalizer::normalizePriceTypes($decoded));
                } elseif (json_last_error() === JSON_ERROR_NONE) {
                    $filteredConfig['perksTemplate'] = $perksSource;
                } else {
                    Log::warning('Invalid JSON in perks field, attempting to fix', [
                        'perks' => substr($perksSource, 0, 100),
                    ]);
                    $filteredConfig['perksTemplate'] = json_encode([]);
                }
            }
        } else {
            $filteredConfig['perksTemplate'] = '[]';
        }

        // Safety check: config processing above must never displace the id.
        if ($filteredConfig['id'] !== $appId) {
            Log::error('CRITICAL: Crowdfund id was wrong or missing in filteredConfig - fixing it!', [
                'appId_parameter' => $appId,
                'filteredConfig_id' => $filteredConfig['id'] ?? 'MISSING',
            ]);
            $filteredConfig['id'] = $appId;
        }

        // Contributions settings - map to BTCPay field names
        if (isset($config['contributions']) && is_array($config['contributions'])) {
            $contributions = $config['contributions'];
            if (isset($contributions['sortByPopularity'])) {
                $filteredConfig['sortPerksByPopularity'] = (bool) $contributions['sortByPopularity'];
            }
            if (isset($contributions['displayRanking'])) {
                $filteredConfig['displayPerksRanking'] = (bool) $contributions['displayRanking'];
            }
            if (isset($contributions['displayValue'])) {
                $filteredConfig['displayPerksValue'] = (bool) $contributions['displayValue'];
            }
            if (isset($contributions['noAdditionalAfterTarget'])) {
                $filteredConfig['enforceTargetAmount'] = (bool) $contributions['noAdditionalAfterTarget'];
            }
        }

        [$resetEveryAmount, $resetEvery] = $this->normalizeResetEvery($config);
        $filteredConfig['resetEveryAmount'] = $resetEveryAmount;
        if ($resetEvery !== null) {
            $filteredConfig['resetEvery'] = $resetEvery;
        }

        // If resetEveryAmount > 0 and resetEvery is not 'Never', ensure startDate is set
        if ($resetEveryAmount > 0 && $resetEvery !== 'Never' && $resetEvery !== null) {
            if (! isset($filteredConfig['startDate']) || $filteredConfig['startDate'] === null) {
                $filteredConfig['startDate'] = now()->timestamp;
                Log::info('Auto-setting startDate for recurring goal', [
                    'resetEveryAmount' => $resetEveryAmount,
                    'resetEvery' => $resetEvery,
                    'startDate' => $filteredConfig['startDate'],
                ]);
            }
        }

        // Crowdfund: Greenfield uses formId (BTCPay UI: same keys as FormDataService - "", Email, Address, or a store form UUID).
        // Never persist formId as ""; that value hits a BTCPay code path with a null Form and NREs in the crowdfund UI.
        if (isset($config['checkout']) && is_array($config['checkout'])) {
            if (array_key_exists('formId', $config['checkout'])) {
                $v = $config['checkout']['formId'];
                if ($v === null || $v === '' || (is_string($v) && trim($v) === '')) {
                    $filteredConfig['formId'] = null;
                } else {
                    $filteredConfig['formId'] = is_string($v) ? trim($v) : (string) $v;
                }
            } elseif (
                array_key_exists('requestContributorData', $config['checkout'])
                && ! (bool) $config['checkout']['requestContributorData']
            ) {
                $filteredConfig['formId'] = null;
            }
        }

        // Ensure all boolean fields are actually boolean (not strings) at the end.
        // NOTE: resetEveryAmount is NOT a boolean, it's a number (0 or 1)!
        $booleanFieldNames = [
            'enabled',
            'enforceTargetAmount',
            'soundsEnabled',
            'animationsEnabled',
            'disqusEnabled',
            'displayPerksValue',
            'displayPerksRanking',
            'sortPerksByPopularity',
        ];
        foreach ($booleanFieldNames as $field) {
            if (array_key_exists($field, $filteredConfig)) {
                $originalValue = $filteredConfig[$field];
                unset($filteredConfig[$field]);
                if (is_string($originalValue)) {
                    $filteredConfig[$field] = in_array(strtolower($originalValue), ['true', '1', 'yes'], true);
                } else {
                    $filteredConfig[$field] = (bool) $originalValue;
                }
            }
        }

        // Convert resetEveryAmount to number (0 or 1), not boolean
        if (array_key_exists('resetEveryAmount', $filteredConfig)) {
            $originalValue = $filteredConfig['resetEveryAmount'];
            unset($filteredConfig['resetEveryAmount']);
            if (is_string($originalValue)) {
                $filteredConfig['resetEveryAmount'] = in_array(strtolower($originalValue), ['true', '1', 'yes'], true) ? 1 : 0;
            } else {
                $filteredConfig['resetEveryAmount'] = (bool) $originalValue ? 1 : 0;
            }
        }

        // Advanced settings
        if (isset($config['advanced']) && is_array($config['advanced'])) {
            $advanced = $config['advanced'];
            if (isset($advanced['htmlLanguage'])) {
                $filteredConfig['htmlLang'] = $advanced['htmlLanguage'];
            }
            if (isset($advanced['htmlMetaTags'])) {
                $filteredConfig['htmlMetaTags'] = $advanced['htmlMetaTags'];
            }
            if (isset($advanced['enableSounds'])) {
                $filteredConfig['soundsEnabled'] = (bool) $advanced['enableSounds'];
            }
            if (isset($advanced['enableAnimations'])) {
                $filteredConfig['animationsEnabled'] = (bool) $advanced['enableAnimations'];
            }
            if (isset($advanced['enableDiscussion'])) {
                $filteredConfig['disqusEnabled'] = (bool) $advanced['enableDiscussion'];
            }
            if (isset($advanced['callbackNotificationUrl'])) {
                $filteredConfig['notificationUrl'] = $advanced['callbackNotificationUrl'];
            }
        }

        if (array_key_exists('formId', $filteredConfig)) {
            $fid = $filteredConfig['formId'];
            if ($fid === null || $fid === '' || (is_string($fid) && trim($fid) === '')) {
                $filteredConfig['formId'] = null;
            }
        }

        return $filteredConfig;
    }

    /**
     * Normalize the resetEveryAmount/resetEvery pair to a consistent state:
     * amount 0 forces 'Never'; a positive amount cannot combine with 'Never'.
     *
     * @return array{0: int, 1: string|null}
     */
    protected function normalizeResetEvery(array $config): array
    {
        $resetEveryAmount = null;
        if (isset($config['resetEveryAmount'])) {
            $resetValue = $config['resetEveryAmount'];
            if (is_bool($resetValue)) {
                $resetEveryAmount = $resetValue ? 1 : 0;
            } elseif (is_numeric($resetValue)) {
                $resetEveryAmount = (int) $resetValue;
            } else {
                $resetEveryAmount = 0;
            }
        }

        $resetEvery = null;
        if (isset($config['resetEvery'])) {
            $resetEveryValue = $config['resetEvery'];
            if (is_string($resetEveryValue) && in_array($resetEveryValue, ['Day', 'Hour', 'Week', 'Month', 'Year', 'Never'])) {
                $resetEvery = $resetEveryValue;
            }
        }

        if ($resetEveryAmount !== null) {
            if ($resetEveryAmount === 0) {
                $resetEvery = 'Never';
            } else {
                if ($resetEvery === 'Never' || $resetEvery === null) {
                    $resetEvery = 'Day';
                }
                if ($resetEveryAmount < 1) {
                    $resetEveryAmount = 1;
                }
            }
        } else {
            if ($resetEvery !== null && $resetEvery !== 'Never') {
                $resetEveryAmount = 1;
            } else {
                $resetEveryAmount = 0;
                $resetEvery = 'Never';
            }
        }

        return [$resetEveryAmount, $resetEvery];
    }
}

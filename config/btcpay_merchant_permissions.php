<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merchant API Key Permissions (single source of truth)
    |--------------------------------------------------------------------------
    | Permissions for User.btcpay_api_key - the merchant-level key created
    | during email verification. Used by UserService::createApiKey and
    | MerchantApiKeyService::upgradeApiKey.
    |
    | Add new permissions here when implementing features (e.g. user roles,
    | multi-user store management). Run php artisan btcpay:upgrade-merchant-api-keys
    | to upgrade existing users' keys.
    */

    'merchant_api_key' => [
        'btcpay.store.cancreateinvoice',
        'btcpay.store.canviewstoresettings',
        'btcpay.store.canmodifyinvoices',
        'btcpay.store.canmodifystoresettings',
        'btcpay.store.canviewinvoices',
        'btcpay.user.canviewnotificationsforuser',
        'btcpay.user.canmanagenotificationsforuser',
        // Future: 'btcpay.user.canmanageusersforuser', 'btcpay.store.canviewusers', ...
    ],

];

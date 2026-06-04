<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beta override for Pro company limit
    |--------------------------------------------------------------------------
    | When set (e.g. 5), Pro users can create up to this many invoicing companies
    | instead of the plan's max_companies in the database. Leave empty in production.
    */

    'beta_pro_max_companies' => env('INVOICING_BETA_PRO_MAX_COMPANIES') !== null && env('INVOICING_BETA_PRO_MAX_COMPANIES') !== ''
        ? (int) env('INVOICING_BETA_PRO_MAX_COMPANIES')
        : null,

];

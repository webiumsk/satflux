<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System monitoring (P1 phase 8)
    |--------------------------------------------------------------------------
    |
    | The scheduled system:health-check persists snapshots and alerts the
    | address below on failures (throttled) and recoveries. With no address
    | configured, alerts are logged only.
    |
    */

    'alert_email' => env('SYSTEM_ALERT_EMAIL'),

    /** Minutes between repeated alert e-mails for the same failing check. */
    'alert_throttle_minutes' => (int) env('SYSTEM_ALERT_THROTTLE_MINUTES', 60),

    /** error/critical log records per hour that flip the errors check to failed. */
    'error_rate_threshold' => (int) env('SYSTEM_ERROR_RATE_THRESHOLD', 25),

    /** Days of system_health_snapshots history to keep. */
    'snapshot_retention_days' => (int) env('SYSTEM_HEALTH_RETENTION_DAYS', 7),

];

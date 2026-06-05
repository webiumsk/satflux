<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention (scheduled data:retention-run)
    |--------------------------------------------------------------------------
    |
    | Opt-in via DATA_RETENTION_ENABLED. Tune per environment; backups may
    | still hold deleted data until backup rotation expires.
    |
    */

    'enabled' => (bool) env('DATA_RETENTION_ENABLED', false),

    'batch_size' => (int) env('DATA_RETENTION_BATCH_SIZE', 200),

    /** Processed BTCPay webhook_events older than this are deleted. */
    'webhook_events_days' => (int) env('DATA_RETENTION_WEBHOOK_EVENTS_DAYS', 90),

    /** audit_logs rows older than this are deleted. */
    'audit_logs_days' => (int) env('DATA_RETENTION_AUDIT_LOGS_DAYS', 730),

    /** Export files on exports disk + finished Export rows past expires_at or age. */
    'export_files_days' => (int) env('DATA_RETENTION_EXPORT_FILES_DAYS', 30),

    /** Draft business_documents with no update older than this. */
    'draft_documents_days' => (int) env('DATA_RETENTION_DRAFT_DOCUMENTS_DAYS', 365),

    /** Soft-deleted companies force-deleted after this many days. */
    'soft_deleted_companies_days' => (int) env('DATA_RETENTION_SOFT_DELETED_COMPANIES_DAYS', 30),

    /** Cancelled business expenses (and attachment files) hard-deleted after this many days. */
    'cancelled_expenses_days' => (int) env('DATA_RETENTION_CANCELLED_EXPENSES_DAYS', 90),

    /** Remove public BTC pay token after document is marked paid. */
    'clear_payment_token_when_paid' => (bool) env('DATA_RETENTION_CLEAR_PAYMENT_TOKEN_WHEN_PAID', true),

    /** Delegates to bank_import.file_retention_days when retention job runs. */
    'bank_import_files' => true,

];

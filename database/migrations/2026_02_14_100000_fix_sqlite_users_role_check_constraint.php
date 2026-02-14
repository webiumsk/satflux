<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix SQLite role CHECK so it allows 'free', 'pro', 'enterprise'.
     * Only runs on SQLite; other drivers are already updated by earlier migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                email_verified_at TIMESTAMP,
                password VARCHAR(255) NOT NULL,
                remember_token VARCHAR(100),
                role VARCHAR(255) CHECK (role IN ('free', 'support', 'admin', 'pro', 'enterprise')) DEFAULT 'free',
                lightning_public_key VARCHAR(66) UNIQUE,
                btcpay_user_id VARCHAR(255),
                btcpay_api_key TEXT,
                btcpay_subscription_id VARCHAR(255),
                subscription_expires_at TIMESTAMP,
                subscription_grace_period_ends_at TIMESTAMP,
                last_login_at TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        DB::statement("
            INSERT INTO users_new
            SELECT id, name, email, email_verified_at, password, remember_token,
                   CASE WHEN role = 'merchant' OR role IS NULL THEN 'free' ELSE role END,
                   lightning_public_key, btcpay_user_id, btcpay_api_key,
                   btcpay_subscription_id, subscription_expires_at,
                   subscription_grace_period_ends_at, last_login_at,
                   created_at, updated_at
            FROM users
        ");

        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_new RENAME TO users');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // No-op: constraint fix is forward-only
    }
};

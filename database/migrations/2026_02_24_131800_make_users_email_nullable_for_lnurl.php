<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * LNURL-auth creates users with email=null; email is set after user completes registration.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->unique()->change();
            });
            return;
        }

        // SQLite: recreate table with email nullable (->change() would create reserved index name)
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement("
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255),
                    email VARCHAR(255) UNIQUE,
                    email_verified_at TIMESTAMP,
                    password VARCHAR(255) NOT NULL,
                    remember_token VARCHAR(100),
                    role VARCHAR(255) DEFAULT 'free',
                    lightning_public_key VARCHAR(66),
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
            DB::statement('INSERT INTO users_new SELECT * FROM users');
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("UPDATE users SET email = 'pending@lnurl.local' WHERE email IS NULL");
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->change();
        });
    }
};

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

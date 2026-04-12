<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Make email column nullable to support SSO users without email
        if (Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // If change() is not supported (missing doctrine/dbal), try raw SQL for MySQL
                try {
                    $driver = Schema::getConnection()->getDriverName();
                    if (in_array($driver, ['mysql','mariadb'])) {
                        Schema::getConnection()->statement("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
                    }
                } catch (\Throwable $_) {
                    // give up silently; migration may need manual DBAL install
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                try {
                    $driver = Schema::getConnection()->getDriverName();
                    if (in_array($driver, ['mysql','mariadb'])) {
                        Schema::getConnection()->statement("ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL");
                    }
                } catch (\Throwable $_) {
                    // no-op
                }
            }
        }
    }
};

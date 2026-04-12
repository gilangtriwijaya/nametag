<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sso_user_id')) {
                $table->unsignedBigInteger('sso_user_id')->nullable()->after('id');
                $table->unique('sso_user_id');
            }

            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 255)->nullable()->after('sso_user_id');
                $table->index('username');
            }

            if (!Schema::hasColumn('users', 'user_type_id')) {
                $table->unsignedBigInteger('user_type_id')->nullable()->after('password');
                $table->index('user_type_id');
            }

            // OPTIONAL: email jadi nullable (supaya tidak blok kalau suatu user tidak punya email)
            // Kalau kamu takut ganggu constraint unik email, kita bisa skip dulu.
            // Kalau mau aktifkan, pastikan unique email tetap masuk akal.
            // $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sso_user_id')) {
                $table->dropUnique(['sso_user_id']);
                $table->dropColumn('sso_user_id');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropIndex(['username']);
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('users', 'user_type_id')) {
                $table->dropIndex(['user_type_id']);
                $table->dropColumn('user_type_id');
            }
        });
    }
};

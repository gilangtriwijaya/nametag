<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opds', function (Blueprint $table) {
            if (!Schema::hasColumn('opds', 'sso_id')) {
                $table->unsignedBigInteger('sso_id')->nullable()->unique()->after('id');
            }
        });

        Schema::table('opd_units', function (Blueprint $table) {
            if (!Schema::hasColumn('opd_units', 'sso_id')) {
                $table->unsignedBigInteger('sso_id')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opd_units', function (Blueprint $table) {
            if (Schema::hasColumn('opd_units', 'sso_id')) {
                $table->dropUnique(['sso_id']);
                $table->dropColumn('sso_id');
            }
        });

        Schema::table('opds', function (Blueprint $table) {
            if (Schema::hasColumn('opds', 'sso_id')) {
                $table->dropUnique(['sso_id']);
                $table->dropColumn('sso_id');
            }
        });
    }
};

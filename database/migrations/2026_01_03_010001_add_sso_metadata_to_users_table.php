<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sso_app_role')) {
                $table->string('sso_app_role')->nullable()->after('sso_user_id');
            }
            if (!Schema::hasColumn('users', 'sso_app_roles')) {
                $table->json('sso_app_roles')->nullable()->after('sso_app_role');
            }
            if (!Schema::hasColumn('users', 'sso_allowed_opds_by_app')) {
                $table->json('sso_allowed_opds_by_app')->nullable()->after('sso_app_roles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sso_allowed_opds_by_app')) {
                $table->dropColumn('sso_allowed_opds_by_app');
            }
            if (Schema::hasColumn('users', 'sso_app_roles')) {
                $table->dropColumn('sso_app_roles');
            }
            if (Schema::hasColumn('users', 'sso_app_role')) {
                $table->dropColumn('sso_app_role');
            }
        });
    }
};

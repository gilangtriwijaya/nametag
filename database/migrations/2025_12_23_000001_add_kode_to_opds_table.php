<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opds', function (Blueprint $table) {
            if (!Schema::hasColumn('opds', 'kode')) {
                $table->string('kode', 50)->nullable()->after('id');
                $table->unique('kode', 'opds_kode_unique');
            }

            if (!Schema::hasColumn('opds', 'nama_resmi')) {
                $table->string('nama_resmi', 255)->nullable()->after('kode');
            }

            if (!Schema::hasColumn('opds', 'singkatan')) {
                $table->string('singkatan', 255)->nullable()->after('nama_resmi');
            }

            if (!Schema::hasColumn('opds', 'is_active')) {
                $table->tinyInteger('is_active')->default(1)->after('singkatan');
            }

            if (!Schema::hasColumn('opds', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            }

            if (!Schema::hasColumn('opds', 'can_use_nametag')) {
                $table->tinyInteger('can_use_nametag')->default(1)->after('sort_order');
            }

            if (!Schema::hasColumn('opds', 'can_use_penilaian')) {
                $table->tinyInteger('can_use_penilaian')->default(1)->after('can_use_nametag');
            }

            // pastikan slug & nama tetap ada (karena UI lama pakai ini)
            if (!Schema::hasColumn('opds', 'nama')) {
                $table->string('nama', 255)->nullable()->after('nama_resmi');
            }
            if (!Schema::hasColumn('opds', 'slug')) {
                $table->string('slug', 255)->nullable()->after('nama');
                $table->index('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opds', function (Blueprint $table) {
            if (Schema::hasColumn('opds', 'kode')) {
                $table->dropUnique('opds_kode_unique');
                $table->dropColumn('kode');
            }

            foreach (['nama_resmi','singkatan','is_active','sort_order','can_use_nametag','can_use_penilaian'] as $c) {
                if (Schema::hasColumn('opds', $c)) {
                    $table->dropColumn($c);
                }
            }
            // `nama` & `slug` jangan di-drop karena itu kemungkinan kolom lama yang dipakai aplikasi
        });
    }
};

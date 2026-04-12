<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        if (! Schema::hasColumn('employees', 'nama_unit_opd')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('nama_unit_opd', 150)->nullable()->after('unit_kerja');
            });
        }

        // Backfill from existing unit_kerja then clear unit_kerja
        try {
            DB::statement("UPDATE employees SET nama_unit_opd = unit_kerja WHERE unit_kerja IS NOT NULL");
            DB::statement("UPDATE employees SET unit_kerja = NULL WHERE unit_kerja IS NOT NULL");
        } catch (\Throwable $e) {
            // ignore; best-effort backfill
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        // restore unit_kerja from nama_unit_opd if exists
        try {
            if (Schema::hasColumn('employees', 'nama_unit_opd') && Schema::hasColumn('employees', 'unit_kerja')) {
                DB::statement("UPDATE employees SET unit_kerja = nama_unit_opd WHERE nama_unit_opd IS NOT NULL");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (Schema::hasColumn('employees', 'nama_unit_opd')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('nama_unit_opd');
            });
        }
    }
};

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

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'unit_kerja_id')) {
                $table->unsignedBigInteger('unit_kerja_id')->nullable()->after('opd_unit_id')->index();
            }
        });

        // Backfill: for each employee with nama_unit_opd, find or create unit_kerja and set FK
        try {
            DB::transaction(function () {
                $rows = DB::table('employees')
                    ->select('id','opd_id','nama_unit_opd')
                    ->whereNotNull('nama_unit_opd')
                    ->get();

                $cache = [];
                foreach ($rows as $r) {
                    $name = trim((string) $r->nama_unit_opd);
                    if ($name === '') continue;

                    $key = ($r->opd_id ?? '0') . '||' . mb_strtolower($name);
                    if (isset($cache[$key])) {
                        $unitId = $cache[$key];
                    } else {
                        // try find existing unit_kerja matching name + opd_id
                        $unit = DB::table('unit_kerja')
                            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])
                            ->where(function ($q) use ($r) {
                                if ($r->opd_id) {
                                    $q->where('opd_id', $r->opd_id);
                                }
                            })
                            ->first();

                        if ($unit) {
                            $unitId = $unit->id;
                        } else {
                            // create a new unit_kerja row
                            $unitId = DB::table('unit_kerja')->insertGetId([
                                'opd_id'    => $r->opd_id,
                                'code'      => null,
                                'nama'      => $name,
                                'status'    => 'AKTIF',
                                'alamat'    => null,
                                'kecamatan' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $cache[$key] = $unitId;
                    }

                    // update employee: set unit_kerja_id and copy nama into unit_kerja text column
                    DB::table('employees')->where('id', $r->id)->update([
                        'unit_kerja_id' => $unitId,
                        'unit_kerja'    => DB::raw("(SELECT nama FROM unit_kerja WHERE id = {$unitId} LIMIT 1)"),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // best-effort backfill; swallow errors to avoid migration failure
        }

        // add foreign key constraint if possible
        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreign('unit_kerja_id')->references('id')->on('unit_kerja')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // ignore if DB engine doesn't support adding FK here
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        try {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'unit_kerja_id')) {
                    $table->dropForeign(['unit_kerja_id']);
                    $table->dropColumn('unit_kerja_id');
                }
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

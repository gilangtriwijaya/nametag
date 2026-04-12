<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\SsoSyncLog;

class MirrorOpdFromSso extends Command
{
    protected $signature = 'sso:mirror-opd
                            {--reset : Hapus data opds & opd_units (nametag_db) lalu tarik ulang}
                            {--only=all : all|opds|units}';

    protected $description = 'Mirror master OPD & Unit dari DB SSO ke DB Nametag (pakai sso_id).';

    public function handle(): int
    {
        $only = strtolower((string) $this->option('only'));
        if (!in_array($only, ['all', 'opds', 'units'], true)) {
            $this->error("Option --only harus: all|opds|units");
            return self::INVALID;
        }

        $log = null;
        try { $log = SsoSyncLog::create(['command'=>'sso:mirror-opd','started_at'=>now(),'status'=>'running']); } catch (\Throwable $_) {}

        try {
            $this->assertSchema();

            if ((bool) $this->option('reset')) {
                $this->resetLocalMaster(); // PENTING: jangan pakai TRUNCATE dalam transaction
            }

            // Sinkronisasi dibuat atomic (tidak tercampur dengan reset)
            DB::transaction(function () use ($only) {
                if ($only === 'all' || $only === 'opds') {
                    $this->syncOpds();
                }
                if ($only === 'all' || $only === 'units') {
                    $this->syncUnits();
                }
            }, 3);

            $this->info('OK: mirror selesai.');
            try { if ($log) $log->update(['finished_at'=>now(),'status'=>'success']); } catch (\Throwable $_) {}
            return self::SUCCESS;

        } catch (\Throwable $e) {
            try { if ($log) $log->update(['finished_at'=>now(),'status'=>'failed','message'=>$e->getMessage()]); } catch (\Throwable $_) {}
            $this->error('GAGAL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Reset LOCAL master (nametag_db) tanpa TRUNCATE supaya tidak implicit commit.
     */
    private function resetLocalMaster(): void
    {
        $this->warn('RESET: delete opd_units & opds (nametag_db)');

        // Delete child dulu (FK)
        DB::table('opd_units')->delete();
        DB::table('opds')->delete();

        // Opsional: reset auto increment (MySQL/MariaDB)
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Kalau table punya data lain di FK, ini aman karena sudah delete semua.
            DB::statement('ALTER TABLE opd_units AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE opds AUTO_INCREMENT = 1');
        }

        $this->info('OK: reset local master selesai.');
    }

    private function assertSchema(): void
    {
        $needs = [
            'opds' => ['sso_id', 'nama', 'slug', 'nama_resmi'],
            'opd_units' => ['sso_id', 'opd_id', 'nama'],
        ];

        foreach ($needs as $table => $cols) {
            foreach ($cols as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    throw new \RuntimeException("Schema tidak sesuai: {$table}.{$col} tidak ada.");
                }
            }
        }
    }

    private function syncOpds(): void
    {
        $conn = DB::connection('sso');
        $schema = $conn->getSchemaBuilder();

        // Required base columns we always request from SSO
        $cols = [
            'id',
            'kode',
            'nama_resmi',
            'singkatan',
            'is_active',
            'sort_order',
            'can_use_nametag',
            'can_use_penilaian',
        ];

        // Optional fields that may not exist on older SSO schemas
        $optional = ['pimpinan', 'alamat', 'telepon', 'nip', 'pangkat', 'golongan'];
        foreach ($optional as $c) {
            try {
                if ($schema->hasColumn('opds', $c)) {
                    $cols[] = $c;
                }
            } catch (\Throwable $e) {
                // In case the remote connection/schema introspection fails,
                // skip the optional column and continue.
                continue;
            }
        }

        $rows = $conn->table('opds')->orderBy('id')->get($cols);

        $now = now();
        $inserted = 0;
        $updated  = 0;

        foreach ($rows as $r) {
            $ssoId     = (int) $r->id;
            $kode      = trim((string) ($r->kode ?? ''));
            $namaResmi = trim((string) ($r->nama_resmi ?? ''));

            $nama = $namaResmi !== '' ? $namaResmi : ($kode !== '' ? $kode : ('OPD-' . $ssoId));

            // slug harus unik di nametag_db (kolom slug unique)
            $baseSlug = Str::slug($nama);
            $slug = $baseSlug !== '' ? $baseSlug : ('opd-' . $ssoId);

            // Kalau slug sudah dipakai oleh opd lain, bikin unik pakai sso_id
            $slugTaken = DB::table('opds')
                ->where('slug', $slug)
                ->where('sso_id', '!=', $ssoId)
                ->exists();

            if ($slugTaken) {
                $slug = $slug . '-' . $ssoId;
            }

            $payload = [
                'sso_id'            => $ssoId,
                'kode'              => $kode !== '' ? $kode : null,
                'nama_resmi'        => $namaResmi !== '' ? $namaResmi : $nama,
                'singkatan'         => $r->singkatan !== null ? trim((string) $r->singkatan) : null,
                'is_active'         => (int) ($r->is_active ?? 1),
                'sort_order'        => (int) ($r->sort_order ?? 0),
                'can_use_nametag'   => (int) ($r->can_use_nametag ?? 1),
                'can_use_penilaian' => (int) ($r->can_use_penilaian ?? 1),
                // additional mirrored fields
                'pimpinan'          => isset($r->pimpinan) ? trim((string)$r->pimpinan) : null,
                'alamat'            => isset($r->alamat) ? trim((string)$r->alamat) : null,
                'telepon'           => isset($r->telepon) ? trim((string)$r->telepon) : null,
                'nip'               => isset($r->nip) ? trim((string)$r->nip) : null,
                'pangkat'           => isset($r->pangkat) ? trim((string)$r->pangkat) : null,
                'golongan'          => isset($r->golongan) ? trim((string)$r->golongan) : null,
                'nama'              => $nama,   // Wajib NOT NULL di nametag_db kamu
                'slug'              => $slug,   // Wajib UNIQUE di nametag_db kamu
                'updated_at'        => $now,
            ];

            $existingId = DB::table('opds')->where('sso_id', $ssoId)->value('id');

            if ($existingId) {
                DB::table('opds')->where('id', $existingId)->update($payload);
                $updated++;
            } else {
                $payload['created_at'] = $now;
                DB::table('opds')->insert($payload);
                $inserted++;
            }
        }

        $this->info("OK: OPD termirror. inserted={$inserted}, updated={$updated}");
    }

    private function syncUnits(): void
    {
        $conn = DB::connection('sso');
        $schema = $conn->getSchemaBuilder();

        // Required base columns we always request from SSO
        $cols = [
            'id',
            'opd_id',
            'nama_resmi',
            'is_active',
        ];

        // Optional fields that may not exist on older SSO schemas
        $optional = ['alamat', 'singkatan', 'sort_order', 'urut_tampil', 'uses_nametag', 'uses_penilaian'];
        foreach ($optional as $c) {
            try {
                if ($schema->hasColumn('opd_units', $c)) {
                    $cols[] = $c;
                }
            } catch (\Throwable $e) {
                // In case the remote connection/schema introspection fails,
                // skip the optional column and continue.
                continue;
            }
        }

        $rows = $conn->table('opd_units')->orderBy('id')->get($cols);

        // [sso_opd_id => local_opd_id]
        $opdMap = DB::table('opds')->pluck('id', 'sso_id')->all();

        $now = now();
        $skipped = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($rows as $r) {
            $ssoUnitId = (int) $r->id;
            $ssoOpdId  = (int) $r->opd_id;

            $localOpdId = $opdMap[$ssoOpdId] ?? null;
            if (!$localOpdId) {
                $skipped++;
                continue;
            }

            $namaResmi = trim((string) ($r->nama_resmi ?? ''));
            $nama = $namaResmi !== '' ? $namaResmi : ('Unit-' . $ssoUnitId);

            $status = ((int) ($r->is_active ?? 1) === 1) ? 'AKTIF' : 'NONAKTIF';

            $payload = [
                'sso_id'     => $ssoUnitId,
                'opd_id'     => (int) $localOpdId,
                'nama'       => $nama,      // Wajib NOT NULL
                'status'     => $status,    // Wajib NOT NULL (default AKTIF di schema kamu)
                'alamat'     => isset($r->alamat) ? trim((string)$r->alamat) : null,
                'updated_at' => $now,
            ];

            $existingId = DB::table('opd_units')->where('sso_id', $ssoUnitId)->value('id');

            if ($existingId) {
                DB::table('opd_units')->where('id', $existingId)->update($payload);
                $updated++;
            } else {
                $payload['created_at'] = $now;
                DB::table('opd_units')->insert($payload);
                $inserted++;
            }
        }

        $this->info("OK: Unit termirror. inserted={$inserted}, updated={$updated}, skipped={$skipped}");
    }
}

<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Opd;
use App\Models\OpdUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatistikPublikService
{
    private const CACHE_VERSION = 'v1';

    public function cacheKey(): string
    {
        return sprintf('statpub:%s:all', self::CACHE_VERSION);
    }

    public function rebuildCache(): void
    {
        $ttl = max(60, (int) config('app.publik_cache_ttl', 3600));

        Cache::store('file')->put($this->cacheKey(), $this->wrappedPayload(), $ttl);
    }

    public function wrappedPayload(): array
    {
        return [
            'success' => true,
            'app' => config('app.code', config('app.name')),
            'resource' => 'statistik',
            'generated_at' => now()->toIso8601String(),
            'cache_ttl' => max(60, (int) config('app.publik_cache_ttl', 3600)),
            'cache_hit' => false,
            'data' => $this->buildPayload(),
        ];
    }

    public function buildPayload(): array
    {
        $activeTokens = DB::table('employee_qr_tokens')
            ->select('employee_id')
            ->where('status', 'active')
            ->groupBy('employee_id');

        $summary = [
            'total_opd' => (int) DB::table('opds')->count(),
            'total_unit_kerja' => (int) DB::table('opd_units')->count(),
            'total_pegawai' => (int) DB::table('employees')->count(),
            'pegawai_aktif' => (int) DB::table('employees')->where('status_aktif', 'AKTIF')->count(),
            'pegawai_nonaktif' => (int) DB::table('employees')->where('status_aktif', 'NONAKTIF')->count(),
            'nametag_ready' => (int) DB::table('employee_qr_tokens')->distinct()->count('employee_id'),
        ];

        $byOpd = Opd::query()
            ->select([
                'opds.id',
                'opds.nama',
                DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif = "AKTIF" THEN 1 ELSE 0 END), 0) as aktif'),
                DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif = "NONAKTIF" THEN 1 ELSE 0 END), 0) as nonaktif'),
                DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN employees.deleted_at IS NULL AND eq.employee_id IS NOT NULL THEN employees.id END), 0) as nametag'),
            ])
            ->leftJoin('employees', 'employees.opd_id', '=', 'opds.id')
            ->leftJoinSub($activeTokens, 'eq', function ($join) {
                $join->on('eq.employee_id', '=', 'employees.id');
            })
            ->groupBy('opds.id', 'opds.nama')
            ->orderBy('opds.nama')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'aktif' => (int) $row->aktif,
                'nonaktif' => (int) $row->nonaktif,
                'nametag' => (int) $row->nametag,
            ])
            ->values();

        $byUnit = OpdUnit::query()
            ->select([
                'opd_units.id',
                'opd_units.nama',
                DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif = "AKTIF" THEN 1 ELSE 0 END), 0) as aktif'),
                DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif = "NONAKTIF" THEN 1 ELSE 0 END), 0) as nonaktif'),
                DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN employees.deleted_at IS NULL AND eq.employee_id IS NOT NULL THEN employees.id END), 0) as nametag'),
            ])
            ->leftJoin('employees', 'employees.opd_unit_id', '=', 'opd_units.id')
            ->leftJoinSub($activeTokens, 'eq', function ($join) {
                $join->on('eq.employee_id', '=', 'employees.id');
            })
            ->groupBy('opd_units.id', 'opd_units.nama')
            ->orderBy('opd_units.nama')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'aktif' => (int) $row->aktif,
                'nonaktif' => (int) $row->nonaktif,
                'nametag' => (int) $row->nametag,
            ])
            ->values();

        return [
            'summary' => $summary,
            'by_opd' => $byOpd,
            'by_unit' => $byUnit,
        ];
    }
}

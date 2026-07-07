<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Opd;

class NametagData
{
    /**
     * Data untuk sisi depan nametag.
     */
    public static function buildFront(Employee $e): array
    {
        // Nama lengkap untuk sisi depan:
        // - tampilkan gelar_depan (jika ada) sebelum nama
        // - tampilkan nama utama
        // - jika ada gelar_belakang tambahkan dengan prefiks ", " (koma + spasi)
        $namaMain = trim(implode(' ', array_filter([
            $e->gelar_depan,
            $e->nama,
        ])));
        $gelarBelakang = trim((string)($e->gelar_belakang ?? ''));
        if ($gelarBelakang !== '') {
            $nama = $namaMain . ', ' . $gelarBelakang;
        } else {
            $nama = $namaMain;
        }

        // Pakai accessor kalau ada (nip sudah diberi spasi), kalau tidak ya mentah
        $nip = $e->nip_formatted ?? $e->nip ?? '';

        // Jabatan: gunakan jabatan_display jika ada dan tidak kosong, fallback ke jabatan
        $jabatan = (!empty(trim($e->jabatan_display ?? ''))) ? $e->jabatan_display : ($e->jabatan ?: '');

        return [
            'nama'    => $nama,
            'nip'     => $nip,
            'jabatan' => $jabatan,
        ];
    }

    /**
     * Normalize gelar/back-degree strings with quote escape support.
     *
     * Features:
     * - After each dot, capitalize first letter and lowercase rest (standard rule)
     * - Comma-separated blocks are trimmed and joined with comma+space
     * - Content inside double quotes "..." is preserved as-is (escapes normalization)
     *
     * Examples:
     * - "S.I.KOM., M.KESOS" -> "S.I.Kom., M.Kesos"
     * - "S.\"IP\"" -> "S.IP" (quote escape: preserve uppercase)
     * - "S.I.\"P\"" -> "S.I.P" (mixed: apply rule to S and I, preserve P)
     *
     * @param string $s Input gelar string
     * @return string Normalized gelar
     */
    public static function normalizeGelarPublic(string $s): string
    {
        // Step 1: Extract and preserve content inside double quotes
        $preservedMap = [];
        $placeholder = '__PRESERVED_%d__';

        $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $placeholder) {
            $idx = count($preservedMap);
            $key = sprintf($placeholder, $idx);
            $preservedMap[$key] = $matches[1];  // Store exact content inside quotes
            return $key;  // Replace with placeholder temporarily
        }, $s);

        // Step 2: Apply normalization on remaining parts (split by comma)
        $parts = array_map('trim', explode(',', $s));
        $outParts = [];

        foreach ($parts as $part) {
            if ($part === '') continue;

            // split into segments separated by dots, but keep dots when rebuilding
            $segs = preg_split('/(\.)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 0; $i < count($segs); $i++) {
                $seg = $segs[$i];

                // Skip dots
                if ($seg === '.') continue;
                if ($seg === '') continue;

                // If this segment is a placeholder (preserved content), keep as-is
                if (preg_match('/__PRESERVED_\d+__/', $seg)) {
                    continue;  // Will be restored later
                }

                // Otherwise, apply standard normalization: ucfirst(lowercase)
                $seg = mb_strtolower($seg, 'UTF-8');
                $segs[$i] = mb_convert_case(mb_substr($seg, 0, 1, 'UTF-8'), MB_CASE_UPPER, 'UTF-8')
                    . mb_substr($seg, 1, null, 'UTF-8');
            }

            $outParts[] = implode('', $segs);
        }

        $result = implode(', ', $outParts);

        // Step 3: Restore preserved (quoted) content
        foreach ($preservedMap as $key => $value) {
            $result = str_replace($key, $value, $result);
        }

        return $result;
    }

    /**
     * Data untuk sisi belakang nametag.
     */
    public static function buildBack(Employee $e): array
    {
        $opd  = $e->opd;      // relasi Employee->opd()
        $unit = $e->opdUnit;  // relasi Employee->opdUnit()
        $isDinasPendidikan = $opd && stripos(trim((string) $opd->nama), 'dinas pendidikan') !== false;

        /**
         * ===== Aturan baru "Unit Kerja" =====
         * - Kalau punya opd_unit_id & relasi unit ada → pakai nama unit.
         * - Kalau tidak ada unit → pakai nama OPD induk.
         * - Fallback terakhir → kolom nama_unit_opd / '-'.
         */
        if ($unit && $e->opd_unit_id) {
            $unitDisplay = $unit->nama;
            $unitCaseMode = $isDinasPendidikan ? 'none' : 'title';
        } elseif ($opd) {
            $unitDisplay = $opd->nama;
            $unitCaseMode = 'title';
        } else {
            $unitDisplay = $e->nama_unit_opd ?: '-';
            $unitCaseMode = 'title';
        }

        // Nama pegawai untuk bagian belakang: format "gelar_depan nama, gelar_belakang"
        // - tampilkan gelar_depan (jika ada) sebelum nama
        // - tampilkan nama utama ($e->nama)
        // - jika ada gelar_belakang tampilkan setelah koma
        $namaMain = trim(implode(' ', array_filter([
            $e->gelar_depan,
            $e->nama,
        ])));
        $gelarBelakang = trim((string)($e->gelar_belakang ?? ''));
        // Note: gelar_belakang is already normalized at SAVE time by EmployeeService,
        // so no need to re-normalize here.
        if ($gelarBelakang !== '') {
            $namaPegawai = $namaMain . ', ' . $gelarBelakang;
        } else {
            $namaPegawai = $namaMain;
        }

        $nipPegawai = $e->nip_formatted ?? $e->nip ?? '';

        // ===== ALAMAT: Prioritas unit (jika terisi) → fallback OPD → '-' =====
        // Ini siap untuk masa mendatang ketika unit alamat di-sync dari SSO
        if ($unit && $unit->alamat) {
            $alamatDisplay = $unit->alamat;
        } elseif ($opd) {
            $alamatDisplay = $opd->alamat;
        } else {
            $alamatDisplay = '-';
        }

        // ===== Data Sekda / TTD: langsung dari OPD "Sekretariat Daerah" di tabel opds =====
        $sekdaOpd = Opd::query()
            ->whereRaw('LOWER(nama) LIKE ?', ['%sekretariat daerah%'])
            ->orderBy('id', 'desc')
            ->first();

        $ttdNama    = $sekdaOpd?->pimpinan ?: null; // contoh: "Sahtiar"
        $ttdPangkat = $sekdaOpd?->pangkat  ?: null;
        $ttdNip     = $sekdaOpd?->nip      ?: null;

        return [
            // biodata pegawai (dipakai oleh key val_*)
            'val_nama'   => $namaPegawai,
            'val_nip'    => $nipPegawai,
            'val_jab'    => $e->jabatan ?: '-',
            'val_unit'   => $unitDisplay,
            'val_unit_case' => $unitCaseMode,
            'val_goldar' => $e->gol_darah ?: '-',
            'val_alamat' => $alamatDisplay,

            // blok TTD Sekda (dipakai oleh key ttd_*)
            'ttd_nama'    => $ttdNama,
            'ttd_pangkat' => $ttdPangkat,
            'ttd_nip'     => $ttdNip,
        ];
    }
}

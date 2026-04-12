<?php

namespace App\Support;

use App\Models\Employee;

class EmployeeBg
{
    /**
     * Kembalikan tipe jabatan PERSIS seperti di DB (dinormalisasi),
     * agar cocok dengan kunci di config('photo_bg.role_bg_colors').
     */
    public static function typeFromEmployee(Employee $e): string
    {
        $src = self::norm((string) ($e->jabatan_type ?? ''));

        // daftar nilai valid sesuai form/DB kamu
        $allow = [
            'PIMPINAN TINGGI PRATAMA',
            'ADMINISTRATOR',
            'PENGAWAS',
            'FUNGSIONAL',
            'PELAKSANA',
        ];

        if (in_array($src, $allow, true)) {
            return $src; // EXACT MATCH —> cocok dengan config
        }

        // Fallback: tebak dari 'jabatan' (kalau data lama/nihil)
        $txt = self::norm((string) ($e->jabatan ?? ''));
        if (str_contains($txt, 'PIMPINAN TINGGI')) return 'PIMPINAN TINGGI PRATAMA';
        foreach (['ADMINISTRATOR','PENGAWAS','FUNGSIONAL','PELAKSANA'] as $k) {
            if (str_contains($txt, $k)) return $k;
        }

        return '__DEFAULT__';
    }

    /**
     * Ambil warna latar dari config berdasarkan tipe (exact).
     */
    public static function bgHexForType(string $type): string
    {
        $colors = config('photo_bg.role_bg_colors', []);

        // exact (case & spasi sudah dinormalisasi)
        $key = self::norm($type);
        if (isset($colors[$key])) {
            return $colors[$key];
        }

        // Amanin alias "JPT" kalau masih ada yang kirim itu
        if ($key === 'JPT' && isset($colors['PIMPINAN TINGGI PRATAMA'])) {
            return $colors['PIMPINAN TINGGI PRATAMA'];
        }

        return $colors['__DEFAULT__'] ?? '#0F172A';
    }

    /** Normalisasi: trim, uppercase, rapikan spasi berlebih */
    private static function norm(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return mb_strtoupper($s, 'UTF-8');
    }
}

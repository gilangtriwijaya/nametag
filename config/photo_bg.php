<?php

return [

    // Warna latar foto per jenis jabatan
    'role_bg_colors' => [
        'PIMPINAN TINGGI PRATAMA' => '#ff0000', // merah
        'ADMINISTRATOR'           => '#0145ff', // biru 
        'PENGAWAS'                => '#008000', // hijau 
        'FUNGSIONAL'              => '#b7b7b7', // abu-abu
        'PELAKSANA'               => '#FFA500', // oranye
        '__DEFAULT__'             => '#0F172A', // slate-900 (fallback)
    ],

    // Toleransi chroma-key berbasis HSV (lebih longgar sedikit)
    'h_tolerance'     => 16,
    's_tolerance'     => 0.30,
    'v_tolerance'     => 0.30,

    // Feather pinggir (anti-jaggy)
    'feather_px'      => 1,   // 0..4 aman

    // Minimum coverage latar yang harus terdeteksi agar chroma-key dianggap berhasil
    'min_bg_coverage' => 0.20,

    // Opsi default pemfitingan ke box foto (bisa dioverride di pemanggil)
    'fit_to_box'      => true,           // paksa hasil akhir pas ke box
    'fit_mode'        => 'stretch',      // 'stretch' | 'contain'
    // 'target_w_px'   => null,          // opsional: paksa ukuran target (px)
    // 'target_h_px'   => null,

    // Ukuran slot foto default (px) yang dipakai saat menyimpan hasil crop.
    // Pastikan ini sesuai dengan ukuran canvas crop di form (default 560px).
    'slot_width_px'  => 560,
    'slot_height_px' => 560,

    // Fallback bila chroma-key gagal total: bingkai warna jabatan
    'fallback_frame' => [
        'padding_px' => 18,        // tebal bingkai
        'bg_hex'     => '#FFFFFF', // kanvas putih di balik foto
    ],
];

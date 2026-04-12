<?php

return [
    'dpi' => 300,

    'bg_removal' => [
        // 'gd' = legacy GD fallback, 'rembg_local' = call local rembg HTTP server
        // Default to rembg_local (local rembg HTTP server). Can be overridden with NAMETAG_BG_MODE env.
        'mode' => env('NAMETAG_BG_MODE', 'rembg_local'),
        'rembg_url' => env('NAMETAG_REMBG_URL', 'http://127.0.0.1:5000/api/remove?model=u2net'),
        // feather radius used by ImageMagick (blur radius) to soften alpha edges
        // tuned higher to reduce visible halos from automatic matting
        'feather' => (int) env('NAMETAG_BG_FEATHER', 8),
        // ImageMagick morphology tuning (used for mask cleanup before blur)
        'im_close_disk' => (int) env('NAMETAG_IM_CLOSE', 4),
        'im_erode_disk' => (int) env('NAMETAG_IM_ERODE', 3),

        // Optional edge decontamination (desaturate color spill on semi-transparent band)
        'edge_desaturate' => (bool) env('NAMETAG_EDGE_DESAT', true),
        // Saturation percent for edge band (e.g., 60 = reduce to 60%)
        'edge_desat_percent' => (int) env('NAMETAG_EDGE_SAT', 60),
        // Mask blur radius to define the band thickness around edges
        'edge_mask_blur' => (int) env('NAMETAG_EDGE_BLUR', 2),
        // Mask level stretch low/high (percent) to focus on semi-transparent range
        'edge_mask_level_low'  => (int) env('NAMETAG_EDGE_LEVEL_LOW', 20),
        'edge_mask_level_high' => (int) env('NAMETAG_EDGE_LEVEL_HIGH', 80),
        // Optional rembg alpha matting to improve boundary quality
        'alpha_matting' => (bool) env('NAMETAG_ALPHA_MATTING', true),
        'alpha_foreground_threshold' => (int) env('NAMETAG_ALPHA_FG', 240),
        'alpha_background_threshold' => (int) env('NAMETAG_ALPHA_BG', 10),
        'alpha_erode_size' => (int) env('NAMETAG_ALPHA_ERODE', 20),
    ],

    'force_sync_batch' => true,
    'line_height' => [
        'front'   => 1.5,
        'back'    => 1.5,
        'default' => 1.25, // fallback umum
    ],
    
    // Backwards-compatible single-font entry (kept for existing code).
    'font' => [
        'family'  => 'Inter', // nama bebas, dokumentasi saja
        'regular' => public_path('fonts/OpenSans-Regular.ttf'),
        'bold'    => public_path('fonts/OpenSans-Bold.ttf'),
    ],

    // Multiple named fonts (you can reference these in templates).
    // Keys: primary, secondary, tertiary — adjust paths to real TTF files.
    'fonts' => [
        'primary' => [
            'family'  => 'Inter',
            'regular' => public_path('fonts/OpenSans-Regular.ttf'),
            'bold'    => public_path('fonts/OpenSans-Bold.ttf'),
        ],
        'secondary' => [
            'family'  => 'Roboto',
            'regular' => public_path('fonts/OpenSauceSans-Regular.ttf'),
            'bold'    => public_path('fonts/OpenSauceSans-Bold.ttf'),
        ],
        'tertiary' => [
            'family'  => 'NotoSans',
            'regular' => public_path('fonts/OpenSauceOne-Regular.ttf'),
            'bold'    => public_path('fonts/OpenSauceOne-Bold.ttf'),
        ],
    ],

    'role_colors' => [
        'PIMPINAN TINGGI PRATAMA' => '#FF0000',
        'ADMINISTRATOR'           => '#0f4fff',
        'PENGAWAS'                => '#06fa64',
        'FUNGSIONAL'              => '#a9aaad',
        'PELAKSANA'               => '#FFA500',
        '__DEFAULT__'             => '#0F172A',
    ],

    'templates' => [
        'front' => [
            'size_mm'    => ['w' => 54.03, 'h' => 85.63],
            // relative path (resolved by NametagPathHelpers)
            'background' => 'templates/PolosFront.png',

            // foto + QR
            'photo'      => ['x'=>11.49,  'y'=>25,  'w'=>31.01, 'h'=>31.01, 'radius'=>3.06],
            'qr'         => ['x'=>20,'y'=>69.7, 'size'=>14],

            'texts' => [
                [
                    'key'        => 'title',
                    'x'          => 3,
                    'y'          => 17.43,
                    'w'          => 48,
                    'h'          => 7.11,
                    'align'      => 'center',
                    'uppercase'  => true,
                    'font'       => ['key'=>'secondary','size'=>2,'bold'=>true],
                    'text'       => 'Pemerintah Daerah',
                ],
                [
                    'key'        => 'instansi_title',
                    'x'          => 3,
                    'y'          => 19.92,
                    'w'          => 48,
                    'h'          => 7.11,
                    'align'      => 'center',
                    'uppercase'  => true,
                    'font'       => ['key'=>'secondary','size'=>2,'bold'=>true],
                    'text'       => 'Kabupaten Kepulauan Anambas',
                ],

                // NAMA
                [
                    'key'         => 'nama',
                    'x'           => 3,
                    'y'           => 60,
                    'w'           => 48,
                    'align'       => 'center',
                    'case'        => 'title',
                    'font'        => ['key'=>'secondary','size'=>2,'bold'=>true],
                    'line_height' => 1,
                    'flow'        => true,
                ],

                // NIP: label + nomor akan digabung di service => "NIP. 1996..."
                [
                    'key'         => 'nip',
                    'x'           => 3,   // ambil full lebar, center
                    'y'           => 62.5,
                    'w'           => 48,
                    'align'       => 'center',
                    'font'        => ['key'=>'primary','size'=>2],
                    'wrap'        => 1,
                    'line_height' => 2,
                    'flow'        => true,
                    // 'text' dikosongkan, nilai diisi otomatis dari service
                ],

                // JABATAN
                [
                    'key'         => 'jabatan',
                    'x'           => 3,
                    'y'           => 65,
                    'w'           => 48,
                    'align'       => 'center',
                    'case'        => 'title',
                    'font'        => ['key'=>'primary','size'=>2,'bold'=>true],
                    'wrap'        => 2,
                    'line_height' => 1.6,
                    'flow'        => true,
                ],
            ],
        ],

        'back' => [
            'size_mm'    => ['w' => 54.03, 'h' => 85.63],
            // relative path (resolved by NametagPathHelpers)
            'background' => 'templates/PolosBack.png',

            // slot stempel + TTD (service hanya baca x,y,size)
            // Tambahkan 'img_y_offset' (mm) untuk menggeser gambar saja
            'stamp' => [
                'x' => 12, 'y' => 45, 'size' => 18, 'img_y_offset' => 0,
            ],
            'signature' => [
                'x' => 23, 'y' => 43, 'size' => 22, 'img_y_offset' => 0,
            ],

            'texts' => [
                // label kiri
                ['key'=>'label_nama',   'x'=>2.53, 'y'=>17.07,'w'=>49.77,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'NAMA'],
                ['key'=>'titik_nama',   'x'=>14,'y'=>17.07,'w'=>1.53, 'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_nama',     'x'=>15.5,'y'=>17.07,'w'=>37,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'wrap'=>2,'flow'=>true],

                ['key'=>'label_nip',    'x'=>2.53, 'y'=>20.63,'w'=>49.77,'align'=>'left','case'=>'upper','font'=>['size'=>1.6],'text'=>'NIP.'],
                ['key'=>'titik_nip',    'x'=>14,'y'=>20.63,'w'=>1.53, 'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_nip',      'x'=>15.5,'y'=>20.63,'w'=>37,'align'=>'left','font'=>['size'=>1.6]],

                ['key'=>'label_jab',    'x'=>2.53, 'y'=>24.19,'w'=>49.77,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'JABATAN'],
                ['key'=>'titik_jab',    'x'=>14,'y'=>24.19,'w'=>1.53, 'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_jab',      'x'=>15.5,'y'=>24.19,'w'=>37,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'wrap'=>3,'flow'=>true],

                ['key'=>'label_unit',   'x'=>2.53, 'y'=>27.74,'w'=>49.77,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'UNIT KERJA'],
                ['key'=>'titik_unit',   'x'=>14,'y'=>27.74,'w'=>1.53, 'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_unit',     'x'=>15.5,'y'=>27.74,'w'=>37,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'wrap'=>2,'flow'=>true],

                ['key'=>'label_goldar', 'x'=>2.53, 'y'=>31.30,'w'=>49.77,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'GOL. DARAH'],
                ['key'=>'titik_goldar', 'x'=>14,'y'=>31.30,'w'=>1.53,'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_goldar',   'x'=>15.5,'y'=>31.30,'w'=>37,'align'=>'left','case'=>'upper','font'=>['size'=>1.6]],

                ['key'=>'label_alamat', 'x'=>2.53, 'y'=>34.86,'w'=>49.77,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'ALAMAT KTR.'],
                ['key'=>'titik_alamat', 'x'=>14,'y'=>34.86,'w'=>1.53,'align'=>'left','font'=>['size'=>1.6],'text'=>':'],
                ['key'=>'val_alamat',   'x'=>15.5,'y'=>34.86,'w'=>37,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'wrap'=>3],

                // BLOK TTD (dibaca service)
                // Place Sekda title above the stamp, and Sekda name below the stamp
                ['key'=>'ttd_title',    'x'=>26.80,'y'=>50,'w'=>45.94,'h'=>7.11,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'SEKRETARIS DAERAH,'],
                ['key'=>'ttd_nama',     'x'=>26.80,'y'=>46,'w'=>45.94,'h'=>7.11,'align'=>'left','case'=>'title','font'=>['size'=>1.6]],
                ['key'=>'ttd_sekda',    'x'=>26.80,'y'=>60,'w'=>45.94,'h'=>7.11,'align'=>'left','case'=>'title','font'=>['size'=>1.6],'text'=>'Sahtiar'],
            ],
        ],
    ],
    // Dispatch tuning for batch -> single job expansion
    'dispatch_chunk_size' => (int) env('NAMETAG_DISPATCH_CHUNK_SIZE', 20),
    'dispatch_chunk_delay_seconds' => (int) env('NAMETAG_DISPATCH_CHUNK_DELAY', 0),
    // Estimated seconds per item (used by controller initial ETA)
    'estimated_seconds_per_item' => (float) env('NAMETAG_ESTIMATED_SECONDS_PER_ITEM', 0.6),
];

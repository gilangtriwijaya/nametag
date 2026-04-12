<?php

return [
    // Semantic pipeline version for derived images
    'version' => '3.1.0',

    // Default rembg model to record in manifests
    'rembg_model' => env('REMBG_MODEL', 'u2net'),

    // Path to executable wrapper which invokes rembg in the virtualenv.
    // Default points to the installed wrapper created by deployment.
    'rembg_bin' => env('REMBG_BIN', '/usr/local/bin/rembg-wrapper'),

    // Halo / morphological defaults used by the pipeline (informational)
    'halo' => [
        'morph' => 2,
        'erode' => 1,
        'blur'  => 1,
    ],

    // Failure policy: one of 'use_original'|'use_previous'|'placeholder'|'fail'
    // Default: 'use_original' — prefer showing original photo when cleaning fails.
    'failure_policy' => env('PHOTO_PIPELINE_FAILURE', 'use_original'),

    // Job-level photo styles. Keys are normalized job type identifiers.
    // Each entry may contain: bg (hex), style (solid|gradient|bw|frame), metadata...
    'job_styles' => [
        // fallback
        '__DEFAULT__' => [
            'bg' => '#0F172A',
            'style' => 'solid',
        ],

        // example mapping (keep in-sync with existing config/photo_bg.role_bg_colors)
        'PIMPINAN TINGGI PRATAMA' => ['bg' => '#ff0000', 'style' => 'solid'],
        'ADMINISTRATOR'           => ['bg' => '#0145ff', 'style' => 'solid'],
        'PENGAWAS'                => ['bg' => '#0ae70a', 'style' => 'solid'],
        'FUNGSIONAL'              => ['bg' => '#808080', 'style' => 'solid'],
        'PELAKSANA'               => ['bg' => '#FFA500', 'style' => 'solid'],
    ],
];

<?php

return [
    // Aktif/nonaktifkan pencatatan
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    // Tulis log sinkron (tanpa queue). Jika kamu pakai queue worker, baru set true.
    'queue' => env('ACTIVITY_LOGGER_QUEUE', false),

    // Nama log default (boleh diganti per-event dengan activity('xxx'))
    'default_log_name' => 'app',

    // Model & koneksi/tabel yang dipakai paket
    'activity_model'       => \Spatie\Activitylog\Models\Activity::class,
    'database_connection'  => env('ACTIVITY_LOGGER_DB_CONNECTION', null), // null = koneksi default
    'table_name'           => 'activity_log', // ← penting: singular (sesuai skema paket)

    // Driver auth yang dipakai untuk causer()
    'default_auth_driver' => 'web',

    // Akses entitas yang di-soft-delete
    'subject_returns_soft_deleted_models' => true,
    'causer_returns_soft_deleted_models'  => true,

    // Jangan simpan entri kosong (tanpa perubahan)
    'submit_empty_logs' => false,
];

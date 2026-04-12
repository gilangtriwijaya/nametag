<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Laravel 11 style:
| - File ini untuk closure command sederhana saja.
| - Class-based commands diregister lewat app/Console/Kernel.php
|   (via $commands atau $this->load(__DIR__.'/Commands')).
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTE:
// JANGAN register command class di sini.
// JANGAN tulis referensi ke class yang sudah dihapus (MirrorOpdMasterFromSso, dll).

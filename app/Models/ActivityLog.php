<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    // Sesuai tabel default Spatie
    protected $table = 'activity_log';

    // Kita insert manual, jadi guard semua kolom
    protected $guarded = [];

    // Kolom properties disimpan JSON
    protected $casts = [
        'properties' => 'array',
    ];

    // Biar bisa dipakai di Blade kalau diperlukan
    public function subject()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->morphTo();
    }
}

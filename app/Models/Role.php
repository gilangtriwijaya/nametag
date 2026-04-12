<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // Tetap pakai tabel 'roles' bawaan Spatie
    protected $table = 'roles';

    // Biarkan 'opd_id' bisa diisi (Spatie default: guarded = ['id'])
    protected $guarded = ['id'];

    protected $casts = [
        'opd_id' => 'integer',
    ];

    // Relasi opsional
    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }

    // Scope memudahkan query
    public function scopeGlobal($q)
    {
        return $q->whereNull('opd_id');
    }

    public function scopeForOpd($q, $opdId)
    {
        return $q->where('opd_id', $opdId);
    }
}

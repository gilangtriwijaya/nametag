<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdUnit extends Model
{
    use SoftDeletes;

    protected $table = 'opd_units';

    /**
     * Hanya kolom yang benar-benar ada di tabel.
     */
    protected $fillable = [
        'opd_id',
        'type',
        'code',      // <- di DB namanya "code" (bukan "kode")
        'nama',
        'status',    // AKTIF / NONAKTIF
        'alamat',
        'kecamatan',
    ];

    /**
     * Casting seperlunya.
     */
    protected $casts = [
        'opd_id' => 'int',
    ];

    /* ===== Relations ===== */
    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'opd_unit_id');
    }

    /**
     * TIDAK ada audit kolom created_by/updated_by/deleted_by di tabel ini,
     * jadi jangan isi apapun di event creating/updating/deleting.
     */
}

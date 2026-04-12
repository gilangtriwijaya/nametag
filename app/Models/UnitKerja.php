<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitKerja extends Model
{
    use SoftDeletes;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'opd_id',
        'code',
        'nama',
        'status',
        'alamat',
        'kecamatan',
    ];

    protected $casts = [
        'opd_id' => 'int',
    ];

    public function opd()
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'unit_kerja_id');
    }
}

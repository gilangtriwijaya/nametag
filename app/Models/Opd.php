<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opd extends Model
{
    protected $table = 'opds';

    protected $fillable = [
        'nama', 'slug', 'pimpinan', 'alamat', 'telepon',
        'ttd_file_path',  'nip', 'pangkat', 'golongan', 'created_by', 'updated_by',
    ];

    protected $appends = ['pangkat_gol'];

    public function getTtdPathAttribute()
    {
        return $this->attributes['ttd_file_path'] ?? null;
    }

    public function getPangkatGolAttribute(): ?string
    {
        if (!$this->pangkat && !$this->golongan) return null;
        if ($this->pangkat && $this->golongan) return "{$this->pangkat} / {$this->golongan}";
        return $this->pangkat ?: $this->golongan;
    }
    
    public $timestamps = true;
    
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
    
    public function units()
    {
        return $this->hasMany(\App\Models\OpdUnit::class, 'opd_id');
    
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NametagBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'nametag_batches';

    protected $fillable = [
        'id','user_id','opd_id','opd_unit_id','employee_ids','total','done','fail','skipped','status','started_at','finished_at'
    ];

    protected $casts = [
        'employee_ids' => 'array',
        'total' => 'integer',
        'done' => 'integer',
        'fail' => 'integer',
        'skipped' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (empty($m->id)) $m->id = (string) Str::uuid();
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAccessLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_at = now();
        });
    }
}

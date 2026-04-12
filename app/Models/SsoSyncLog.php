<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoSyncLog extends Model
{
    protected $table = 'sso_sync_logs';

    protected $fillable = [
        'command', 'started_at', 'finished_at', 'status', 'message'
    ];

    public $timestamps = true;

    protected $dates = ['started_at', 'finished_at', 'created_at', 'updated_at'];
}

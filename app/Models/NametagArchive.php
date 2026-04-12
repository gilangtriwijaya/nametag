<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NametagArchive extends Model
{
    protected $table = 'nametag_archives';

    protected $fillable = [
        'user_id', 'name', 'count', 'path', 'status', 'notes'
    ];
}

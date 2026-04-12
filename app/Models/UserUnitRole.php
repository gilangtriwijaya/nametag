<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUnitRole extends Model
{
    protected $table = 'user_unit_roles';

    protected $fillable = ['user_id','opd_unit_id','role'];

    public function user()     { return $this->belongsTo(User::class); }
    public function opdUnit()  { return $this->belongsTo(OpdUnit::class, 'opd_unit_id'); }
}

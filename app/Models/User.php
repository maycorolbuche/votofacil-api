<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'room_id',
        'device_id',
        'is_primary',
        'name',
    ];
}
